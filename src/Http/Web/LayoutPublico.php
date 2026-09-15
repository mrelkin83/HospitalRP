<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Config\ConfigAplicacion;
use App\Core\Config\ConfigDinamica;
use App\Core\Support\Vistas;
use App\Modules\Contenido\Repository\AvisoRepository;
use App\Modules\Contenido\Repository\PopupRepository;
use App\Modules\Sistema\Repository\MenuRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * Arma toda página pública: barra de accesibilidad, encabezado con menús
 * editables, aviso vigente (o modo emergencia) y pie normativo con la
 * configuración dinámica. Los controladores solo aportan su contenido.
 */
final readonly class LayoutPublico
{
    public function __construct(
        private Vistas $vistas,
        private ConfigDinamica $config,
        private ConfigAplicacion $aplicacion,
        private MenuRepository $menus,
        private AvisoRepository $avisos,
        private PopupRepository $popups,
    ) {
    }

    public function responder(
        string $titulo,
        ?string $descripcion,
        string $contenido,
        bool $noindex = false,
        int $estado = Response::HTTP_OK,
        bool $esPortada = false,
    ): Response {
        $aviso = $this->avisos->vigente();

        // Ruta actual para el canonical y OpenGraph. Se toma del superglobal
        // y no de un parámetro: evitaría pasar el Request por cada uno de
        // los controladores públicos solo para esto.
        $rutaActual = rawurldecode(
            (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH),
        );

        $html = $this->vistas->render('publico/base', [
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'contenido' => $contenido,
            'noindex' => $noindex,
            'esPortada' => $esPortada,
            'aviso' => $aviso,
            'emergencia' => $aviso !== null && $aviso->esCritico(),
            // Un aviso crítico transforma la portada para que el teléfono sea
            // lo primero; taparlo con una ventana emergente encima iría justo
            // contra eso. En emergencia no hay popup, sea cual sea.
            'popup' => $aviso !== null && $aviso->esCritico()
                ? null
                : $this->popups->vigente($esPortada),
            'menuSuperior' => $this->menus->itemsPorUbicacion('superior'),
            'menuPrincipal' => $this->menus->itemsPorUbicacion('principal'),
            'menuPie1' => $this->menus->itemsPorUbicacion('pie_1'),
            'menuPie2' => $this->menus->itemsPorUbicacion('pie_2'),
            'config' => $this->config,
            'urlCanonica' => $this->aplicacion->url . ($rutaActual === '/' ? '/' : $rutaActual),
        ]);

        $respuesta = new Response($html, $estado, ['Content-Type' => 'text/html; charset=utf-8']);

        // Página anónima: cacheable un rato. Sin sesión no hay nada personal,
        // y un minuto de retraso en un cambio de contenido es aceptable.
        if ($estado === Response::HTTP_OK) {
            $respuesta->setPublic();
            $respuesta->setMaxAge(60);
        }

        return $respuesta;
    }
}

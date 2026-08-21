<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Config\ConfigDinamica;
use App\Core\Support\Vistas;
use App\Modules\Contenido\Repository\AvisoRepository;
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
        private MenuRepository $menus,
        private AvisoRepository $avisos,
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

        $html = $this->vistas->render('publico/base', [
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'contenido' => $contenido,
            'noindex' => $noindex,
            'esPortada' => $esPortada,
            'aviso' => $aviso,
            'emergencia' => $aviso !== null && $aviso->esCritico(),
            'menuSuperior' => $this->menus->itemsPorUbicacion('superior'),
            'menuPrincipal' => $this->menus->itemsPorUbicacion('principal'),
            'menuPie1' => $this->menus->itemsPorUbicacion('pie_1'),
            'menuPie2' => $this->menus->itemsPorUbicacion('pie_2'),
            'config' => $this->config,
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

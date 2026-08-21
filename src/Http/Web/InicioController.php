<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Config\ConfigDinamica;
use App\Core\Storage\AlmacenArchivos;
use App\Core\Support\Vistas;
use App\Modules\Contenido\Repository\NoticiaRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Home del sitio público (Fase 1, paso 9), según frontend.png: héroe,
 * buscador, ocho accesos y noticias reales. Máximo ocho acciones destacadas
 * (CLAUDE.md §6): las sedes llegan con la Fase 2.
 */
final readonly class InicioController
{
    public function __construct(
        private LayoutPublico $layout,
        private Vistas $vistas,
        private ConfigDinamica $config,
        private NoticiaRepository $noticias,
        private AlmacenArchivos $almacen,
    ) {
    }

    public function index(Request $peticion): Response
    {
        $noticias = $this->noticias->publicasRecientes(3);

        $imagenes = $this->almacen->porIds(array_values(array_filter(array_map(
            static fn ($n): ?int => $n->imagenId,
            $noticias,
        ))));

        return $this->layout->responder(
            titulo: 'Inicio',
            descripcion: sprintf(
                '%s: citas, urgencias, especialidades, trámites y PQRSDF en línea. %s',
                $this->config->texto('entidad.nombre'),
                $this->config->texto('entidad.eslogan'),
            ),
            contenido: $this->vistas->render('publico/inicio', [
                'noticias' => $noticias,
                'imagenes' => $imagenes,
                'config' => $this->config,
            ]),
            esPortada: true,
        );
    }

    /**
     * El buscador real llega en la Fase 6; mientras tanto la ruta existe y
     * responde con honestidad en vez de dar 404.
     */
    public function buscar(Request $peticion): Response
    {
        return $this->layout->responder(
            titulo: 'Buscador',
            descripcion: null,
            contenido: $this->vistas->render('publico/mensaje', [
                'encabezado' => 'El buscador está en construcción',
                'mensaje' => 'Muy pronto podrás buscar en todo el sitio desde aquí. '
                    . 'Mientras tanto, usa el menú principal o comunícate con nuestras líneas de atención.',
            ]),
            noindex: true,
        );
    }
}

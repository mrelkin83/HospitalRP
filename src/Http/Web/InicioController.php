<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Config\ConfigDinamica;
use App\Core\Storage\AlmacenArchivos;
use App\Core\Support\Vistas;
use App\Modules\Contenido\Repository\NoticiaRepository;
use App\Modules\Directorio\Repository\SedeRepository;
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
        private SedeRepository $sedes,
        private AlmacenArchivos $almacen,
    ) {
    }

    public function index(Request $peticion): Response
    {
        $noticias = $this->noticias->publicasRecientes(3);
        $sedes = array_slice($this->sedes->activasPublicas(), 0, 3);

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
                'sedes' => $sedes,
                'config' => $this->config,
            ]),
            esPortada: true,
        );
    }
}

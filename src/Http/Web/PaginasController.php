<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Storage\AlmacenArchivos;
use App\Core\Support\Vistas;
use App\Modules\Contenido\Repository\PaginaRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Páginas institucionales por ruta dinámica. Es la ÚLTIMA ruta del sitio:
 * lo que ninguna ruta fija atrapó, o es una página del panel de contenido,
 * o es un 404 honesto.
 */
final readonly class PaginasController
{
    public function __construct(
        private LayoutPublico $layout,
        private Vistas $vistas,
        private PaginaRepository $paginas,
        private AlmacenArchivos $almacen,
    ) {
    }

    public function mostrar(Request $peticion): Response
    {
        $pagina = $this->paginas->publicaPorRuta((string) $peticion->attributes->get('ruta'));

        if ($pagina === null) {
            return $this->layout->responder(
                titulo: 'Página no encontrada',
                descripcion: null,
                contenido: $this->vistas->render('publico/mensaje', [
                    'encabezado' => 'Página no encontrada',
                    'mensaje' => 'La página que buscas no existe o cambió de dirección. '
                        . 'Revisa el menú principal o usa los teléfonos de la parte superior.',
                ]),
                noindex: true,
                estado: Response::HTTP_NOT_FOUND,
            );
        }

        $this->paginas->contarVista($pagina->id);

        return $this->layout->responder(
            titulo: $pagina->metaTitulo ?? $pagina->titulo,
            descripcion: $pagina->metaDescripcion ?? $pagina->resumen,
            contenido: $this->vistas->render('publico/pagina', [
                'pagina' => $pagina,
                'imagen' => $pagina->imagenId !== null ? $this->almacen->porId($pagina->imagenId) : null,
            ]),
            noindex: $pagina->noindex,
        );
    }
}

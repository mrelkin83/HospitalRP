<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Storage\AlmacenArchivos;
use App\Core\Support\Vistas;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Galería de imágenes. Cierra la última sección de PRENSA que el Joomla tenía
 * y el sitio nuevo no (docs/14 §2).
 *
 * No es un módulo nuevo: la biblioteca de medios ya existe en el panel y
 * guarda `publico`, `texto_alt`, `ancho` y `alto`. Aquí solo se expone lo
 * marcado como público, y esa condición no es un detalle — la biblioteca
 * contiene también adjuntos de trámites de la ciudadanía.
 */
final readonly class GaleriaController
{
    private const int POR_PAGINA = 24;

    public function __construct(
        private LayoutPublico $layout,
        private Vistas $vistas,
        private AlmacenArchivos $almacen,
    ) {
    }

    public function mostrar(Request $peticion): Response
    {
        $pagina = max(1, $peticion->query->getInt('pagina', 1));
        ['imagenes' => $imagenes, 'total' => $total] = $this->almacen->imagenesPublicas($pagina, self::POR_PAGINA);

        return $this->layout->responder(
            titulo: 'Galería de imágenes',
            descripcion: 'Galería de imágenes de la E.S.E. Hospital Rosario Pumarejo de López: '
                . 'jornadas, campañas y actividades institucionales.',
            contenido: $this->vistas->render('publico/galeria', [
                'imagenes' => $imagenes,
                'pagina' => $pagina,
                'totalPaginas' => max(1, (int) ceil($total / self::POR_PAGINA)),
                'total' => $total,
            ]),
        );
    }
}

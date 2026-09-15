<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Storage\AlmacenArchivos;
use App\Core\Support\Vistas;
use App\Modules\Contenido\Repository\NoticiaRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Noticias de cara al ciudadano: solo lo publicado y dentro de su ventana.
 */
final readonly class NoticiasPublicoController
{
    private const int POR_PAGINA = 12;

    public function __construct(
        private LayoutPublico $layout,
        private Vistas $vistas,
        private NoticiaRepository $noticias,
        private AlmacenArchivos $almacen,
        private Connection $conexion,
    ) {
    }

    /**
     * Listado, entero o acotado a una categoría.
     *
     * La misma acción sirve a `/noticias` y a `/noticias/categoria/{slug}`
     * porque es la misma página con un filtro: duplicarla solo llevaría a que
     * una de las dos se quedara atrás.
     */
    public function listado(Request $peticion): Response
    {
        $slugCategoria = (string) $peticion->attributes->get('categoria', '');
        $categoria = $slugCategoria === '' ? null : $this->noticias->categoriaPorSlug($slugCategoria);

        // Una categoría inventada en la URL es un 404 honesto, no el listado
        // completo disfrazado: el visitante pidió algo que no existe.
        if ($slugCategoria !== '' && $categoria === null) {
            return $this->layout->responder(
                titulo: 'Categoría no encontrada',
                descripcion: null,
                contenido: $this->vistas->render('publico/mensaje', [
                    'encabezado' => 'Categoría no encontrada',
                    'mensaje' => 'Esa categoría de noticias no existe. Consulta el listado completo de noticias.',
                ]),
                noindex: true,
                estado: Response::HTTP_NOT_FOUND,
            );
        }

        $pagina = max(1, $peticion->query->getInt('pagina', 1));
        ['noticias' => $noticias, 'total' => $total] = $this->noticias->publicasPaginadas(
            $pagina,
            self::POR_PAGINA,
            $categoria === null ? null : $categoria['id'],
        );

        $imagenes = $this->almacen->porIds(array_values(array_filter(array_map(
            static fn ($n): ?int => $n->imagenId,
            $noticias,
        ))));

        return $this->layout->responder(
            titulo: $categoria === null ? 'Noticias' : $categoria['nombre'],
            descripcion: $categoria === null
                ? 'Noticias y novedades de la E.S.E. Hospital Rosario Pumarejo de López.'
                : sprintf('%s de la E.S.E. Hospital Rosario Pumarejo de López.', $categoria['nombre']),
            contenido: $this->vistas->render('publico/noticias/listado', [
                'noticias' => $noticias,
                'imagenes' => $imagenes,
                'pagina' => $pagina,
                'totalPaginas' => max(1, (int) ceil($total / self::POR_PAGINA)),
                'categorias' => $this->noticias->categoriasConNoticias(),
                'categoriaActiva' => $categoria,
            ]),
        );
    }

    public function detalle(Request $peticion): Response
    {
        $noticia = $this->noticias->publicaPorSlug((string) $peticion->attributes->get('slug'));

        if ($noticia === null) {
            return $this->layout->responder(
                titulo: 'Noticia no encontrada',
                descripcion: null,
                contenido: $this->vistas->render('publico/mensaje', [
                    'encabezado' => 'Noticia no encontrada',
                    'mensaje' => 'La noticia que buscas no existe, fue retirada o cambió de dirección.',
                ]),
                noindex: true,
                estado: Response::HTTP_NOT_FOUND,
            );
        }

        $this->conexion->executeStatement('UPDATE noticias SET vistas = vistas + 1 WHERE id = ?', [$noticia->id]);

        return $this->layout->responder(
            titulo: $noticia->metaTitulo ?? $noticia->titulo,
            descripcion: $noticia->metaDescripcion ?? $noticia->resumen,
            contenido: $this->vistas->render('publico/noticias/detalle', [
                'noticia' => $noticia,
                'imagen' => $noticia->imagenId !== null ? $this->almacen->porId($noticia->imagenId) : null,
            ]),
        );
    }
}

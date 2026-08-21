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

    public function listado(Request $peticion): Response
    {
        $pagina = max(1, $peticion->query->getInt('pagina', 1));
        ['noticias' => $noticias, 'total' => $total] = $this->noticias->publicasPaginadas($pagina, self::POR_PAGINA);

        $imagenes = $this->almacen->porIds(array_values(array_filter(array_map(
            static fn ($n): ?int => $n->imagenId,
            $noticias,
        ))));

        return $this->layout->responder(
            titulo: 'Noticias',
            descripcion: 'Noticias y novedades de la E.S.E. Hospital Rosario Pumarejo de López.',
            contenido: $this->vistas->render('publico/noticias/listado', [
                'noticias' => $noticias,
                'imagenes' => $imagenes,
                'pagina' => $pagina,
                'totalPaginas' => max(1, (int) ceil($total / self::POR_PAGINA)),
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

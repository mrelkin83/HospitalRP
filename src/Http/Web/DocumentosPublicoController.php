<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Storage\AlmacenArchivos;
use App\Core\Support\Vistas;
use App\Modules\Contenido\Repository\CategoriaRepository;
use App\Modules\Documentos\Repository\DocumentoRepository;
use App\Modules\Documentos\Request\FiltrosDocumento;
use App\Modules\Sistema\Repository\DependenciaRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Buscador documental y ficha pública. Los PDF nunca son la interfaz
 * (CLAUDE.md §6): la ficha HTML lleva los metadatos y el PDF es un botón.
 */
final readonly class DocumentosPublicoController
{
    public function __construct(
        private LayoutPublico $layout,
        private Vistas $vistas,
        private DocumentoRepository $documentos,
        private DependenciaRepository $dependencias,
        private CategoriaRepository $categorias,
        private AlmacenArchivos $almacen,
    ) {
    }

    public function buscador(Request $peticion): Response
    {
        // `?categoria=` admite id o slug. El slug es lo que usan los ítems de
        // menú —«Revista Conexión Salud»— porque un id cambia entre
        // instalaciones según el orden de sembrado y dejaría el enlace roto.
        $porSlug = $peticion->query->getString('categoria');

        if ($porSlug !== '' && !ctype_digit($porSlug)) {
            $categoria = $this->categorias->porSlug('documento', $porSlug);

            // Un slug que no existe NO puede caer al listado completo: sería
            // devolver otra cosa y llamarla lo que se pidió. Mismo criterio
            // que en las categorías de noticias.
            if ($categoria === null) {
                return $this->layout->responder(
                    titulo: 'Categoría no encontrada',
                    descripcion: null,
                    contenido: $this->vistas->render('publico/mensaje', [
                        'encabezado' => 'Categoría no encontrada',
                        'mensaje' => 'Esa categoría de documentos no existe. '
                            . 'Consulta el buscador completo de documentos.',
                    ]),
                    noindex: true,
                    estado: Response::HTTP_NOT_FOUND,
                );
            }

            $peticion->query->set('categoria', (string) $categoria['id']);
        }

        $filtros = FiltrosDocumento::desdePeticion($peticion);
        ['documentos' => $documentos, 'total' => $total] = $this->documentos->buscarPublicos($filtros);

        return $this->layout->responder(
            titulo: 'Documentos',
            descripcion: 'Normatividad, actos administrativos e informes de la E.S.E. Hospital Rosario Pumarejo de López.',
            contenido: $this->vistas->render('publico/documentos/buscador', [
                'documentos' => $documentos,
                'total' => $total,
                'filtros' => $filtros,
                'dependencias' => $this->dependencias->activas(),
                'categorias' => $this->categorias->activasPorTipo('documento'),
                'anios' => $this->documentos->aniosDisponibles(),
            ]),
        );
    }

    public function ficha(Request $peticion): Response
    {
        $documento = $this->documentos->publicoPorId($peticion->attributes->getInt('id'));

        if ($documento === null) {
            return $this->layout->responder(
                titulo: 'Documento no encontrado',
                descripcion: null,
                contenido: $this->vistas->render('publico/mensaje', [
                    'encabezado' => 'Documento no encontrado',
                    'mensaje' => 'El documento que buscas no existe o fue retirado. Usa el buscador documental.',
                ]),
                noindex: true,
                estado: Response::HTTP_NOT_FOUND,
            );
        }

        return $this->layout->responder(
            titulo: $documento->titulo,
            descripcion: mb_substr($documento->resumen, 0, 300),
            contenido: $this->vistas->render('publico/documentos/ficha', [
                'documento' => $documento,
                'archivo' => $this->almacen->porId($documento->archivoId),
                'reemplazadoPor' => $documento->estado->value === 'derogado'
                    ? $this->documentos->reemplazadoPor($documento->id)
                    : null,
            ]),
        );
    }

    public function descargar(Request $peticion): Response
    {
        $documento = $this->documentos->publicoPorId($peticion->attributes->getInt('id'));

        if ($documento === null) {
            return new Response('No encontrado', Response::HTTP_NOT_FOUND, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $this->documentos->contarDescarga($documento->id);

        // La entrega física la hace el controlador de archivos, que valida
        // permisos y cabeceras; aquí solo se cuenta la descarga del documento.
        return new RedirectResponse('/archivos/' . $documento->archivoId, Response::HTTP_FOUND);
    }
}

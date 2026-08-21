<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Auth\Actor;
use App\Core\Auth\Sesion;
use App\Core\Storage\AlmacenArchivos;
use App\Core\Storage\ArchivoInvalidoException;
use App\Core\Support\Vistas;
use App\Modules\Contenido\Repository\CategoriaRepository;
use App\Modules\Documentos\Entity\Documento;
use App\Modules\Documentos\Entity\EstadoDocumento;
use App\Modules\Documentos\Exception\PublicacionIncompletaException;
use App\Modules\Documentos\Repository\DocumentoRepository;
use App\Modules\Documentos\Request\DocumentoDatos;
use App\Modules\Documentos\Service\DocumentoService;
use App\Modules\Sistema\Repository\DependenciaRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gestión documental en el panel: ficha completa (TRD incluida), PDF
 * validado por contenido, versiones con historial y estados normativos.
 */
final readonly class DocumentosController
{
    public function __construct(
        private Sesion $sesion,
        private Vistas $vistas,
        private DocumentoService $servicio,
        private DocumentoRepository $documentos,
        private DependenciaRepository $dependencias,
        private CategoriaRepository $categorias,
        private AlmacenArchivos $almacen,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        return $this->pagina('Documentos', $this->vistas->render('admin/documentos/listado', [
            'documentos' => $this->documentos->listarParaPanel(),
            'mensaje' => $this->mensajeDeEstado($peticion),
        ]));
    }

    public function formularioNueva(Request $peticion): Response
    {
        return $this->pagina('Nuevo documento', $this->renderFormulario(null, null, []));
    }

    public function guardar(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $datos = DocumentoDatos::desdePeticion($peticion);
        $errores = $datos->validar();
        $archivoId = $this->procesarPdf($peticion, $errores, obligatorio: true);

        if ($errores !== []) {
            return $this->pagina('Nuevo documento', $this->renderFormulario(null, $datos, $errores), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        assert($archivoId !== null);
        $id = $this->servicio->crear($datos, $archivoId, Actor::desde($this->sesion, $peticion));

        return new RedirectResponse('/admin/documentos/' . $id . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    public function formularioEditar(Request $peticion): Response
    {
        $documento = $this->documentos->porId($peticion->attributes->getInt('id'));

        if ($documento === null) {
            return $this->noEncontrado();
        }

        $errorPublicacion = $peticion->query->getString('falta');

        return $this->pagina('Editar documento', $this->renderFormulario(
            $documento,
            null,
            $errorPublicacion !== '' ? ['publicacion' => $errorPublicacion] : [],
            $this->mensajeDeEstado($peticion),
        ));
    }

    public function actualizar(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $documento = $this->documentos->porId($peticion->attributes->getInt('id'));

        if ($documento === null) {
            return $this->noEncontrado();
        }

        $datos = DocumentoDatos::desdePeticion($peticion);
        $errores = $datos->validar();

        if ($errores !== []) {
            return $this->pagina('Editar documento', $this->renderFormulario($documento, $datos, $errores), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->servicio->actualizar($documento->id, $datos, Actor::desde($this->sesion, $peticion));

        return new RedirectResponse('/admin/documentos/' . $documento->id . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    public function publicar(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');

        try {
            if ($peticion->request->getBoolean('publicado')) {
                $this->servicio->publicar($id, Actor::desde($this->sesion, $peticion));
            } else {
                $this->servicio->despublicar($id, Actor::desde($this->sesion, $peticion));
            }
        } catch (PublicacionIncompletaException $e) {
            return new RedirectResponse('/admin/documentos/' . $id . '?falta=' . urlencode($e->getMessage()), Response::HTTP_SEE_OTHER);
        }

        return new RedirectResponse('/admin/documentos/' . $id . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    public function cambiarEstado(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $estado = EstadoDocumento::tryFrom($peticion->request->getString('estado'));

        if ($estado !== null) {
            $this->servicio->cambiarEstado($id, $estado, Actor::desde($this->sesion, $peticion));
        }

        return new RedirectResponse('/admin/documentos/' . $id . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    public function nuevaVersion(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $errores = [];
        $archivoId = $this->procesarPdf($peticion, $errores, obligatorio: true);

        if ($archivoId === null) {
            return new RedirectResponse('/admin/documentos/' . $id . '?falta=' . urlencode($errores['archivo'] ?? 'Elige el PDF de la nueva versión.'), Response::HTTP_SEE_OTHER);
        }

        $notas = trim($peticion->request->getString('notas'));
        $version = $this->servicio->nuevaVersion($id, $archivoId, $notas !== '' ? $notas : null, Actor::desde($this->sesion, $peticion));

        return new RedirectResponse('/admin/documentos/' . $id . '?version=' . $version, Response::HTTP_SEE_OTHER);
    }

    public function eliminar(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $this->servicio->eliminar($peticion->attributes->getInt('id'), Actor::desde($this->sesion, $peticion));

        return new RedirectResponse('/admin/documentos?eliminado=1', Response::HTTP_SEE_OTHER);
    }

    // --- soporte ---

    /**
     * @param array<string, string> $errores
     */
    private function procesarPdf(Request $peticion, array &$errores, bool $obligatorio): ?int
    {
        $archivo = $peticion->files->get('archivo');

        if (!$archivo instanceof UploadedFile) {
            if ($obligatorio) {
                $errores['archivo'] = 'Adjunta el PDF del documento.';
            }

            return null;
        }

        // Solo PDF en gestión documental: se verifica el contenido real antes
        // de tocar el almacén para no dejar archivos huérfanos.
        if ($archivo->getMimeType() !== 'application/pdf') {
            $errores['archivo'] = 'El archivo debe ser un PDF. Se detectó otro tipo de contenido.';

            return null;
        }

        try {
            return $this->almacen->guardar($archivo, publico: true, creadoPor: $this->sesion->usuarioId())->id;
        } catch (ArchivoInvalidoException $e) {
            $errores['archivo'] = $e->getMessage();

            return null;
        }
    }

    /**
     * @param array<string, string> $errores
     */
    private function renderFormulario(?Documento $documento, ?DocumentoDatos $enviado, array $errores, ?string $mensaje = null): string
    {
        return $this->vistas->render('admin/documentos/formulario', [
            'documento' => $documento,
            'enviado' => $enviado,
            'errores' => $errores,
            'mensaje' => $mensaje,
            'dependencias' => $this->dependencias->activas(),
            'categorias' => $this->categorias->activasPorTipo('documento'),
            'versiones' => $documento !== null ? $this->documentos->versionesDe($documento->id) : [],
            'tokenCsrf' => $this->sesion->tokenCsrf(),
        ]);
    }

    private function pagina(string $titulo, string $contenido, int $estado = Response::HTTP_OK): Response
    {
        $this->sesion->iniciar();

        $html = $this->vistas->render('admin/base', [
            'titulo' => $titulo,
            'usuarioNombre' => $this->sesion->usuarioNombre() ?? 'Usuario',
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'contenido' => $contenido,
        ]);

        $respuesta = new Response($html, $estado, ['Content-Type' => 'text/html; charset=utf-8']);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }

    private function exigirCsrf(Request $peticion): ?Response
    {
        $this->sesion->iniciar();

        if ($this->sesion->validarCsrf($peticion->request->getString('_token'))) {
            return null;
        }

        return $this->pagina('Sesión expirada', $this->vistas->render('admin/mensaje', [
            'tipo' => 'error',
            'mensaje' => 'Tu sesión expiró antes de enviar el formulario. Vuelve atrás e intenta de nuevo.',
            'volverA' => '/admin/documentos',
        ]), Response::HTTP_FORBIDDEN);
    }

    private function noEncontrado(): Response
    {
        return $this->pagina('No encontrado', $this->vistas->render('admin/mensaje', [
            'tipo' => 'error',
            'mensaje' => 'Ese documento no existe o está en la papelera.',
            'volverA' => '/admin/documentos',
        ]), Response::HTTP_NOT_FOUND);
    }

    private function mensajeDeEstado(Request $peticion): ?string
    {
        $q = $peticion->query;

        return match (true) {
            $q->has('guardado') => 'Cambios guardados.',
            $q->has('version') => sprintf('La versión %d quedó como archivo vigente.', $q->getInt('version')),
            $q->has('eliminado') => 'El documento se envió a la papelera.',
            default => null,
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Auth\Actor;
use App\Core\Auth\AutorizacionService;
use App\Core\Auth\Sesion;
use App\Core\Storage\AlmacenArchivos;
use App\Core\Storage\ArchivoInvalidoException;
use App\Core\Support\Vistas;
use App\Modules\Contenido\Entity\EstadoAprobacion;
use App\Modules\Contenido\Entity\Pagina;
use App\Modules\Contenido\Exception\TransicionEstadoInvalidaException;
use App\Modules\Contenido\Repository\PaginaRepository;
use App\Modules\Contenido\Request\PaginaDatos;
use App\Modules\Contenido\Service\PaginaService;
use App\Modules\Contenido\Service\VersionadoService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Páginas institucionales en el panel. Mismo flujo que las noticias; además
 * la dirección (ruta) es editable y única, y las páginas protegidas por
 * normativa no se pueden eliminar.
 */
final readonly class PaginasAdminController
{
    public function __construct(
        private Sesion $sesion,
        private Vistas $vistas,
        private PaginaService $servicio,
        private PaginaRepository $paginas,
        private VersionadoService $versionado,
        private AlmacenArchivos $almacen,
        private AutorizacionService $autorizacion,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        return $this->pagina('Páginas', $this->vistas->render('admin/paginas/listado', [
            'paginas' => $this->paginas->listarParaPanel(),
            'mensaje' => $this->mensajeDeEstado($peticion),
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'puedeEditar' => $this->sesion->usuarioId() !== null
                && $this->autorizacion->puede($this->sesion->usuarioId(), 'contenido.editar'),
        ]));
    }

    public function formularioNueva(Request $peticion): Response
    {
        return $this->pagina('Nueva página', $this->renderFormulario(null, null, []), conEditor: true);
    }

    public function guardar(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $datos = PaginaDatos::desdePeticion($peticion);
        $errores = $this->validar($datos, null);
        $imagenId = $this->procesarImagen($peticion, $errores);

        if ($errores !== []) {
            return $this->pagina('Nueva página', $this->renderFormulario(null, $datos, $errores), Response::HTTP_UNPROCESSABLE_ENTITY, conEditor: true);
        }

        $id = $this->servicio->crear($datos, $imagenId, Actor::desde($this->sesion, $peticion));

        return new RedirectResponse('/admin/paginas/' . $id . '?guardada=1', Response::HTTP_SEE_OTHER);
    }

    public function formularioEditar(Request $peticion): Response
    {
        $paginaEntidad = $this->paginas->porId($peticion->attributes->getInt('id'));

        if ($paginaEntidad === null) {
            return $this->noEncontrada();
        }

        return $this->pagina('Editar página', $this->renderFormulario($paginaEntidad, null, [], $this->mensajeDeEstado($peticion)), conEditor: true);
    }

    public function actualizar(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $paginaEntidad = $this->paginas->porId($peticion->attributes->getInt('id'));

        if ($paginaEntidad === null) {
            return $this->noEncontrada();
        }

        $datos = PaginaDatos::desdePeticion($peticion);
        $errores = $this->validar($datos, $paginaEntidad->id);
        $imagenId = $this->procesarImagen($peticion, $errores) ?? $paginaEntidad->imagenId;

        if ($errores !== []) {
            return $this->pagina('Editar página', $this->renderFormulario($paginaEntidad, $datos, $errores), Response::HTTP_UNPROCESSABLE_ENTITY, conEditor: true);
        }

        $this->servicio->actualizar($paginaEntidad->id, $datos, $imagenId, Actor::desde($this->sesion, $peticion));

        return new RedirectResponse('/admin/paginas/' . $paginaEntidad->id . '?guardada=1', Response::HTTP_SEE_OTHER);
    }

    public function cambiarEstado(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $destino = EstadoAprobacion::tryFrom($peticion->request->getString('estado'));

        if ($destino === null) {
            return $this->noEncontrada();
        }

        $usuarioId = $this->sesion->usuarioId();
        $exigePublicar = in_array($destino, [EstadoAprobacion::Aprobado, EstadoAprobacion::Publicado], true);

        if ($exigePublicar && ($usuarioId === null || !$this->autorizacion->puede($usuarioId, 'contenido.publicar'))) {
            return $this->pagina('Sin permiso', $this->vistas->render('admin/mensaje', [
                'tipo' => 'error',
                'mensaje' => 'Tu cuenta puede redactar, pero aprobar o publicar requiere el permiso de publicación. Pídeselo al administrador.',
                'volverA' => '/admin/paginas/' . $id,
            ]), Response::HTTP_FORBIDDEN);
        }

        try {
            $this->servicio->cambiarEstado($id, $destino, Actor::desde($this->sesion, $peticion));
        } catch (TransicionEstadoInvalidaException) {
            return new RedirectResponse('/admin/paginas/' . $id . '?transicion=invalida', Response::HTTP_SEE_OTHER);
        }

        return new RedirectResponse('/admin/paginas/' . $id . '?estado=cambiado', Response::HTTP_SEE_OTHER);
    }

    public function restaurar(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $this->servicio->restaurar($id, $peticion->request->getInt('version'), Actor::desde($this->sesion, $peticion));

        return new RedirectResponse('/admin/paginas/' . $id . '?restaurada=1', Response::HTTP_SEE_OTHER);
    }

    public function eliminar(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');

        if (!$this->servicio->eliminar($id, Actor::desde($this->sesion, $peticion))) {
            return new RedirectResponse('/admin/paginas/' . $id . '?protegida=1', Response::HTTP_SEE_OTHER);
        }

        return new RedirectResponse('/admin/paginas?eliminada=1', Response::HTTP_SEE_OTHER);
    }

    // --- soporte ---

    /**
     * @return array<string, string>
     */
    private function validar(PaginaDatos $datos, ?int $exceptoId): array
    {
        $errores = $datos->validar();

        if (!isset($errores['ruta']) && $this->paginas->rutaOcupada($datos->ruta, $exceptoId)) {
            $errores['ruta'] = 'Ya existe otra página con esa dirección.';
        }

        return $errores;
    }

    /**
     * @param array<string, string> $errores
     */
    private function procesarImagen(Request $peticion, array &$errores): ?int
    {
        $imagen = $peticion->files->get('imagen');

        if (!$imagen instanceof UploadedFile) {
            return null;
        }

        $textoAlt = trim($peticion->request->getString('imagen_alt'));

        if ($textoAlt === '') {
            $errores['imagen_alt'] = 'Describe la imagen en una frase: es lo que escucha una persona ciega y lo que ve quien navega sin imágenes.';

            return null;
        }

        try {
            return $this->almacen->guardar($imagen, publico: true, creadoPor: $this->sesion->usuarioId(), textoAlt: $textoAlt)->id;
        } catch (ArchivoInvalidoException $e) {
            $errores['imagen'] = $e->getMessage();

            return null;
        }
    }

    /**
     * @param array<string, string> $errores
     */
    private function renderFormulario(?Pagina $paginaEntidad, ?PaginaDatos $enviado, array $errores, ?string $mensaje = null): string
    {
        return $this->vistas->render('admin/paginas/formulario', [
            'paginaEntidad' => $paginaEntidad,
            'enviado' => $enviado,
            'errores' => $errores,
            'mensaje' => $mensaje,
            'historial' => $paginaEntidad !== null ? $this->versionado->historial('paginas', $paginaEntidad->id) : [],
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'puedePublicar' => $this->sesion->usuarioId() !== null
                && $this->autorizacion->puede($this->sesion->usuarioId(), 'contenido.publicar'),
        ]);
    }

    private function pagina(string $titulo, string $contenido, int $estado = Response::HTTP_OK, bool $conEditor = false): Response
    {
        $this->sesion->iniciar();

        $html = $this->vistas->render('admin/base', [
            'titulo' => $titulo,
            'usuarioNombre' => $this->sesion->usuarioNombre() ?? 'Usuario',
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'contenido' => $contenido,
            'conEditor' => $conEditor,
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
            'volverA' => '/admin/paginas',
        ]), Response::HTTP_FORBIDDEN);
    }

    private function noEncontrada(): Response
    {
        return $this->pagina('No encontrada', $this->vistas->render('admin/mensaje', [
            'tipo' => 'error',
            'mensaje' => 'Esa página no existe o está en la papelera.',
            'volverA' => '/admin/paginas',
        ]), Response::HTTP_NOT_FOUND);
    }

    private function mensajeDeEstado(Request $peticion): ?string
    {
        $q = $peticion->query;

        return match (true) {
            $q->has('guardada') => 'Cambios guardados.',
            $q->has('eliminada') => 'La página se envió a la papelera.',
            $q->has('restaurada') => 'La versión se restauró.',
            $q->has('estado') => 'El estado cambió.',
            $q->has('protegida') => 'Esa página la exige la normativa: se puede editar, no eliminar.',
            $q->has('transicion') => 'Ese cambio de estado no está permitido: el flujo es borrador → en revisión → aprobado → publicado.',
            default => null,
        };
    }
}

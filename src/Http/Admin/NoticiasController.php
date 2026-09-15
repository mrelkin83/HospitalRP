<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Auth\Actor;
use App\Core\Auth\AutorizacionService;
use App\Core\Auth\Sesion;
use App\Core\Storage\AlmacenArchivos;
use App\Core\Storage\ArchivoInvalidoException;
use App\Core\Support\Vistas;
use App\Modules\Sistema\Service\AsistenteIa;
use App\Modules\Contenido\Entity\EstadoAprobacion;
use App\Modules\Contenido\Entity\Noticia;
use App\Modules\Contenido\Exception\TransicionEstadoInvalidaException;
use App\Modules\Contenido\Repository\CategoriaRepository;
use App\Modules\Contenido\Repository\NoticiaRepository;
use App\Modules\Contenido\Request\NoticiaDatos;
use App\Modules\Contenido\Service\NoticiaService;
use App\Modules\Contenido\Service\VersionadoService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Noticias en el panel. El camino feliz de publicar es: título, resumen,
 * contenido, imagen, guardar, aprobar, publicar — sin jerga ni pasos ocultos
 * (CLAUDE.md §6, Panel).
 *
 * El acceso con sesión lo garantiza el Kernel; aprobar y publicar exigen
 * además el permiso `contenido.publicar`, verificado aquí.
 */
final readonly class NoticiasController
{
    public function __construct(
        private Sesion $sesion,
        private Vistas $vistas,
        private AsistenteIa $asistenteIa,
        private NoticiaService $servicio,
        private NoticiaRepository $noticias,
        private CategoriaRepository $categorias,
        private VersionadoService $versionado,
        private AlmacenArchivos $almacen,
        private AutorizacionService $autorizacion,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        return $this->pagina('Noticias', $this->vistas->render('admin/noticias/listado', [
            'noticias' => $this->noticias->listarParaPanel(),
            'mensaje' => $this->mensajeDeEstado($peticion),
            // Editar y papelera desde la propia fila: la papelera vivía dentro
            // del formulario y había que abrir la pieza para retirarla.
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'puedeEditar' => $this->sesion->usuarioId() !== null
                && $this->autorizacion->puede($this->sesion->usuarioId(), 'contenido.editar'),
        ]));
    }

    public function formularioNueva(Request $peticion): Response
    {
        return $this->pagina('Nueva noticia', $this->renderFormulario(null, null, []), conEditor: true);
    }

    public function guardar(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $datos = NoticiaDatos::desdePeticion($peticion);
        $errores = $datos->validar();
        $imagenId = $this->procesarImagen($peticion, $errores);

        if ($errores !== []) {
            return $this->pagina('Nueva noticia', $this->renderFormulario(null, $datos, $errores), Response::HTTP_UNPROCESSABLE_ENTITY, conEditor: true);
        }

        $id = $this->servicio->crear($datos, $imagenId, Actor::desde($this->sesion, $peticion));

        return new RedirectResponse('/admin/noticias/' . $id . '?guardada=1', Response::HTTP_SEE_OTHER);
    }

    public function formularioEditar(Request $peticion): Response
    {
        $noticia = $this->noticias->porId($peticion->attributes->getInt('id'));

        if ($noticia === null) {
            return $this->noEncontrada();
        }

        return $this->pagina('Editar noticia', $this->renderFormulario($noticia, null, [], $this->mensajeDeEstado($peticion)), conEditor: true);
    }

    public function actualizar(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $noticia = $this->noticias->porId($peticion->attributes->getInt('id'));

        if ($noticia === null) {
            return $this->noEncontrada();
        }

        $datos = NoticiaDatos::desdePeticion($peticion);
        $errores = $datos->validar();
        $imagenId = $this->procesarImagen($peticion, $errores) ?? $noticia->imagenId;

        if ($errores !== []) {
            return $this->pagina('Editar noticia', $this->renderFormulario($noticia, $datos, $errores), Response::HTTP_UNPROCESSABLE_ENTITY, conEditor: true);
        }

        $this->servicio->actualizar($noticia->id, $datos, $imagenId, Actor::desde($this->sesion, $peticion));

        return new RedirectResponse('/admin/noticias/' . $noticia->id . '?guardada=1', Response::HTTP_SEE_OTHER);
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

        // Aprobar y publicar son decisiones editoriales: permiso aparte del
        // de redactar (CLAUDE.md §6, flujo de aprobación).
        $usuarioId = $this->sesion->usuarioId();
        $exigePublicar = in_array($destino, [EstadoAprobacion::Aprobado, EstadoAprobacion::Publicado], true);

        if ($exigePublicar && ($usuarioId === null || !$this->autorizacion->puede($usuarioId, 'contenido.publicar'))) {
            return $this->pagina('Sin permiso', $this->vistas->render('admin/mensaje', [
                'tipo' => 'error',
                'mensaje' => 'Tu cuenta puede redactar, pero aprobar o publicar requiere el permiso de publicación. Pídeselo al administrador.',
                'volverA' => '/admin/noticias/' . $id,
            ]), Response::HTTP_FORBIDDEN);
        }

        try {
            $this->servicio->cambiarEstado($id, $destino, Actor::desde($this->sesion, $peticion));
        } catch (TransicionEstadoInvalidaException) {
            return new RedirectResponse('/admin/noticias/' . $id . '?transicion=invalida', Response::HTTP_SEE_OTHER);
        }

        return new RedirectResponse('/admin/noticias/' . $id . '?estado=cambiado', Response::HTTP_SEE_OTHER);
    }

    public function restaurar(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $version = $peticion->request->getInt('version');

        $this->servicio->restaurar($id, $version, Actor::desde($this->sesion, $peticion));

        return new RedirectResponse('/admin/noticias/' . $id . '?restaurada=' . $version, Response::HTTP_SEE_OTHER);
    }

    public function eliminar(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $this->servicio->eliminar($peticion->attributes->getInt('id'), Actor::desde($this->sesion, $peticion));

        return new RedirectResponse('/admin/noticias?eliminada=1', Response::HTTP_SEE_OTHER);
    }

    // --- soporte ---

    /**
     * @param array<string, string> $errores Se le añaden los errores de imagen
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
    private function renderFormulario(?Noticia $noticia, ?NoticiaDatos $enviado, array $errores, ?string $mensaje = null): string
    {
        return $this->vistas->render('admin/noticias/formulario', [
            'noticia' => $noticia,
            'enviado' => $enviado,
            'errores' => $errores,
            'mensaje' => $mensaje,
            'categorias' => $this->categorias->activasPorTipo('noticia'),
            'historial' => $noticia !== null ? $this->versionado->historial('noticias', $noticia->id) : [],
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'puedePublicar' => $this->sesion->usuarioId() !== null
                && $this->autorizacion->puede($this->sesion->usuarioId(), 'contenido.publicar'),
            'iaActiva' => $this->asistenteIa->activa(),
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
            'volverA' => '/admin/noticias',
        ]), Response::HTTP_FORBIDDEN);
    }

    private function noEncontrada(): Response
    {
        return $this->pagina('No encontrada', $this->vistas->render('admin/mensaje', [
            'tipo' => 'error',
            'mensaje' => 'Esa noticia no existe o está en la papelera.',
            'volverA' => '/admin/noticias',
        ]), Response::HTTP_NOT_FOUND);
    }

    private function mensajeDeEstado(Request $peticion): ?string
    {
        $q = $peticion->query;

        return match (true) {
            $q->has('guardada') => 'Cambios guardados.',
            $q->has('creada') => 'Noticia creada.',
            $q->has('eliminada') => 'La noticia se envió a la papelera.',
            $q->has('restaurada') => sprintf('Se restauró la versión %d.', $q->getInt('restaurada')),
            $q->has('estado') => 'El estado cambió.',
            $q->has('transicion') => 'Ese cambio de estado no está permitido: el flujo es borrador → en revisión → aprobado → publicado.',
            default => null,
        };
    }
}

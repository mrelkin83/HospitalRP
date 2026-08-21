<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Auth\Actor;
use App\Core\Auth\Sesion;
use App\Core\Support\Vistas;
use App\Modules\Contenido\Repository\CategoriaRepository;
use App\Modules\Contenido\Repository\FaqRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Preguntas frecuentes: crear, editar, ordenar, publicar u ocultar.
 */
final readonly class FaqController
{
    public function __construct(
        private Sesion $sesion,
        private Vistas $vistas,
        private FaqRepository $faq,
        private CategoriaRepository $categorias,
        private AuditoriaService $auditoria,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        return $this->pagina($this->vistas->render('admin/faq/listado', [
            'preguntas' => $this->faq->listarParaPanel(),
            'categorias' => $this->categorias->activasPorTipo('faq'),
            'mensaje' => $peticion->query->has('guardado') ? 'Cambios guardados.' : null,
            'error' => $peticion->query->has('invalido') ? 'La pregunta y la respuesta son obligatorias.' : null,
            'tokenCsrf' => $this->sesion->tokenCsrf(),
        ]));
    }

    public function guardar(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $r = $peticion->request;
        $pregunta = trim($r->getString('pregunta'));
        $respuesta = trim($r->getString('respuesta'));

        if ($pregunta === '' || $respuesta === '') {
            return new RedirectResponse('/admin/faq?invalido=1', Response::HTTP_SEE_OTHER);
        }

        $campos = [
            'pregunta' => mb_substr($pregunta, 0, 500),
            'respuesta' => $respuesta,
            'categoria_id' => $r->getString('categoria_id') !== '' ? $r->getInt('categoria_id') : null,
            'orden' => $r->getInt('orden'),
            'publicada' => $r->getBoolean('publicada') ? 1 : 0,
        ];

        $id = $peticion->attributes->getInt('id');

        if ($id > 0) {
            if ($this->faq->porId($id) === null) {
                return new RedirectResponse('/admin/faq', Response::HTTP_SEE_OTHER);
            }

            $this->faq->actualizar($id, $campos);
            $this->auditar($peticion, AccionAuditoria::Actualizacion, $id, sprintf('Editó la pregunta frecuente «%s»', $pregunta));
        } else {
            $campos['creado_por'] = $this->sesion->usuarioId();
            $id = $this->faq->crear($campos);
            $this->auditar($peticion, AccionAuditoria::Creacion, $id, sprintf('Creó la pregunta frecuente «%s»', $pregunta));
        }

        return new RedirectResponse('/admin/faq?guardado=1', Response::HTTP_SEE_OTHER);
    }

    public function eliminar(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $fila = $this->faq->porId($id);

        if ($fila !== null) {
            $this->faq->eliminar($id);
            $this->auditar($peticion, AccionAuditoria::Eliminacion, $id, sprintf(
                'Eliminó la pregunta frecuente «%s»',
                (string) $fila['pregunta'],
            ));
        }

        return new RedirectResponse('/admin/faq?guardado=1', Response::HTTP_SEE_OTHER);
    }

    // --- soporte ---

    private function auditar(Request $peticion, AccionAuditoria $accion, int $id, string $descripcion): void
    {
        $actor = Actor::desde($this->sesion, $peticion);

        $this->auditoria->registrar(new RegistroAuditoria(
            accion: $accion,
            entidad: 'faq',
            entidadId: $id,
            descripcion: $descripcion,
            usuarioId: $actor->usuarioId,
            usuarioNombre: $actor->nombre,
            ip: $actor->ip,
            userAgent: $actor->userAgent,
        ));
    }

    private function exigirCsrf(Request $peticion): ?Response
    {
        $this->sesion->iniciar();

        if ($this->sesion->validarCsrf($peticion->request->getString('_token'))) {
            return null;
        }

        return $this->pagina($this->vistas->render('admin/mensaje', [
            'tipo' => 'error',
            'mensaje' => 'Tu sesión expiró antes de enviar el formulario. Vuelve atrás e intenta de nuevo.',
            'volverA' => '/admin/faq',
        ]), Response::HTTP_FORBIDDEN);
    }

    private function pagina(string $contenido, int $estado = Response::HTTP_OK): Response
    {
        $this->sesion->iniciar();

        $html = $this->vistas->render('admin/base', [
            'titulo' => 'Preguntas frecuentes',
            'usuarioNombre' => $this->sesion->usuarioNombre() ?? 'Usuario',
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'contenido' => $contenido,
        ]);

        $respuesta = new Response($html, $estado, ['Content-Type' => 'text/html; charset=utf-8']);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }
}

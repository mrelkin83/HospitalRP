<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Auth\Actor;
use App\Core\Auth\Sesion;
use App\Core\Support\Vistas;
use App\Modules\Sistema\Repository\DependenciaRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dependencias (áreas administrativas): responsables de documentos y, más
 * adelante, de trámites.
 */
final readonly class DependenciasController
{
    public function __construct(
        private Sesion $sesion,
        private Vistas $vistas,
        private DependenciaRepository $dependencias,
        private AuditoriaService $auditoria,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        return $this->pagina($this->vistas->render('admin/dependencias/listado', [
            'dependencias' => $this->dependencias->listarParaPanel(),
            'mensaje' => $peticion->query->has('guardado') ? 'Cambios guardados.' : null,
            'error' => $peticion->query->has('invalido') ? 'La dependencia necesita nombre.' : null,
            'tokenCsrf' => $this->sesion->tokenCsrf(),
        ]));
    }

    public function guardar(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $r = $peticion->request;
        $nombre = trim($r->getString('nombre'));

        if ($nombre === '') {
            return new RedirectResponse('/admin/dependencias?invalido=1', Response::HTTP_SEE_OTHER);
        }

        $campos = [
            'nombre' => mb_substr($nombre, 0, 160),
            'sigla' => $r->getString('sigla') !== '' ? trim($r->getString('sigla')) : null,
            'responsable' => $r->getString('responsable') !== '' ? trim($r->getString('responsable')) : null,
            'correo' => $r->getString('correo') !== '' ? trim($r->getString('correo')) : null,
            'telefono' => $r->getString('telefono') !== '' ? trim($r->getString('telefono')) : null,
            'orden' => $r->getInt('orden'),
            'activa' => $r->getBoolean('activa') ? 1 : 0,
        ];

        $id = $peticion->attributes->getInt('id');

        if ($id > 0) {
            if ($this->dependencias->porId($id) === null) {
                return new RedirectResponse('/admin/dependencias', Response::HTTP_SEE_OTHER);
            }

            $this->dependencias->actualizar($id, $campos);
            $accion = AccionAuditoria::Actualizacion;
        } else {
            $id = $this->dependencias->crear($campos);
            $accion = AccionAuditoria::Creacion;
        }

        $actor = Actor::desde($this->sesion, $peticion);
        $this->auditoria->registrar(new RegistroAuditoria(
            accion: $accion,
            entidad: 'dependencias',
            entidadId: $id,
            descripcion: sprintf(
                '%s la dependencia «%s»',
                $accion === AccionAuditoria::Creacion ? 'Creó' : 'Editó',
                $nombre,
            ),
            usuarioId: $actor->usuarioId,
            usuarioNombre: $actor->nombre,
            ip: $actor->ip,
            userAgent: $actor->userAgent,
        ));

        return new RedirectResponse('/admin/dependencias?guardado=1', Response::HTTP_SEE_OTHER);
    }

    // --- soporte ---

    private function exigirCsrf(Request $peticion): ?Response
    {
        $this->sesion->iniciar();

        if ($this->sesion->validarCsrf($peticion->request->getString('_token'))) {
            return null;
        }

        return $this->pagina($this->vistas->render('admin/mensaje', [
            'tipo' => 'error',
            'mensaje' => 'Tu sesión expiró antes de enviar el formulario. Vuelve atrás e intenta de nuevo.',
            'volverA' => '/admin/dependencias',
        ]), Response::HTTP_FORBIDDEN);
    }

    private function pagina(string $contenido, int $estado = Response::HTTP_OK): Response
    {
        $this->sesion->iniciar();

        $html = $this->vistas->render('admin/base', [
            'titulo' => 'Dependencias',
            'usuarioNombre' => $this->sesion->usuarioNombre() ?? 'Usuario',
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'contenido' => $contenido,
        ]);

        $respuesta = new Response($html, $estado, ['Content-Type' => 'text/html; charset=utf-8']);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }
}

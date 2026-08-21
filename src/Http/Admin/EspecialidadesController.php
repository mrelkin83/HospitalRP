<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Auth\Actor;
use App\Core\Auth\Sesion;
use App\Core\Support\Vistas;
use App\Modules\Directorio\Repository\EspecialidadRepository;
use App\Modules\Directorio\Repository\SedeRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Especialidades médicas y las sedes donde se prestan (el filtro público
 * sale de esa relación).
 */
final readonly class EspecialidadesController
{
    public function __construct(
        private Sesion $sesion,
        private Vistas $vistas,
        private EspecialidadRepository $especialidades,
        private SedeRepository $sedes,
        private AuditoriaService $auditoria,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        return $this->pagina($this->vistas->render('admin/especialidades/listado', [
            'especialidades' => $this->especialidades->listarParaPanel(),
            'sedes' => $this->sedes->listarParaPanel(),
            'mensaje' => $peticion->query->has('guardado') ? 'Cambios guardados.' : null,
            'error' => $peticion->query->has('invalido') ? 'La especialidad necesita nombre.' : null,
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
            return new RedirectResponse('/admin/especialidades?invalido=1', Response::HTTP_SEE_OTHER);
        }

        $campos = [
            'nombre' => mb_substr($nombre, 0, 160),
            'descripcion' => $r->getString('descripcion') !== '' ? trim($r->getString('descripcion')) : null,
            'orden' => $r->getInt('orden'),
            'activa' => $r->getBoolean('activa') ? 1 : 0,
        ];

        /** @var list<int> $sedeIds */
        $sedeIds = array_map(intval(...), (array) $r->all('sedes'));

        $id = $peticion->attributes->getInt('id');

        if ($id > 0) {
            if ($this->especialidades->porId($id) === null) {
                return new RedirectResponse('/admin/especialidades', Response::HTTP_SEE_OTHER);
            }

            $this->especialidades->actualizar($id, $campos);
            $accion = AccionAuditoria::Actualizacion;
        } else {
            $id = $this->especialidades->crear($campos);
            $accion = AccionAuditoria::Creacion;
        }

        $this->especialidades->sincronizarSedes($id, $sedeIds);

        $actor = Actor::desde($this->sesion, $peticion);
        $this->auditoria->registrar(new RegistroAuditoria(
            accion: $accion,
            entidad: 'especialidades',
            entidadId: $id,
            descripcion: sprintf(
                '%s la especialidad «%s» (%d sedes)',
                $accion === AccionAuditoria::Creacion ? 'Creó' : 'Editó',
                $nombre,
                count($sedeIds),
            ),
            usuarioId: $actor->usuarioId,
            usuarioNombre: $actor->nombre,
            ip: $actor->ip,
            userAgent: $actor->userAgent,
        ));

        return new RedirectResponse('/admin/especialidades?guardado=1', Response::HTTP_SEE_OTHER);
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
            'volverA' => '/admin/especialidades',
        ]), Response::HTTP_FORBIDDEN);
    }

    private function pagina(string $contenido, int $estado = Response::HTTP_OK): Response
    {
        $this->sesion->iniciar();

        $html = $this->vistas->render('admin/base', [
            'titulo' => 'Especialidades',
            'usuarioNombre' => $this->sesion->usuarioNombre() ?? 'Usuario',
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'contenido' => $contenido,
        ]);

        $respuesta = new Response($html, $estado, ['Content-Type' => 'text/html; charset=utf-8']);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }
}

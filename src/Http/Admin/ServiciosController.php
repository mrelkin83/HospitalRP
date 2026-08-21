<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Auth\Actor;
use App\Core\Auth\Sesion;
use App\Core\Support\Vistas;
use App\Modules\Directorio\Repository\SedeRepository;
use App\Modules\Directorio\Repository\ServicioRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Servicios asistenciales (laboratorio, imágenes, farmacia…) y sus sedes.
 */
final readonly class ServiciosController
{
    public function __construct(
        private Sesion $sesion,
        private Vistas $vistas,
        private ServicioRepository $servicios,
        private SedeRepository $sedes,
        private AuditoriaService $auditoria,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        return $this->pagina($this->vistas->render('admin/servicios/listado', [
            'servicios' => $this->servicios->listarParaPanel(),
            'sedes' => $this->sedes->listarParaPanel(),
            'mensaje' => $peticion->query->has('guardado') ? 'Cambios guardados.' : null,
            'error' => $peticion->query->has('invalido') ? 'El servicio necesita nombre.' : null,
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
            return new RedirectResponse('/admin/servicios?invalido=1', Response::HTTP_SEE_OTHER);
        }

        $campos = [
            'nombre' => mb_substr($nombre, 0, 160),
            'descripcion' => $r->getString('descripcion') !== '' ? trim($r->getString('descripcion')) : null,
            'requisitos' => $r->getString('requisitos') !== '' ? trim($r->getString('requisitos')) : null,
            'horario' => $r->getString('horario') !== '' ? mb_substr(trim($r->getString('horario')), 0, 500) : null,
            'telefono' => $r->getString('telefono') !== '' ? trim($r->getString('telefono')) : null,
            'orden' => $r->getInt('orden'),
            'activo' => $r->getBoolean('activo') ? 1 : 0,
        ];

        /** @var list<int> $sedeIds */
        $sedeIds = array_map(intval(...), (array) $r->all('sedes'));

        $id = $peticion->attributes->getInt('id');

        if ($id > 0) {
            if ($this->servicios->porId($id) === null) {
                return new RedirectResponse('/admin/servicios', Response::HTTP_SEE_OTHER);
            }

            $this->servicios->actualizar($id, $campos);
            $accion = AccionAuditoria::Actualizacion;
        } else {
            $id = $this->servicios->crear($campos);
            $accion = AccionAuditoria::Creacion;
        }

        $this->servicios->sincronizarSedes($id, $sedeIds);

        $actor = Actor::desde($this->sesion, $peticion);
        $this->auditoria->registrar(new RegistroAuditoria(
            accion: $accion,
            entidad: 'servicios',
            entidadId: $id,
            descripcion: sprintf(
                '%s el servicio «%s» (%d sedes)',
                $accion === AccionAuditoria::Creacion ? 'Creó' : 'Editó',
                $nombre,
                count($sedeIds),
            ),
            usuarioId: $actor->usuarioId,
            usuarioNombre: $actor->nombre,
            ip: $actor->ip,
            userAgent: $actor->userAgent,
        ));

        return new RedirectResponse('/admin/servicios?guardado=1', Response::HTTP_SEE_OTHER);
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
            'volverA' => '/admin/servicios',
        ]), Response::HTTP_FORBIDDEN);
    }

    private function pagina(string $contenido, int $estado = Response::HTTP_OK): Response
    {
        $this->sesion->iniciar();

        $html = $this->vistas->render('admin/base', [
            'titulo' => 'Servicios',
            'usuarioNombre' => $this->sesion->usuarioNombre() ?? 'Usuario',
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'contenido' => $contenido,
        ]);

        $respuesta = new Response($html, $estado, ['Content-Type' => 'text/html; charset=utf-8']);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }
}

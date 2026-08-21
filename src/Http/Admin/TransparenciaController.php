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
use App\Modules\Transparencia\Repository\TransparenciaRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panel de transparencia: el administrador ve de un vistazo qué secciones
 * obligatorias siguen vacías y completa cada una con documentos o enlaces.
 */
final readonly class TransparenciaController
{
    public function __construct(
        private Sesion $sesion,
        private Vistas $vistas,
        private TransparenciaRepository $transparencia,
        private DependenciaRepository $dependencias,
        private AuditoriaService $auditoria,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        return $this->pagina($this->vistas->render('admin/transparencia/listado', [
            'arbol' => $this->transparencia->arbol(),
            'vacias' => $this->transparencia->obligatoriasVacias(),
            'dependencias' => $this->dependencias->activas(),
            'mensaje' => $peticion->query->has('guardado') ? 'Cambios guardados.' : null,
            'error' => $peticion->query->has('invalido')
                ? 'El ítem necesita título y un destino: un documento (por su número) o un enlace externo.'
                : null,
            'tokenCsrf' => $this->sesion->tokenCsrf(),
        ]));
    }

    public function actualizarSeccion(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $seccion = $this->transparencia->seccionPorId($id);

        if ($seccion === null) {
            return new RedirectResponse('/admin/transparencia', Response::HTTP_SEE_OTHER);
        }

        $r = $peticion->request;
        $periodicidades = ['ninguna', 'mensual', 'trimestral', 'semestral', 'anual', 'permanente'];
        $periodicidad = $r->getString('periodicidad');

        $this->transparencia->actualizarSeccion($id, [
            'aplica' => $r->getBoolean('aplica') ? 1 : 0,
            'dependencia_id' => $r->getString('dependencia_id') !== '' ? $r->getInt('dependencia_id') : null,
            'periodicidad' => in_array($periodicidad, $periodicidades, true) ? $periodicidad : 'ninguna',
        ]);

        $this->auditar($peticion, AccionAuditoria::Actualizacion, $id, sprintf(
            'Editó la sección de transparencia %s «%s»%s',
            (string) $seccion['codigo'],
            (string) $seccion['nombre'],
            $r->getBoolean('aplica') ? '' : ' (marcada NO APLICA)',
        ));

        return new RedirectResponse('/admin/transparencia?guardado=1', Response::HTTP_SEE_OTHER);
    }

    public function crearItem(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $seccionId = $peticion->attributes->getInt('id');
        $r = $peticion->request;
        $titulo = trim($r->getString('titulo'));
        $documentoId = $r->getString('documento_id') !== '' ? $r->getInt('documento_id') : null;
        $url = trim($r->getString('url_externa'));

        if ($titulo === '' || ($documentoId === null && $url === '') || $this->transparencia->seccionPorId($seccionId) === null) {
            return new RedirectResponse('/admin/transparencia?invalido=1', Response::HTTP_SEE_OTHER);
        }

        $id = $this->transparencia->crearItem([
            'seccion_id' => $seccionId,
            'documento_id' => $documentoId,
            'url_externa' => $url !== '' ? $url : null,
            'titulo' => mb_substr($titulo, 0, 300),
            'descripcion' => $r->getString('descripcion') !== '' ? trim($r->getString('descripcion')) : null,
            'orden' => $r->getInt('orden'),
            'publicado' => $r->getBoolean('publicado', true) ? 1 : 0,
        ]);

        $this->transparencia->marcarActualizada($seccionId);
        $this->auditar($peticion, AccionAuditoria::Creacion, $id, sprintf('Añadió a transparencia el ítem «%s»', $titulo));

        return new RedirectResponse('/admin/transparencia?guardado=1', Response::HTTP_SEE_OTHER);
    }

    public function eliminarItem(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $item = $this->transparencia->itemPorId($id);

        if ($item !== null) {
            $this->transparencia->eliminarItem($id);
            $this->auditar($peticion, AccionAuditoria::Eliminacion, $id, sprintf(
                'Eliminó de transparencia el ítem «%s»',
                (string) $item['titulo'],
            ));
        }

        return new RedirectResponse('/admin/transparencia?guardado=1', Response::HTTP_SEE_OTHER);
    }

    // --- soporte ---

    private function auditar(Request $peticion, AccionAuditoria $accion, int $id, string $descripcion): void
    {
        $actor = Actor::desde($this->sesion, $peticion);

        $this->auditoria->registrar(new RegistroAuditoria(
            accion: $accion,
            entidad: 'transparencia',
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
            'volverA' => '/admin/transparencia',
        ]), Response::HTTP_FORBIDDEN);
    }

    private function pagina(string $contenido, int $estado = Response::HTTP_OK): Response
    {
        $this->sesion->iniciar();

        $html = $this->vistas->render('admin/base', [
            'titulo' => 'Transparencia',
            'usuarioNombre' => $this->sesion->usuarioNombre() ?? 'Usuario',
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'contenido' => $contenido,
        ]);

        $respuesta = new Response($html, $estado, ['Content-Type' => 'text/html; charset=utf-8']);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }
}

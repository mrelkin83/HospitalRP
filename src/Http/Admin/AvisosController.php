<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Auth\Actor;
use App\Core\Auth\Sesion;
use App\Core\Support\Vistas;
use App\Modules\Contenido\Repository\AvisoRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Avisos del sitio: el banner de la home y el modo emergencia se manejan
 * desde aquí. Un aviso crítico activo transforma la portada, así que activar
 * y desactivar queda auditado con nombre y fecha.
 */
final readonly class AvisosController
{
    private const array TIPOS = ['informativo', 'urgencia', 'campana', 'emergencia'];
    private const array NIVELES = ['info', 'alerta', 'critico'];

    public function __construct(
        private Sesion $sesion,
        private Vistas $vistas,
        private AvisoRepository $avisos,
        private AuditoriaService $auditoria,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        return $this->pagina($this->vistas->render('admin/avisos/listado', [
            'avisos' => $this->avisos->listarParaPanel(),
            'mensaje' => $peticion->query->has('guardado') ? 'Cambios guardados.' : null,
            'error' => $peticion->query->has('invalido') ? 'El aviso necesita título y mensaje.' : null,
            'tokenCsrf' => $this->sesion->tokenCsrf(),
        ]));
    }

    public function guardar(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $campos = $this->camposDesde($peticion);

        if ($campos === null) {
            return new RedirectResponse('/admin/avisos?invalido=1', Response::HTTP_SEE_OTHER);
        }

        $id = $peticion->attributes->getInt('id');

        if ($id > 0) {
            if ($this->avisos->porId($id) === null) {
                return new RedirectResponse('/admin/avisos', Response::HTTP_SEE_OTHER);
            }

            $this->avisos->actualizar($id, $campos);
            $accion = AccionAuditoria::Actualizacion;
        } else {
            $campos['creado_por'] = $this->sesion->usuarioId();
            $id = $this->avisos->crear($campos);
            $accion = AccionAuditoria::Creacion;
        }

        $actor = Actor::desde($this->sesion, $peticion);
        $this->auditoria->registrar(new RegistroAuditoria(
            accion: $accion,
            entidad: 'avisos',
            entidadId: $id,
            descripcion: sprintf(
                '%s el aviso «%s» (nivel %s, %s)',
                $accion === AccionAuditoria::Creacion ? 'Creó' : 'Editó',
                (string) $campos['titulo'],
                (string) $campos['nivel'],
                ((int) $campos['activo']) === 1 ? 'ACTIVO' : 'inactivo',
            ),
            usuarioId: $actor->usuarioId,
            usuarioNombre: $actor->nombre,
            ip: $actor->ip,
            userAgent: $actor->userAgent,
        ));

        return new RedirectResponse('/admin/avisos?guardado=1', Response::HTTP_SEE_OTHER);
    }

    // --- soporte ---

    /**
     * @return ?array<string, mixed> null si los datos mínimos no están
     */
    private function camposDesde(Request $peticion): ?array
    {
        $r = $peticion->request;
        $titulo = trim($r->getString('titulo'));
        $mensaje = trim($r->getString('mensaje'));

        if ($titulo === '' || $mensaje === '') {
            return null;
        }

        $fecha = static function (string $valor): ?string {
            $valor = trim($valor);

            return $valor === '' ? null : str_replace('T', ' ', $valor) . (strlen($valor) === 16 ? ':00' : '');
        };

        $tipo = $r->getString('tipo');
        $nivel = $r->getString('nivel');

        return [
            'tipo' => in_array($tipo, self::TIPOS, true) ? $tipo : 'informativo',
            'nivel' => in_array($nivel, self::NIVELES, true) ? $nivel : 'info',
            'titulo' => mb_substr($titulo, 0, 200),
            'mensaje' => mb_substr($mensaje, 0, 1000),
            'url' => $r->getString('url') !== '' ? trim($r->getString('url')) : null,
            'texto_boton' => $r->getString('texto_boton') !== '' ? trim($r->getString('texto_boton')) : null,
            'desde' => $fecha($r->getString('desde')),
            'hasta' => $fecha($r->getString('hasta')),
            'activo' => $r->getBoolean('activo') ? 1 : 0,
            'prioridad' => $r->getInt('prioridad'),
        ];
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
            'volverA' => '/admin/avisos',
        ]), Response::HTTP_FORBIDDEN);
    }

    private function pagina(string $contenido, int $estado = Response::HTTP_OK): Response
    {
        $this->sesion->iniciar();

        $html = $this->vistas->render('admin/base', [
            'titulo' => 'Avisos',
            'usuarioNombre' => $this->sesion->usuarioNombre() ?? 'Usuario',
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'contenido' => $contenido,
        ]);

        $respuesta = new Response($html, $estado, ['Content-Type' => 'text/html; charset=utf-8']);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }
}

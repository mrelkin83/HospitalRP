<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Auth\Actor;
use App\Core\Auth\Sesion;
use App\Core\Storage\AlmacenArchivos;
use App\Core\Support\Vistas;
use App\Modules\Contenido\Repository\PopupRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ventanas emergentes del sitio público.
 *
 * Encender un popup es la acción más intrusiva que ofrece el panel: se pone
 * delante de todo el que entre al sitio. Por eso cada alta, edición, encendido
 * y borrado queda en auditoría con nombre y fecha, igual que los avisos.
 */
final readonly class PopupsController
{
    private const array UBICACIONES = ['todas', 'portada'];
    private const array FRECUENCIAS = ['siempre', 'sesion', 'diaria'];

    public function __construct(
        private Sesion $sesion,
        private Vistas $vistas,
        private PopupRepository $popups,
        private AlmacenArchivos $almacen,
        private AuditoriaService $auditoria,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        return $this->pagina($this->vistas->render('admin/popups/listado', [
            'popups' => $this->popups->listarParaPanel(),
            'imagenes' => $this->almacen->imagenesRecientes(),
            'mensaje' => match (true) {
                $peticion->query->has('guardado') => 'Cambios guardados.',
                $peticion->query->has('eliminado') => 'Ventana emergente eliminada.',
                default => null,
            },
            'error' => $peticion->query->has('invalido')
                ? 'La ventana emergente necesita título y mensaje.'
                : null,
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
            return new RedirectResponse('/admin/popups?invalido=1', Response::HTTP_SEE_OTHER);
        }

        $id = $peticion->attributes->getInt('id');

        if ($id > 0) {
            if ($this->popups->porId($id) === null) {
                return new RedirectResponse('/admin/popups', Response::HTTP_SEE_OTHER);
            }

            $this->popups->actualizar($id, $campos);
            $accion = AccionAuditoria::Actualizacion;
        } else {
            $campos['creado_por'] = $this->sesion->usuarioId();
            $id = $this->popups->crear($campos);
            $accion = AccionAuditoria::Creacion;
        }

        $this->auditar($peticion, $accion, $id, sprintf(
            '%s la ventana emergente «%s» (%s, %s, %s)',
            $accion === AccionAuditoria::Creacion ? 'Creó' : 'Editó',
            (string) $campos['titulo'],
            ((int) $campos['activo']) === 1 ? 'ACTIVA' : 'inactiva',
            (string) $campos['ubicacion'],
            (string) $campos['frecuencia'],
        ));

        return new RedirectResponse('/admin/popups?guardado=1', Response::HTTP_SEE_OTHER);
    }

    public function eliminar(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $popup = $this->popups->porId($id);

        if ($popup === null) {
            return new RedirectResponse('/admin/popups', Response::HTTP_SEE_OTHER);
        }

        $this->popups->eliminar($id);
        $this->auditar($peticion, AccionAuditoria::Eliminacion, $id, sprintf(
            'Eliminó la ventana emergente «%s»',
            (string) $popup['titulo'],
        ));

        return new RedirectResponse('/admin/popups?eliminado=1', Response::HTTP_SEE_OTHER);
    }

    // --- soporte ---

    /** @return ?array<string, mixed> null si los datos mínimos no están */
    private function camposDesde(Request $peticion): ?array
    {
        $r = $peticion->request;
        $titulo = trim($r->getString('titulo'));
        $mensaje = trim($r->getString('mensaje'));

        if ($titulo === '' || $mensaje === '') {
            return null;
        }

        $ubicacion = $r->getString('ubicacion');
        $frecuencia = $r->getString('frecuencia');
        $imagenId = $r->getInt('imagen_id');

        return [
            'titulo' => mb_substr($titulo, 0, 200),
            'mensaje' => mb_substr($mensaje, 0, 1000),
            'imagen_id' => $imagenId > 0 ? $imagenId : null,
            'url' => $this->textoONulo($r->getString('url'), 500),
            'texto_boton' => $this->textoONulo($r->getString('texto_boton'), 80),
            // Valor conocido o el más conservador: una lista desplegable
            // manipulada no debe poder escribir cualquier cosa en un ENUM.
            'ubicacion' => in_array($ubicacion, self::UBICACIONES, true) ? $ubicacion : 'portada',
            'frecuencia' => in_array($frecuencia, self::FRECUENCIAS, true) ? $frecuencia : 'sesion',
            'desde' => $this->fecha($r->getString('desde')),
            'hasta' => $this->fecha($r->getString('hasta')),
            'activo' => $r->getBoolean('activo') ? 1 : 0,
            'prioridad' => $r->getInt('prioridad'),
        ];
    }

    private function textoONulo(string $valor, int $maximo): ?string
    {
        $valor = trim($valor);

        return $valor === '' ? null : mb_substr($valor, 0, $maximo);
    }

    /** `<input type="datetime-local">` manda `2026-09-15T14:30`; MySQL quiere espacio y segundos. */
    private function fecha(string $valor): ?string
    {
        $valor = trim($valor);

        if ($valor === '') {
            return null;
        }

        return str_replace('T', ' ', $valor) . (strlen($valor) === 16 ? ':00' : '');
    }

    private function auditar(Request $peticion, AccionAuditoria $accion, int $id, string $descripcion): void
    {
        $actor = Actor::desde($this->sesion, $peticion);

        $this->auditoria->registrar(new RegistroAuditoria(
            accion: $accion,
            entidad: 'popups',
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
            'volverA' => '/admin/popups',
        ]), Response::HTTP_FORBIDDEN);
    }

    private function pagina(string $contenido, int $estado = Response::HTTP_OK): Response
    {
        $this->sesion->iniciar();

        $html = $this->vistas->render('admin/base', [
            'titulo' => 'Ventanas emergentes',
            'usuarioNombre' => $this->sesion->usuarioNombre() ?? 'Usuario',
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'contenido' => $contenido,
        ]);

        $respuesta = new Response($html, $estado, ['Content-Type' => 'text/html; charset=utf-8']);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }
}

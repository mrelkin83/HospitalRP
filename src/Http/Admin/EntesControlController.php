<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Core\Auth\Sesion;
use App\Core\Support\Vistas;
use App\Modules\Transparencia\Entity\TipoControl;
use App\Modules\Transparencia\Repository\EnteVigilanciaRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panel → Transparencia → Entes que nos vigilan (ITA 3.13).
 *
 * Existe por la segunda regla de oro de CLAUDE.md: la semilla deja a propósito
 * sin teléfono ni correo a los entes cuyos datos no se pudieron verificar, y
 * sin esta pantalla el hospital tendría que llamar a un desarrollador para
 * escribir un número de teléfono.
 */
final readonly class EntesControlController
{
    private const string RUTA = '/admin/transparencia/entes';

    public function __construct(
        private Sesion $sesion,
        private Vistas $vistas,
        private EnteVigilanciaRepository $entes,
        private AuditoriaService $auditoria,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        return $this->pagina($this->vistas->render('admin/entes/listado', [
            'entes' => $this->entes->listarParaPanel(),
            'tipos' => TipoControl::todos(),
            'mensaje' => match (true) {
                $peticion->query->has('guardado') => 'Cambios guardados.',
                $peticion->query->has('eliminado') => 'Ente eliminado.',
                default => null,
            },
            'error' => $peticion->query->has('invalido')
                ? 'El ente necesita un nombre y un tipo de control.'
                : null,
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
        $tipo = TipoControl::tryFrom($r->getString('tipo_control'));

        if ($nombre === '' || $tipo === null) {
            return new RedirectResponse(self::RUTA . '?invalido=1', Response::HTTP_SEE_OTHER);
        }

        $opcional = static function (string $valor, int $max): ?string {
            $valor = trim($valor);

            return $valor === '' ? null : mb_substr($valor, 0, $max);
        };

        $campos = [
            'nombre' => mb_substr($nombre, 0, 250),
            'tipo_control' => $tipo->value,
            'direccion' => $opcional($r->getString('direccion'), 300),
            'telefono' => $opcional($r->getString('telefono'), 120),
            'correo' => $opcional($r->getString('correo'), 160),
            'url' => $opcional($r->getString('url'), 500),
            'mecanismos' => $opcional($r->getString('mecanismos'), 4000),
            'orden' => $r->getInt('orden'),
            'publicado' => $r->getBoolean('publicado') ? 1 : 0,
        ];

        $id = $peticion->attributes->getInt('id');

        if ($id > 0) {
            if ($this->entes->porId($id) === null) {
                return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
            }

            $this->entes->actualizar($id, $campos);
            $accion = AccionAuditoria::Actualizacion;
        } else {
            $id = $this->entes->crear($campos);
            $accion = AccionAuditoria::Creacion;
        }

        $this->registrar($accion, $id, sprintf('Ente de vigilancia: «%s»', $campos['nombre']));

        return new RedirectResponse(self::RUTA . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    public function eliminar(Request $peticion): Response
    {
        if (($rechazo = $this->exigirCsrf($peticion)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $ente = $this->entes->porId($id);

        if ($ente === null) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        $this->entes->eliminar($id);
        $this->registrar(AccionAuditoria::Eliminacion, $id, sprintf(
            'Ente de vigilancia: «%s»',
            (string) $ente['nombre'],
        ));

        return new RedirectResponse(self::RUTA . '?eliminado=1', Response::HTTP_SEE_OTHER);
    }

    // --- soporte ---

    private function registrar(AccionAuditoria $accion, int $id, string $descripcion): void
    {
        $this->sesion->iniciar();

        $this->auditoria->registrar(new RegistroAuditoria(
            accion: $accion,
            entidad: 'entes_vigilancia',
            entidadId: $id,
            descripcion: $descripcion,
            usuarioId: $this->sesion->usuarioId(),
            usuarioNombre: $this->sesion->usuarioNombre(),
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
            'volverA' => self::RUTA,
        ]), Response::HTTP_FORBIDDEN);
    }

    private function pagina(string $contenido, int $estado = Response::HTTP_OK): Response
    {
        $this->sesion->iniciar();

        $html = $this->vistas->render('admin/base', [
            'titulo' => 'Entes que nos vigilan',
            'usuarioNombre' => $this->sesion->usuarioNombre() ?? 'Usuario',
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'contenido' => $contenido,
        ]);

        $respuesta = new Response($html, $estado, ['Content-Type' => 'text/html; charset=utf-8']);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }
}

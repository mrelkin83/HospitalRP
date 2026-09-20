<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Support\Vistas;
use App\Http\Admin\Soporte\PantallaAdmin;
use App\Modules\CuentasCobro\Repository\SupervisorRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Supervisores de contratos (pantalla A5): vincular un usuario del panel
 * con el nombre y el cargo EXACTOS que deben salir en el informe de
 * supervisión GJ-FR-004.
 */
final readonly class CuentasSupervisoresController
{
    private const string TITULO = 'Cuentas de cobro — Supervisores';
    private const string RUTA = '/admin/cuentas-cobro/supervisores';

    public function __construct(
        private PantallaAdmin $pantalla,
        private Vistas $vistas,
        private SupervisorRepository $supervisores,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        $error = $this->pantalla->sacarDeLaSesion('cc_error');
        $mensaje = $this->pantalla->sacarDeLaSesion('cc_mensaje');

        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/cuentas/supervisores', [
            'supervisores' => $this->supervisores->listar(),
            'usuariosElegibles' => $this->supervisores->usuariosElegibles(),
            'mensaje' => is_string($mensaje) ? $mensaje : null,
            'error' => is_string($error) ? $error : null,
            'tokenCsrf' => $this->pantalla->tokenCsrf(),
        ]));
    }

    public function guardar(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $r = $peticion->request;
        $id = $peticion->attributes->getInt('id');
        $existente = $id > 0 ? $this->supervisores->porId($id) : null;

        if ($id > 0 && $existente === null) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        $nombre = trim($r->getString('nombre_completo'));
        $cargo = trim($r->getString('cargo'));

        if ($nombre === '' || $cargo === '') {
            return $this->volverConError('El nombre y el cargo son obligatorios: salen tal cual en el PDF del informe.');
        }

        $campos = [
            'nombre_completo' => mb_substr($nombre, 0, 240),
            'cargo' => mb_substr($cargo, 0, 160),
            'dependencia' => $this->texto($r->getString('dependencia'), 160),
            'activo' => $r->getBoolean('activo') ? 1 : 0,
        ];

        if ($existente === null) {
            $usuarioId = $r->getInt('usuario_id');

            if ($usuarioId <= 0) {
                return $this->volverConError('Seleccione el usuario del panel que actuará como supervisor.');
            }

            if ($this->supervisores->porUsuarioId($usuarioId) !== null) {
                return $this->volverConError('Ese usuario ya está vinculado como supervisor.');
            }

            $id = $this->supervisores->crear($campos + ['usuario_id' => $usuarioId]);
            $this->pantalla->auditar($peticion, AccionAuditoria::Creacion, 'cc_supervisor', $id, "Supervisor {$nombre} vinculado");
        } else {
            // El usuario vinculado no se cambia: si la persona se va, se
            // desactiva este supervisor y se vincula otro. Así los informes
            // viejos siguen diciendo quién los firmó.
            $this->supervisores->actualizar($id, $campos);
            $this->pantalla->auditar($peticion, AccionAuditoria::Actualizacion, 'cc_supervisor', $id, "Supervisor {$nombre} actualizado");
        }

        $this->pantalla->guardarEnLaSesion('cc_mensaje', 'Supervisor guardado.');

        return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
    }

    private function texto(string $valor, int $maximo): ?string
    {
        $limpio = trim($valor);

        return $limpio === '' ? null : mb_substr($limpio, 0, $maximo);
    }

    private function volverConError(string $mensaje): RedirectResponse
    {
        $this->pantalla->guardarEnLaSesion('cc_error', $mensaje);

        return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
    }
}

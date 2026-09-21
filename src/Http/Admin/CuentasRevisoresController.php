<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Support\Vistas;
use App\Http\Admin\Soporte\PantallaAdmin;
use App\Modules\CuentasCobro\Repository\RevisorRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Revisores de cuentas de cobro (pantalla del administrador): vincular a
 * los usuarios del panel —hoy son cuatro personas— que verifican cada
 * cuenta ENVIADA y la firman antes de que pase al supervisor.
 */
final readonly class CuentasRevisoresController
{
    private const string TITULO = 'Cuentas de cobro — Revisores';
    private const string RUTA = '/admin/cuentas-cobro/revisores';

    public function __construct(
        private PantallaAdmin $pantalla,
        private Vistas $vistas,
        private RevisorRepository $revisores,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        $error = $this->pantalla->sacarDeLaSesion('cc_error');
        $mensaje = $this->pantalla->sacarDeLaSesion('cc_mensaje');

        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/cuentas/revisores', [
            'revisores' => $this->revisores->listar(),
            'usuariosElegibles' => $this->revisores->usuariosElegibles(),
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
        $existente = $id > 0 ? $this->revisores->porId($id) : null;

        if ($id > 0 && $existente === null) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        $nombre = trim($r->getString('nombre_completo'));
        $cargo = trim($r->getString('cargo'));

        if ($nombre === '' || $cargo === '') {
            return $this->volverConError('El nombre y el cargo son obligatorios: quedan en la evidencia de cada revisión firmada.');
        }

        $campos = [
            'nombre_completo' => mb_substr($nombre, 0, 240),
            'cargo' => mb_substr($cargo, 0, 160),
            'activo' => $r->getBoolean('activo') ? 1 : 0,
        ];

        if ($existente === null) {
            $usuarioId = $r->getInt('usuario_id');

            if ($usuarioId <= 0) {
                return $this->volverConError('Seleccione el usuario del panel que actuará como revisor.');
            }

            if ($this->revisores->porUsuarioId($usuarioId) !== null) {
                return $this->volverConError('Ese usuario ya está vinculado como revisor.');
            }

            $id = $this->revisores->crear($campos + ['usuario_id' => $usuarioId]);
            $this->pantalla->auditar($peticion, AccionAuditoria::Creacion, 'cc_revisor', $id, "Revisor {$nombre} vinculado");
        } else {
            // El usuario vinculado no se cambia: si la persona se va, se
            // desactiva este revisor y se vincula otro. Así las revisiones
            // viejas siguen diciendo quién las firmó.
            $this->revisores->actualizar($id, $campos);
            $this->pantalla->auditar($peticion, AccionAuditoria::Actualizacion, 'cc_revisor', $id, "Revisor {$nombre} actualizado");
        }

        $this->pantalla->guardarEnLaSesion('cc_mensaje', 'Revisor guardado.');

        return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
    }

    private function volverConError(string $mensaje): RedirectResponse
    {
        $this->pantalla->guardarEnLaSesion('cc_error', $mensaje);

        return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
    }
}

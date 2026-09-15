<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Auth\HasherPassword;
use App\Core\Auth\PoliticaPassword;
use App\Core\Support\Vistas;
use App\Http\Admin\Soporte\PantallaAdmin;
use App\Modules\Sistema\Repository\DependenciaRepository;
use App\Modules\Sistema\Repository\RolRepository;
use App\Modules\Sistema\Repository\UsuarioAdminRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cuentas del panel: altas, cambios de rol, restablecimientos y bajas.
 *
 * Hasta ahora la única forma de crear un usuario era `bin/consola
 * usuarios:crear-admin`, lo que en la práctica significaba que el hospital
 * dependía de alguien con acceso al servidor para dar de alta a una
 * secretaria. Esta pantalla cierra ese hueco.
 *
 * Tres barandas que no se negocian, porque son las que impiden que un
 * descuido deje el sitio sin dueño o convierta a un funcionario en
 * administrador:
 *   1. Nadie se cambia el rol, se desactiva ni se da de baja a sí mismo.
 *   2. No se puede dejar la entidad sin ningún administrador activo.
 *   3. Las contraseñas las genera el sistema al crear o restablecer, se
 *      muestran una sola vez y el dueño de la cuenta las cambia al entrar.
 */
final readonly class UsuariosController
{
    private const string TITULO = 'Usuarios';
    private const string RUTA = '/admin/usuarios';

    public function __construct(
        private PantallaAdmin $pantalla,
        private Vistas $vistas,
        private UsuarioAdminRepository $usuarios,
        private RolRepository $roles,
        private DependenciaRepository $dependencias,
        private HasherPassword $hasher,
        private PoliticaPassword $politica,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        // La contraseña recién generada viaja por la sesión y no por la URL:
        // un «?password=Ab3…» queda en el historial del navegador, en los logs
        // del servidor y en el proxy de la entidad.
        $credencial = $this->pantalla->sacarDeLaSesion('credencial_nueva');

        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/usuarios/listado', [
            'usuarios' => $this->usuarios->todos(true),
            'roles' => $this->roles->paraSelector(),
            'dependencias' => $this->dependencias->listarParaPanel(),
            'credencial' => $credencial,
            'usuarioActual' => $this->pantalla->usuarioId(),
            'mensaje' => $peticion->query->has('guardado') ? 'Cambios guardados.' : null,
            'error' => $this->errorDe($peticion->query->getString('error')),
            'tokenCsrf' => $this->pantalla->tokenCsrf(),
        ]));
    }

    public function crear(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $r = $peticion->request;
        $nombre = trim($r->getString('nombre'));
        $correo = mb_strtolower(trim($r->getString('correo')));
        $rolId = $r->getInt('rol_id');

        if ($nombre === '' || filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            return $this->volverCon('datos');
        }

        if ($this->roles->porId($rolId) === null) {
            return $this->volverCon('rol');
        }

        if ($this->usuarios->correoOcupado($correo)) {
            return $this->volverCon('correo');
        }

        $password = $this->politica->generar();
        $dependenciaId = $r->getInt('dependencia_id');
        $cargo = trim($r->getString('cargo'));

        $id = $this->usuarios->crear([
            'nombre' => mb_substr($nombre, 0, 160),
            'correo' => mb_substr($correo, 0, 160),
            'hash_password' => $this->hasher->hash($password),
            'rol_id' => $rolId,
            'dependencia_id' => $dependenciaId > 0 ? $dependenciaId : null,
            'cargo' => $cargo !== '' ? mb_substr($cargo, 0, 120) : null,
            'activo' => 1,
            // Quien crea la cuenta conoce la clave inicial; el dueño la cambia
            // en su primera entrada y desde ahí solo la sabe él.
            'debe_cambiar_pass' => 1,
        ]);

        $this->pantalla->auditar($peticion, AccionAuditoria::Creacion, 'usuarios', $id, sprintf(
            'Creó la cuenta «%s»',
            $correo,
        ));

        $this->pantalla->guardarEnLaSesion('credencial_nueva', [
            'correo' => $correo,
            'password' => $password,
            'motivo' => 'creada',
        ]);

        return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
    }

    public function actualizar(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $usuario = $this->usuarios->porId($id);

        if ($usuario === null) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        $r = $peticion->request;
        $nombre = trim($r->getString('nombre'));
        $rolId = $r->getInt('rol_id');

        if ($nombre === '' || $this->roles->porId($rolId) === null) {
            return $this->volverCon('datos');
        }

        $esUnoMismo = $id === $this->pantalla->usuarioId();
        $activo = $r->getBoolean('activo');

        // Cambiarse el propio rol es escalada de privilegios con otro nombre, y
        // desactivarse a uno mismo deja al funcionario fuera sin remedio.
        if ($esUnoMismo && ((int) $usuario['rol_id'] !== $rolId || !$activo)) {
            return $this->volverCon('uno_mismo');
        }

        if (!$this->quedaAlgunAdministrador($usuario, $rolId, $activo)) {
            return $this->volverCon('ultimo_admin');
        }

        $dependenciaId = $r->getInt('dependencia_id');
        $cargo = trim($r->getString('cargo'));
        $rolCambio = (int) $usuario['rol_id'] !== $rolId;

        $this->usuarios->actualizar($id, [
            'nombre' => mb_substr($nombre, 0, 160),
            'rol_id' => $rolId,
            'dependencia_id' => $dependenciaId > 0 ? $dependenciaId : null,
            'cargo' => $cargo !== '' ? mb_substr($cargo, 0, 120) : null,
            'activo' => $activo ? 1 : 0,
        ]);

        $this->pantalla->auditar(
            $peticion,
            $rolCambio ? AccionAuditoria::CambioPermisos : AccionAuditoria::Actualizacion,
            'usuarios',
            $id,
            sprintf(
                'Editó la cuenta «%s»%s',
                (string) $usuario['correo'],
                $rolCambio
                    ? sprintf(': rol %s → %s', (string) $usuario['rol_nombre'], $this->nombreDeRol($rolId))
                    : '',
            ),
        );

        return new RedirectResponse(self::RUTA . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    /**
     * Restablecimiento: clave temporal y cambio obligatorio al entrar.
     *
     * No existe «ver la contraseña de alguien» ni envío por correo: el hash es
     * irreversible y el correo institucional no es canal seguro para una
     * credencial. Se muestra en pantalla, una vez, a quien la generó.
     */
    public function restablecer(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $usuario = $this->usuarios->porId($id);

        if ($usuario === null) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        $password = $this->politica->generar();
        $this->usuarios->cambiarPassword($id, $this->hasher->hash($password), true);

        $this->pantalla->auditar($peticion, AccionAuditoria::RestablecerPassword, 'usuarios', $id, sprintf(
            'Restableció la contraseña de «%s»',
            (string) $usuario['correo'],
        ));

        $this->pantalla->guardarEnLaSesion('credencial_nueva', [
            'correo' => (string) $usuario['correo'],
            'password' => $password,
            'motivo' => 'restablecida',
        ]);

        return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
    }

    public function desbloquear(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $usuario = $this->usuarios->porId($id);

        if ($usuario !== null) {
            $this->usuarios->desbloquear($id);
            $this->pantalla->auditar($peticion, AccionAuditoria::DesbloqueoCuenta, 'usuarios', $id, sprintf(
                'Desbloqueó la cuenta «%s»',
                (string) $usuario['correo'],
            ));
        }

        return new RedirectResponse(self::RUTA . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    public function eliminar(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $usuario = $this->usuarios->porId($id);

        if ($usuario === null) {
            return new RedirectResponse(self::RUTA, Response::HTTP_SEE_OTHER);
        }

        if ($id === $this->pantalla->usuarioId()) {
            return $this->volverCon('uno_mismo');
        }

        if (!$this->quedaAlgunAdministrador($usuario, (int) $usuario['rol_id'], false)) {
            return $this->volverCon('ultimo_admin');
        }

        $this->usuarios->eliminar($id);

        $this->pantalla->auditar($peticion, AccionAuditoria::Eliminacion, 'usuarios', $id, sprintf(
            'Dio de baja la cuenta «%s»',
            (string) $usuario['correo'],
        ));

        return new RedirectResponse(self::RUTA . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    public function restaurar(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $id = $peticion->attributes->getInt('id');
        $usuario = $this->usuarios->porId($id);

        if ($usuario !== null) {
            $this->usuarios->restaurar($id);
            $this->pantalla->auditar($peticion, AccionAuditoria::Restauracion, 'usuarios', $id, sprintf(
                'Reactivó la cuenta «%s»',
                (string) $usuario['correo'],
            ));
        }

        return new RedirectResponse(self::RUTA . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    // --- soporte ---

    /**
     * ¿Queda algún administrador activo después de este cambio?
     *
     * El rol con permiso total es el único que puede volver a repartir
     * permisos: quedarse sin ninguno obliga a entrar por consola al servidor,
     * que es justo la dependencia que este módulo viene a quitar.
     *
     * @param array<string, mixed> $usuario
     */
    private function quedaAlgunAdministrador(array $usuario, int $rolIdNuevo, bool $seguiraActivo): bool
    {
        $eraAdministrador = $this->esRolTotal((int) $usuario['rol_id'])
            && (bool) $usuario['activo']
            && $usuario['eliminado_en'] === null;

        if (!$eraAdministrador) {
            return true;
        }

        $seguiraSiendolo = $this->esRolTotal($rolIdNuevo) && $seguiraActivo;

        return $seguiraSiendolo || $this->usuarios->administradoresActivos() > 1;
    }

    private function esRolTotal(int $rolId): bool
    {
        $rol = $this->roles->porId($rolId);

        return $rol !== null && in_array('*', $rol['permisos'], true);
    }

    private function nombreDeRol(int $rolId): string
    {
        $rol = $this->roles->porId($rolId);

        return $rol === null ? 'desconocido' : (string) $rol['nombre'];
    }

    private function volverCon(string $error): Response
    {
        return new RedirectResponse(self::RUTA . '?error=' . $error, Response::HTTP_SEE_OTHER);
    }

    private function errorDe(string $codigo): ?string
    {
        return match ($codigo) {
            'datos' => 'Revisa los datos: el nombre y un correo válido son obligatorios.',
            'correo' => 'Ya existe una cuenta con ese correo.',
            'rol' => 'El rol seleccionado no existe.',
            'uno_mismo' => 'No puedes cambiarte el rol, desactivarte ni darte de baja a ti mismo. Pídeselo a otro administrador.',
            'ultimo_admin' => 'Es el último administrador activo: nombra otro antes de quitarle el rol, desactivarlo o darlo de baja.',
            default => null,
        };
    }
}

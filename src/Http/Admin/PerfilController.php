<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Audit\AccionAuditoria;
use App\Core\Auth\HasherPassword;
use App\Core\Auth\PoliticaPassword;
use App\Core\Auth\Sesion;
use App\Core\Support\Vistas;
use App\Http\Admin\Soporte\PantallaAdmin;
use App\Modules\Sistema\Repository\UsuarioAdminRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La cuenta propia del funcionario: sus datos y su contraseña.
 *
 * Sin permiso asociado y a propósito: todo el que entra al panel tiene
 * derecho a cambiar su propia clave, incluso el usuario con el rol más
 * limitado. Condicionarlo a un permiso dejaría cuentas que solo el
 * administrador puede rescatar.
 */
final readonly class PerfilController
{
    private const string TITULO = 'Mi cuenta';
    private const string RUTA = '/admin/perfil';

    public function __construct(
        private PantallaAdmin $pantalla,
        private Sesion $sesion,
        private Vistas $vistas,
        private UsuarioAdminRepository $usuarios,
        private HasherPassword $hasher,
        private PoliticaPassword $politica,
    ) {
    }

    public function index(Request $peticion): Response
    {
        $usuario = $this->usuarioActual();

        if ($usuario === null) {
            return new RedirectResponse('/admin/entrar', Response::HTTP_SEE_OTHER);
        }

        return $this->pantalla->pagina(self::TITULO, $this->vistas->render('admin/perfil/index', [
            'usuario' => $usuario,
            'mensaje' => match (true) {
                $peticion->query->has('guardado') => 'Datos actualizados.',
                $peticion->query->has('password') => 'Tu contraseña quedó cambiada.',
                default => null,
            },
            'error' => $peticion->query->has('invalido') ? 'Revisa el nombre: no puede quedar vacío.' : null,
            'tokenCsrf' => $this->pantalla->tokenCsrf(),
            'largoMinimo' => PoliticaPassword::LARGO_MINIMO,
        ]));
    }

    public function guardar(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, self::RUTA)) !== null) {
            return $rechazo;
        }

        $usuario = $this->usuarioActual();

        if ($usuario === null) {
            return new RedirectResponse('/admin/entrar', Response::HTTP_SEE_OTHER);
        }

        $nombre = trim($peticion->request->getString('nombre'));

        if ($nombre === '') {
            return new RedirectResponse(self::RUTA . '?invalido=1', Response::HTTP_SEE_OTHER);
        }

        $cargo = trim($peticion->request->getString('cargo'));

        // El correo y el rol no se editan aquí: el correo es la credencial de
        // ingreso y el rol son los permisos. Cambiarse uno mismo cualquiera de
        // los dos es escalada de privilegios con otro nombre.
        $this->usuarios->actualizar((int) $usuario['id'], [
            'nombre' => mb_substr($nombre, 0, 160),
            'cargo' => $cargo !== '' ? mb_substr($cargo, 0, 120) : null,
        ]);

        $this->sesion->renombrar($nombre);

        $this->pantalla->auditar(
            $peticion,
            AccionAuditoria::Actualizacion,
            'usuarios',
            (int) $usuario['id'],
            'Actualizó los datos de su propia cuenta',
        );

        return new RedirectResponse(self::RUTA . '?guardado=1', Response::HTTP_SEE_OTHER);
    }

    /**
     * Pantalla de cambio de contraseña.
     *
     * Es la misma para el cambio voluntario y para el obligatorio de la
     * primera entrada; lo único que cambia es el texto y que en el obligatorio
     * el Kernel no deja ir a ninguna otra parte del panel.
     */
    public function formularioPassword(Request $peticion): Response
    {
        if ($this->pantalla->usuarioId() === null) {
            return new RedirectResponse('/admin/entrar', Response::HTTP_SEE_OTHER);
        }

        return $this->paginaPassword();
    }

    public function cambiarPassword(Request $peticion): Response
    {
        if (($rechazo = $this->pantalla->rechazoCsrf($peticion, self::TITULO, '/admin/perfil/password')) !== null) {
            return $rechazo;
        }

        $usuario = $this->usuarioActual();

        if ($usuario === null) {
            return new RedirectResponse('/admin/entrar', Response::HTTP_SEE_OTHER);
        }

        $actual = $peticion->request->getString('password_actual');
        $nueva = $peticion->request->getString('password_nueva');
        $confirmacion = $peticion->request->getString('password_confirmacion');

        // Se pide la contraseña vigente aunque haya sesión abierta: un panel
        // dejado abierto sin bloquear es la forma habitual de que alguien más
        // se apodere de la cuenta.
        if (!$this->hasher->verificar($actual, (string) $usuario['hash_password'])) {
            return $this->paginaPassword(['La contraseña actual no es correcta.']);
        }

        if ($nueva !== $confirmacion) {
            return $this->paginaPassword(['La confirmación no coincide con la contraseña nueva.']);
        }

        if ($nueva === $actual) {
            return $this->paginaPassword(['La contraseña nueva debe ser distinta de la actual.']);
        }

        $problemas = $this->politica->problemas($nueva, (string) $usuario['correo'], (string) $usuario['nombre']);

        if ($problemas !== []) {
            return $this->paginaPassword($problemas);
        }

        $this->usuarios->cambiarPassword((int) $usuario['id'], $this->hasher->hash($nueva), false);
        $this->sesion->passwordCambiada();

        $this->pantalla->auditar(
            $peticion,
            AccionAuditoria::CambioPassword,
            'usuarios',
            (int) $usuario['id'],
            'Cambió su propia contraseña',
        );

        return new RedirectResponse(self::RUTA . '?password=1', Response::HTTP_SEE_OTHER);
    }

    // --- soporte ---

    /**
     * @param list<string> $errores
     */
    private function paginaPassword(array $errores = []): Response
    {
        $this->sesion->iniciar();
        $obligatorio = $this->sesion->debeCambiarPassword();

        $contenido = $this->vistas->render('admin/perfil/password', [
            'obligatorio' => $obligatorio,
            'errores' => $errores,
            'tokenCsrf' => $this->pantalla->tokenCsrf(),
            'largoMinimo' => PoliticaPassword::LARGO_MINIMO,
        ]);

        $estado = $errores === [] ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY;

        return $this->pantalla->pagina('Cambiar contraseña', $contenido, $estado);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function usuarioActual(): ?array
    {
        $id = $this->pantalla->usuarioId();

        return $id === null ? null : $this->usuarios->porId($id);
    }
}

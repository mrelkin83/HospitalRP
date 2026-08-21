<?php

declare(strict_types=1);

namespace App\Core\Auth;

use App\Core\Config\ConfigAplicacion;
use App\Core\Config\Env;

/**
 * Sesión HTTP del panel.
 *
 * Cookies HttpOnly + SameSite=Strict siempre; Secure cuando el entorno exige
 * HTTPS (en local no hay TLS y la cookie no llegaría). El ID se regenera al
 * autenticar para cortar la fijación de sesión, y la caducidad por
 * inactividad se controla en el servidor, no en la cookie: la fecha de una
 * cookie la decide el navegador del atacante.
 */
final class Sesion
{
    private const string CLAVE_USUARIO_ID = '_usuario_id';
    private const string CLAVE_USUARIO_NOMBRE = '_usuario_nombre';
    private const string CLAVE_ULTIMA_ACTIVIDAD = '_ultima_actividad';
    private const string CLAVE_CSRF = '_csrf';

    public function __construct(
        private readonly string $nombre,
        private readonly int $vidaMinutos,
        private readonly bool $cookieSegura,
    ) {
    }

    public static function desde(Env $env, ConfigAplicacion $config): self
    {
        return new self(
            nombre: $env->texto('SESION_NOMBRE', 'hrpl_sesion'),
            vidaMinutos: $env->entero('SESION_VIDA_MINUTOS', 120),
            cookieSegura: $config->entorno->exigeHttps(),
        );
    }

    public function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name($this->nombre);
        session_set_cookie_params([
            // 0 = cookie de sesión: muere al cerrar el navegador. La caducidad
            // real por inactividad se valida abajo, en el servidor.
            'lifetime' => 0,
            'path' => '/',
            'secure' => $this->cookieSegura,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        // Rechaza IDs de sesión que el servidor no haya emitido.
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string) ($this->vidaMinutos * 60));

        session_start();

        $ahora = time();
        $ultimaActividad = $_SESSION[self::CLAVE_ULTIMA_ACTIVIDAD] ?? null;

        if (is_int($ultimaActividad) && ($ahora - $ultimaActividad) > $this->vidaMinutos * 60) {
            $this->vaciar();
        }

        $_SESSION[self::CLAVE_ULTIMA_ACTIVIDAD] = $ahora;
    }

    public function autenticar(Usuario $usuario): void
    {
        // Nueva identidad de sesión: lo que el navegador tuviera antes de
        // autenticarse deja de valer (fijación de sesión).
        session_regenerate_id(true);

        $_SESSION[self::CLAVE_USUARIO_ID] = $usuario->id;
        $_SESSION[self::CLAVE_USUARIO_NOMBRE] = $usuario->nombre;
    }

    public function usuarioId(): ?int
    {
        $id = $_SESSION[self::CLAVE_USUARIO_ID] ?? null;

        return is_int($id) ? $id : null;
    }

    public function usuarioNombre(): ?string
    {
        $nombre = $_SESSION[self::CLAVE_USUARIO_NOMBRE] ?? null;

        return is_string($nombre) ? $nombre : null;
    }

    public function cerrar(): void
    {
        $this->vaciar();

        if (PHP_SAPI !== 'cli') {
            setcookie($this->nombre, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => $this->cookieSegura,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }

        session_destroy();
    }

    public function tokenCsrf(): string
    {
        $token = $_SESSION[self::CLAVE_CSRF] ?? null;

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::CLAVE_CSRF] = $token;
        }

        return $token;
    }

    public function validarCsrf(?string $token): bool
    {
        $esperado = $_SESSION[self::CLAVE_CSRF] ?? null;

        return is_string($esperado)
            && $esperado !== ''
            && is_string($token)
            && hash_equals($esperado, $token);
    }

    private function vaciar(): void
    {
        $_SESSION = [];
    }
}

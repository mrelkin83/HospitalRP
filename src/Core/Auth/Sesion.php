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
    private const string CLAVE_DEBE_CAMBIAR_PASS = '_debe_cambiar_pass';
    private const string CLAVE_CSRF = '_csrf';
    private const string CLAVE_FLASH = '_flash';
    private const string CLAVE_PERSISTENTE = '_persistente';

    public function __construct(
        private readonly string $nombre,
        private readonly int $vidaMinutos,
        private readonly bool $cookieSegura,
        private readonly int $vidaPersistenteMinutos = 43200,
    ) {
    }

    public static function desde(Env $env, ConfigAplicacion $config): self
    {
        return new self(
            nombre: $env->texto('SESION_NOMBRE', 'hrpl_sesion'),
            vidaMinutos: $env->entero('SESION_VIDA_MINUTOS', 120),
            cookieSegura: $config->entorno->exigeHttps(),
            // «Mantener la sesión iniciada»: 30 días por defecto.
            vidaPersistenteMinutos: $env->entero('SESION_VIDA_PERSISTENTE_DIAS', 30) * 24 * 60,
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
        // El recolector se ajusta a la vida MÁS LARGA de las dos, o borraría
        // del disco las sesiones persistentes a las dos horas. Quien decide de
        // verdad si una sesión caducó es la comprobación de abajo, que sí
        // distingue entre persistente y normal.
        ini_set('session.gc_maxlifetime', (string) (max($this->vidaMinutos, $this->vidaPersistenteMinutos) * 60));

        session_start();

        $ahora = time();
        $persistente = ($_SESSION[self::CLAVE_PERSISTENTE] ?? false) === true;
        $vidaMinutos = $persistente ? $this->vidaPersistenteMinutos : $this->vidaMinutos;
        $ultimaActividad = $_SESSION[self::CLAVE_ULTIMA_ACTIVIDAD] ?? null;

        if (is_int($ultimaActividad) && ($ahora - $ultimaActividad) > $vidaMinutos * 60) {
            $this->vaciar();
            $persistente = false;
        }

        $_SESSION[self::CLAVE_ULTIMA_ACTIVIDAD] = $ahora;

        // Caducidad deslizante: mientras el funcionario siga entrando, la
        // cookie se renueva. Si deja de entrar, muere sola a los 30 días.
        if ($persistente) {
            $this->emitirCookie($ahora + $this->vidaPersistenteMinutos * 60);
        }
    }

    /**
     * @param bool $persistente «Mantener la sesión iniciada»: la cookie
     *                         sobrevive al cierre del navegador.
     */
    public function autenticar(Usuario $usuario, bool $persistente = false): void
    {
        // Nueva identidad de sesión: lo que el navegador tuviera antes de
        // autenticarse deja de valer (fijación de sesión).
        session_regenerate_id(true);

        $_SESSION[self::CLAVE_USUARIO_ID] = $usuario->id;
        $_SESSION[self::CLAVE_USUARIO_NOMBRE] = $usuario->nombre;
        $_SESSION[self::CLAVE_DEBE_CAMBIAR_PASS] = $usuario->debeCambiarPassword;
        $_SESSION[self::CLAVE_PERSISTENTE] = $persistente;

        // `session_regenerate_id` reemite la cookie con los parámetros de
        // `session_set_cookie_params`, es decir con `lifetime = 0`. Si el
        // usuario pidió seguir conectado hay que volver a emitirla DESPUÉS,
        // ya con fecha, o la sesión moriría igual al cerrar el navegador.
        if ($persistente) {
            $this->emitirCookie(time() + $this->vidaPersistenteMinutos * 60);
        }
    }

    /**
     * Contraseña provisional pendiente de cambio.
     *
     * Se guarda en la sesión al autenticar en vez de consultarse en cada
     * petición: el Kernel pregunta esto en toda pantalla del panel y una
     * consulta por petición para leer un booleano no se paga.
     */
    public function debeCambiarPassword(): bool
    {
        return ($_SESSION[self::CLAVE_DEBE_CAMBIAR_PASS] ?? false) === true;
    }

    public function passwordCambiada(): void
    {
        $_SESSION[self::CLAVE_DEBE_CAMBIAR_PASS] = false;
    }

    /** El nombre del encabezado del panel tras editar el perfil propio. */
    public function renombrar(string $nombre): void
    {
        $_SESSION[self::CLAVE_USUARIO_NOMBRE] = $nombre;
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

    /**
     * Reemite la cookie de sesión con fecha de caducidad.
     *
     * Los atributos repiten los de `session_set_cookie_params` a propósito:
     * `setcookie` no hereda nada: lo que no se declare aquí se pierde, y
     * perder `httponly` o `SameSite` en la cookie de un panel administrativo
     * es exactamente el agujero que esos atributos vienen a tapar.
     */
    private function emitirCookie(int $caduca): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }

        setcookie($this->nombre, (string) session_id(), [
            'expires' => $caduca,
            'path' => '/',
            'secure' => $this->cookieSegura,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
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

    /**
     * Guarda un dato para la siguiente petición y solo para ella.
     *
     * Existe por las contraseñas temporales: tras crear un usuario hay que
     * enseñar la clave generada una vez, y ni la URL ni la base de datos son
     * sitio para eso. La sesión del funcionario que la generó, sí.
     */
    public function guardarFlash(string $clave, mixed $valor): void
    {
        $_SESSION[self::CLAVE_FLASH][$clave] = $valor;
    }

    /** Lee el dato y lo borra: al recargar la pantalla ya no está. */
    public function sacarFlash(string $clave): mixed
    {
        $valor = $_SESSION[self::CLAVE_FLASH][$clave] ?? null;
        unset($_SESSION[self::CLAVE_FLASH][$clave]);

        return $valor;
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

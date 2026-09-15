<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Auth\AutenticadorService;
use App\Core\Auth\ResultadoLogin;
use App\Core\Auth\Sesion;
use App\Core\Auth\Usuario;
use App\Core\Config\ConfigAplicacion;
use App\Core\Support\Vistas;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entrada y salida del panel.
 */
final readonly class LoginController
{
    /**
     * Cookie de «recordar mi cuenta». Guarda SOLO el correo, para rellenar el
     * campo; la sesión viaja aparte, en su propia cookie. Mezclar las dos
     * cosas convertiría una comodidad de tecleo en una credencial.
     */
    private const string COOKIE_CORREO = 'hrpl_correo';
    private const int DIAS_RECUERDO = 365;

    public function __construct(
        private Sesion $sesion,
        private AutenticadorService $autenticador,
        private Vistas $vistas,
        private ConfigAplicacion $config,
    ) {
    }

    public function formulario(Request $peticion): Response
    {
        $this->sesion->iniciar();

        if ($this->sesion->usuarioId() !== null) {
            return new RedirectResponse('/admin', Response::HTTP_SEE_OTHER);
        }

        return $this->paginaLogin(correo: $this->correoRecordado($peticion));
    }

    public function entrar(Request $peticion): Response
    {
        $this->sesion->iniciar();

        if ($this->sesion->usuarioId() !== null) {
            return new RedirectResponse('/admin', Response::HTTP_SEE_OTHER);
        }

        $correo = trim($peticion->request->getString('correo'));
        $recordar = $peticion->request->getBoolean('recordar');
        $persistente = $peticion->request->getBoolean('persistente');

        if (!$this->sesion->validarCsrf($peticion->request->getString('_token'))) {
            return $this->paginaLogin(
                'Tu sesión expiró antes de enviar el formulario. Intenta de nuevo.',
                Response::HTTP_FORBIDDEN,
                $correo,
                $recordar,
                $persistente,
            );
        }

        $autenticacion = $this->autenticador->intentar(
            correo: $correo,
            password: $peticion->request->getString('password'),
            ip: $peticion->getClientIp(),
            userAgent: $peticion->headers->get('User-Agent'),
        );

        /** @var Usuario $usuario en el caso Correcto nunca es null */
        $usuario = $autenticacion->usuario;

        return match ($autenticacion->resultado) {
            ResultadoLogin::Correcto => $this->abrirSesion($usuario, $correo, $recordar, $persistente),
            ResultadoLogin::CuentaBloqueada => $this->paginaLogin(
                'La cuenta está bloqueada temporalmente por intentos fallidos. Espera unos minutos y vuelve a intentar.',
                Response::HTTP_TOO_MANY_REQUESTS,
                $correo,
                $recordar,
                $persistente,
            ),
            ResultadoLogin::CredencialesInvalidas => $this->paginaLogin(
                'Correo o contraseña incorrectos.',
                Response::HTTP_UNAUTHORIZED,
                $correo,
                $recordar,
                $persistente,
            ),
        };
    }

    public function salir(Request $peticion): Response
    {
        $this->sesion->iniciar();
        $usuarioId = $this->sesion->usuarioId();

        if ($usuarioId !== null && $this->sesion->validarCsrf($peticion->request->getString('_token'))) {
            $this->autenticador->cerrarSesion(
                usuarioId: $usuarioId,
                nombre: $this->sesion->usuarioNombre() ?? '',
                ip: $peticion->getClientIp(),
                userAgent: $peticion->headers->get('User-Agent'),
            );
            $this->sesion->cerrar();
        }

        return new RedirectResponse('/admin/entrar', Response::HTTP_SEE_OTHER);
    }

    private function abrirSesion(Usuario $usuario, string $correo, bool $recordar, bool $persistente): Response
    {
        $this->sesion->autenticar($usuario, $persistente);

        $respuesta = new RedirectResponse('/admin', Response::HTTP_SEE_OTHER);
        $respuesta->headers->setCookie($this->cookieCorreo($recordar ? $correo : null));

        return $respuesta;
    }

    /**
     * La cookie que recuerda el correo. Con `$correo = null` la borra: al
     * desmarcar la casilla el correo tiene que desaparecer del equipo, que es
     * justo lo que se espera al desmarcarla en un computador compartido.
     */
    private function cookieCorreo(?string $correo): Cookie
    {
        return Cookie::create(
            name: self::COOKIE_CORREO,
            value: $correo ?? '',
            expire: $correo === null ? 1 : time() + self::DIAS_RECUERDO * 24 * 3600,
            path: '/',
            secure: $this->config->entorno->exigeHttps(),
            httpOnly: true,
            sameSite: Cookie::SAMESITE_STRICT,
        );
    }

    /** El correo guardado, si lo hay y si parece un correo. */
    private function correoRecordado(Request $peticion): string
    {
        $correo = trim((string) $peticion->cookies->get(self::COOKIE_CORREO, ''));

        return filter_var($correo, FILTER_VALIDATE_EMAIL) === false ? '' : $correo;
    }

    private function paginaLogin(
        ?string $error = null,
        int $estado = Response::HTTP_OK,
        string $correo = '',
        bool $recordar = false,
        bool $persistente = false,
    ): Response {
        $html = $this->vistas->render('admin/login', [
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'error' => $error,
            'correo' => $correo,
            // Si el correo vino de la cookie, la casilla ya estaba marcada:
            // pintarla vacía haría que al entrar se borrase el recuerdo.
            'recordar' => $recordar || $correo !== '',
            'persistente' => $persistente,
        ]);

        $respuesta = new Response($html, $estado, ['Content-Type' => 'text/html; charset=utf-8']);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }
}

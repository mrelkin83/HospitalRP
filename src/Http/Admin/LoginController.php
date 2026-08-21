<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Auth\AutenticadorService;
use App\Core\Auth\ResultadoLogin;
use App\Core\Auth\Sesion;
use App\Core\Auth\Usuario;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entrada y salida del panel.
 *
 * El HTML vive aquí de forma provisional, igual que en InicioController: el
 * sistema de plantillas y el diseño llegan en los pasos 9 y 10 de la Fase 0.
 */
final readonly class LoginController
{
    public function __construct(
        private Sesion $sesion,
        private AutenticadorService $autenticador,
    ) {
    }

    public function formulario(Request $peticion): Response
    {
        $this->sesion->iniciar();

        if ($this->sesion->usuarioId() !== null) {
            return new RedirectResponse('/admin', Response::HTTP_SEE_OTHER);
        }

        return $this->paginaLogin();
    }

    public function entrar(Request $peticion): Response
    {
        $this->sesion->iniciar();

        if ($this->sesion->usuarioId() !== null) {
            return new RedirectResponse('/admin', Response::HTTP_SEE_OTHER);
        }

        if (!$this->sesion->validarCsrf($peticion->request->getString('_token'))) {
            return $this->paginaLogin(
                'Tu sesión expiró antes de enviar el formulario. Intenta de nuevo.',
                Response::HTTP_FORBIDDEN,
            );
        }

        $autenticacion = $this->autenticador->intentar(
            correo: trim($peticion->request->getString('correo')),
            password: $peticion->request->getString('password'),
            ip: $peticion->getClientIp(),
            userAgent: $peticion->headers->get('User-Agent'),
        );

        /** @var Usuario $usuario en el caso Correcto nunca es null */
        $usuario = $autenticacion->usuario;

        return match ($autenticacion->resultado) {
            ResultadoLogin::Correcto => $this->abrirSesion($usuario),
            ResultadoLogin::CuentaBloqueada => $this->paginaLogin(
                'La cuenta está bloqueada temporalmente por intentos fallidos. Espera unos minutos y vuelve a intentar.',
                Response::HTTP_TOO_MANY_REQUESTS,
            ),
            ResultadoLogin::CredencialesInvalidas => $this->paginaLogin(
                'Correo o contraseña incorrectos.',
                Response::HTTP_UNAUTHORIZED,
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

    private function abrirSesion(Usuario $usuario): Response
    {
        $this->sesion->autenticar($usuario);

        return new RedirectResponse('/admin', Response::HTTP_SEE_OTHER);
    }

    private function paginaLogin(?string $error = null, int $estado = Response::HTTP_OK): Response
    {
        $token = $this->escapar($this->sesion->tokenCsrf());
        $alerta = $error === null
            ? ''
            : sprintf('<p role="alert"><strong>%s</strong></p>', $this->escapar($error));

        $html = <<<HTML
            <!doctype html>
            <html lang="es">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="noindex">
                <title>Ingreso al panel — E.S.E. Hospital Rosario Pumarejo de López</title>
            </head>
            <body>
                <main>
                    <h1>Panel administrativo</h1>
                    <p>E.S.E. Hospital Rosario Pumarejo de López</p>
                    {$alerta}
                    <form method="post" action="/admin/entrar">
                        <input type="hidden" name="_token" value="{$token}">
                        <p>
                            <label for="correo">Correo institucional</label><br>
                            <input type="email" id="correo" name="correo" required
                                   autocomplete="username" autofocus>
                        </p>
                        <p>
                            <label for="password">Contraseña</label><br>
                            <input type="password" id="password" name="password" required
                                   autocomplete="current-password">
                        </p>
                        <p><button type="submit">Ingresar</button></p>
                    </form>
                </main>
            </body>
            </html>
            HTML;

        $respuesta = new Response($html, $estado, ['Content-Type' => 'text/html; charset=utf-8']);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }

    private function escapar(string $texto): string
    {
        return htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

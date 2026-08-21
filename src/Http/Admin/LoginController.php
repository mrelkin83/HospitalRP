<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Auth\AutenticadorService;
use App\Core\Auth\ResultadoLogin;
use App\Core\Auth\Sesion;
use App\Core\Auth\Usuario;
use App\Core\Support\Vistas;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entrada y salida del panel.
 */
final readonly class LoginController
{
    public function __construct(
        private Sesion $sesion,
        private AutenticadorService $autenticador,
        private Vistas $vistas,
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
        $html = $this->vistas->render('admin/login', [
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'error' => $error,
        ]);

        $respuesta = new Response($html, $estado, ['Content-Type' => 'text/html; charset=utf-8']);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }
}

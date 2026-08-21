<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Auth\Sesion;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cascarón del panel: en la Fase 0 solo prueba que la sesión funciona.
 * La navegación y el layout responsivo llegan en el paso 10.
 */
final readonly class PanelController
{
    public function __construct(private Sesion $sesion)
    {
    }

    public function index(Request $peticion): Response
    {
        $this->sesion->iniciar();

        if ($this->sesion->usuarioId() === null) {
            return new RedirectResponse('/admin/entrar', Response::HTTP_SEE_OTHER);
        }

        $nombre = $this->escapar($this->sesion->usuarioNombre() ?? 'Usuario');
        $token = $this->escapar($this->sesion->tokenCsrf());

        $html = <<<HTML
            <!doctype html>
            <html lang="es">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="noindex">
                <title>Panel — E.S.E. Hospital Rosario Pumarejo de López</title>
            </head>
            <body>
                <main>
                    <h1>Panel administrativo</h1>
                    <p>Hola, {$nombre}. El panel está vacío: los módulos llegan en las próximas fases.</p>
                    <form method="post" action="/admin/salir">
                        <input type="hidden" name="_token" value="{$token}">
                        <button type="submit">Cerrar sesión</button>
                    </form>
                </main>
            </body>
            </html>
            HTML;

        $respuesta = new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/html; charset=utf-8']);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }

    private function escapar(string $texto): string
    {
        return htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

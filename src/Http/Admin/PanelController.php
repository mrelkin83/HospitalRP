<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Core\Auth\Sesion;
use App\Core\Support\Vistas;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Portada del panel. El acceso lo garantiza el Kernel (ruta `_acceso:
 * privado`); la comprobación de aquí es defensa en profundidad por si la
 * ruta se reconfigura mal.
 */
final readonly class PanelController
{
    public function __construct(
        private Sesion $sesion,
        private Vistas $vistas,
    ) {
    }

    public function index(Request $peticion): Response
    {
        $this->sesion->iniciar();

        if ($this->sesion->usuarioId() === null) {
            return new RedirectResponse('/admin/entrar', Response::HTTP_SEE_OTHER);
        }

        $nombre = $this->sesion->usuarioNombre() ?? 'Usuario';

        $html = $this->vistas->render('admin/base', [
            'titulo' => 'Inicio',
            'usuarioNombre' => $nombre,
            'tokenCsrf' => $this->sesion->tokenCsrf(),
            'contenido' => $this->vistas->render('admin/panel', ['usuarioNombre' => $nombre]),
        ]);

        $respuesta = new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/html; charset=utf-8']);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }
}

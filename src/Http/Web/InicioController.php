<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Config\ConfigAplicacion;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provisional. Existe solo para probar que el ciclo petición → respuesta
 * funciona en la Fase 0. La home real se construye en la Fase 1, con sus
 * ocho acciones destacadas como máximo (CLAUDE.md §6).
 */
final readonly class InicioController
{
    public function __construct(private ConfigAplicacion $config)
    {
    }

    public function index(Request $peticion): Response
    {
        $entorno = htmlspecialchars(
            $this->config->entorno->value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );

        $html = <<<HTML
            <!doctype html>
            <html lang="es">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="noindex">
                <title>E.S.E. Hospital Rosario Pumarejo de López</title>
            </head>
            <body>
                <main>
                    <h1>E.S.E. Hospital Rosario Pumarejo de López</h1>
                    <p>Sitio en construcción. Fase 0 — Fundación.</p>
                    <p>Entorno: {$entorno}</p>
                </main>
            </body>
            </html>
            HTML;

        return new Response($html, Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }
}

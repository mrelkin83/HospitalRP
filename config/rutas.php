<?php

declare(strict_types=1);

/**
 * Rutas del sitio público y del panel.
 *
 * Se declaran en PHP y no en YAML a propósito: evita traer symfony/yaml y el
 * autocompletado del editor funciona sobre las clases de controlador.
 *
 * El mapa completo de URLs está en docs/01-mapa-del-sitio-y-modelo-de-datos.md.
 * Las rutas se irán añadiendo fase por fase; no adelantar ninguna.
 */

use App\Http\Web\InicioController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

$rutas = new RouteCollection();

$rutas->add('inicio', new Route(
    path: '/',
    defaults: ['_controlador' => [InicioController::class, 'index']],
    methods: ['GET'],
));

return $rutas;

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

use App\Http\Admin\LoginController;
use App\Http\Admin\PanelController;
use App\Http\Web\ArchivoController;
use App\Http\Web\InicioController;
use App\Http\Web\MuestraDisenoController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

$rutas = new RouteCollection();

$rutas->add('inicio', new Route(
    path: '/',
    defaults: ['_controlador' => [InicioController::class, 'index']],
    methods: ['GET'],
));

// Muestra del sistema de diseño: el controlador responde 404 en producción.
$rutas->add('diseno.muestra', new Route(
    path: '/diseno',
    defaults: ['_controlador' => [MuestraDisenoController::class, 'index']],
    methods: ['GET'],
));

$rutas->add('archivos.entregar', new Route(
    path: '/archivos/{id}',
    defaults: ['_controlador' => [ArchivoController::class, 'entregar']],
    requirements: ['id' => '\d+'],
    methods: ['GET'],
));

// --- Panel administrativo ---

$rutas->add('admin.entrar.formulario', new Route(
    path: '/admin/entrar',
    defaults: ['_controlador' => [LoginController::class, 'formulario']],
    methods: ['GET'],
));

$rutas->add('admin.entrar', new Route(
    path: '/admin/entrar',
    defaults: ['_controlador' => [LoginController::class, 'entrar']],
    methods: ['POST'],
));

$rutas->add('admin.salir', new Route(
    path: '/admin/salir',
    defaults: ['_controlador' => [LoginController::class, 'salir']],
    methods: ['POST'],
));

$rutas->add('admin.panel', new Route(
    path: '/admin',
    defaults: [
        '_controlador' => [PanelController::class, 'index'],
        // Cualquier usuario autenticado ve el cascarón; los módulos del panel
        // declararán además su `_permiso` («contenido.ver», etc.).
        '_acceso' => 'privado',
    ],
    methods: ['GET'],
));

return $rutas;

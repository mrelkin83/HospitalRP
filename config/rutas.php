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
use App\Http\Admin\MediosController;
use App\Http\Admin\MenusController;
use App\Http\Admin\NoticiasController;
use App\Http\Admin\PanelController;
use App\Http\Web\ArchivoController;
use App\Http\Web\InicioController;
use App\Http\Web\MuestraDisenoController;
use App\Http\Web\NoticiasPublicoController;
use App\Http\Web\PaginasController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

$rutas = new RouteCollection();

$rutas->add('inicio', new Route(
    path: '/',
    defaults: ['_controlador' => [InicioController::class, 'index']],
    methods: ['GET'],
));

$rutas->add('buscar', new Route(
    path: '/buscar',
    defaults: ['_controlador' => [InicioController::class, 'buscar']],
    methods: ['GET'],
));

$rutas->add('noticias.listado', new Route(
    path: '/noticias',
    defaults: ['_controlador' => [NoticiasPublicoController::class, 'listado']],
    methods: ['GET'],
));

$rutas->add('noticias.detalle', new Route(
    path: '/noticias/{slug}',
    defaults: ['_controlador' => [NoticiasPublicoController::class, 'detalle']],
    requirements: ['slug' => '[a-z0-9\-]+'],
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

// --- Panel: noticias ---
// Redactar y publicar son permisos distintos; el de publicar se verifica en
// el controlador porque depende del estado de destino, no de la ruta.

$rutas->add('admin.noticias', new Route(
    path: '/admin/noticias',
    defaults: [
        '_controlador' => [NoticiasController::class, 'listado'],
        '_acceso' => 'privado',
        '_permiso' => 'contenido.ver',
    ],
    methods: ['GET'],
));

$rutas->add('admin.noticias.nueva', new Route(
    path: '/admin/noticias/nueva',
    defaults: [
        '_controlador' => [NoticiasController::class, 'formularioNueva'],
        '_acceso' => 'privado',
        '_permiso' => 'contenido.editar',
    ],
    methods: ['GET'],
));

$rutas->add('admin.noticias.guardar', new Route(
    path: '/admin/noticias',
    defaults: [
        '_controlador' => [NoticiasController::class, 'guardar'],
        '_acceso' => 'privado',
        '_permiso' => 'contenido.editar',
    ],
    methods: ['POST'],
));

$rutas->add('admin.noticias.editar', new Route(
    path: '/admin/noticias/{id}',
    defaults: [
        '_controlador' => [NoticiasController::class, 'formularioEditar'],
        '_acceso' => 'privado',
        '_permiso' => 'contenido.editar',
    ],
    requirements: ['id' => '\d+'],
    methods: ['GET'],
));

$rutas->add('admin.noticias.actualizar', new Route(
    path: '/admin/noticias/{id}',
    defaults: [
        '_controlador' => [NoticiasController::class, 'actualizar'],
        '_acceso' => 'privado',
        '_permiso' => 'contenido.editar',
    ],
    requirements: ['id' => '\d+'],
    methods: ['POST'],
));

$rutas->add('admin.noticias.estado', new Route(
    path: '/admin/noticias/{id}/estado',
    defaults: [
        '_controlador' => [NoticiasController::class, 'cambiarEstado'],
        '_acceso' => 'privado',
        '_permiso' => 'contenido.editar',
    ],
    requirements: ['id' => '\d+'],
    methods: ['POST'],
));

$rutas->add('admin.noticias.restaurar', new Route(
    path: '/admin/noticias/{id}/restaurar',
    defaults: [
        '_controlador' => [NoticiasController::class, 'restaurar'],
        '_acceso' => 'privado',
        '_permiso' => 'contenido.editar',
    ],
    requirements: ['id' => '\d+'],
    methods: ['POST'],
));

$rutas->add('admin.noticias.eliminar', new Route(
    path: '/admin/noticias/{id}/eliminar',
    defaults: [
        '_controlador' => [NoticiasController::class, 'eliminar'],
        '_acceso' => 'privado',
        '_permiso' => 'contenido.editar',
    ],
    requirements: ['id' => '\d+'],
    methods: ['POST'],
));

// --- Panel: biblioteca de medios ---

$rutas->add('admin.medios', new Route(
    path: '/admin/medios',
    defaults: [
        '_controlador' => [MediosController::class, 'listado'],
        '_acceso' => 'privado',
        '_permiso' => 'contenido.editar',
    ],
    methods: ['GET'],
));

$rutas->add('admin.medios.subir', new Route(
    path: '/admin/medios',
    defaults: [
        '_controlador' => [MediosController::class, 'subir'],
        '_acceso' => 'privado',
        '_permiso' => 'contenido.editar',
    ],
    methods: ['POST'],
));

// --- Panel: menús ---

$rutas->add('admin.menus', new Route(
    path: '/admin/menus',
    defaults: [
        '_controlador' => [MenusController::class, 'listado'],
        '_acceso' => 'privado',
        '_permiso' => 'sistema.menus',
    ],
    methods: ['GET'],
));

$rutas->add('admin.menus.item.guardar', new Route(
    path: '/admin/menus/items/{id}',
    defaults: [
        '_controlador' => [MenusController::class, 'guardarItem'],
        '_acceso' => 'privado',
        '_permiso' => 'sistema.menus',
    ],
    requirements: ['id' => '\d+'],
    methods: ['POST'],
));

$rutas->add('admin.menus.item.crear', new Route(
    path: '/admin/menus/{id}/items',
    defaults: [
        '_controlador' => [MenusController::class, 'crearItem'],
        '_acceso' => 'privado',
        '_permiso' => 'sistema.menus',
    ],
    requirements: ['id' => '\d+'],
    methods: ['POST'],
));

$rutas->add('admin.menus.item.eliminar', new Route(
    path: '/admin/menus/items/{id}/eliminar',
    defaults: [
        '_controlador' => [MenusController::class, 'eliminarItem'],
        '_acceso' => 'privado',
        '_permiso' => 'sistema.menus',
    ],
    requirements: ['id' => '\d+'],
    methods: ['POST'],
));

// --- Páginas institucionales por ruta dinámica ---
// SIEMPRE la última: atrapa lo que ninguna ruta fija reclamó. Si la ruta no
// corresponde a una página publicada, el controlador responde 404.
$rutas->add('paginas.mostrar', new Route(
    path: '/{ruta}',
    defaults: ['_controlador' => [PaginasController::class, 'mostrar']],
    requirements: ['ruta' => '[a-z0-9\-][a-z0-9\-/]*'],
    methods: ['GET'],
));

return $rutas;

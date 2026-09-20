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

use App\Http\Admin\AvisosController;
use App\Http\Admin\CampanasController;
use App\Http\Admin\CategoriasController;
use App\Http\Admin\CuentasContratistasController;
use App\Http\Admin\CuentasContratosController;
use App\Http\Admin\CuentasParametrosController;
use App\Http\Admin\CuentasReportesController;
use App\Http\Admin\CuentasSupervisionController;
use App\Http\Admin\CuentasSupervisoresController;
use App\Http\Admin\FaqController;
use App\Http\Admin\IaController;
use App\Http\Admin\LoginController;
use App\Http\Admin\MediosController;
use App\Http\Admin\HeroesController;
use App\Http\Admin\MenusController;
use App\Http\Admin\NoticiasController;
use App\Http\Admin\ConvocatoriasAdminController;
use App\Http\Admin\IndicadoresAdminController;
use App\Http\Admin\EncuestasAdminController;
use App\Http\Admin\EventosAdminController;
use App\Http\Admin\ServidoresPublicosAdminController;
use App\Http\Admin\DatasetsController;
use App\Http\Admin\DependenciasController;
use App\Http\Admin\DocumentosController;
use App\Http\Admin\BandejaTramitesController;
use App\Http\Admin\TiposTramiteController;
use App\Http\Admin\TransparenciaController;
use App\Http\Admin\EspecialidadesController;
use App\Http\Admin\PaginasAdminController;
use App\Http\Admin\PanelController;
use App\Http\Admin\AuditoriaAdminController;
use App\Http\Admin\ConfiguracionController;
use App\Http\Admin\PerfilController;
use App\Http\Admin\RedireccionesController;
use App\Http\Admin\RolesController;
use App\Http\Admin\UsuariosController;
use App\Http\Admin\PoliticasController;
use App\Http\Admin\PopupsController;
use App\Http\Admin\ProfesionalesController;
use App\Http\Admin\SedesController;
use App\Http\Admin\ServiciosController;
use App\Http\Web\ArchivoController;
use App\Http\Web\BuscarController;
use App\Http\Web\CampanasPublicoController;
use App\Http\Web\DirectorioPublicoController;
use App\Http\Web\FaqPublicoController;
use App\Http\Web\DocumentosPublicoController;
use App\Http\Web\EspecialidadesPublicoController;
use App\Http\Web\ConvocatoriasPublicoController;
use App\Http\Web\EncuestasPublicoController;
use App\Http\Web\EventosPublicoController;
use App\Http\Web\EstadisticasController;
use App\Http\Web\GaleriaController;
use App\Http\Web\InicioController;
use App\Http\Web\MapaSitioController;
use App\Http\Web\MuestraDisenoController;
use App\Http\Web\NoticiasPublicoController;
use App\Http\Web\PaginasController;
use App\Http\Web\PoliticasPublicoController;
use App\Http\Web\SedesPublicoController;
use App\Http\Web\SeoController;
use App\Http\Web\CuentasDescargaController;
use App\Http\Web\CuentasNuevaController;
use App\Http\Web\CuentasPortalController;
use App\Http\Web\ServiciosPublicoController;
use App\Http\Web\ServidoresPublicosController;
use App\Http\Web\TramitesPublicoController;
use App\Http\Web\TransparenciaPublicoController;
use App\Http\Web\UrgenciasContactoController;
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
    defaults: ['_controlador' => [BuscarController::class, 'buscar']],
    methods: ['GET'],
));

$rutas->add('noticias.listado', new Route(
    path: '/noticias',
    defaults: ['_controlador' => [NoticiasPublicoController::class, 'listado']],
    methods: ['GET'],
));

// Dos segmentos, así que no choca con el detalle, que es de uno solo.
$rutas->add('noticias.categoria', new Route(
    path: '/noticias/categoria/{categoria}',
    defaults: ['_controlador' => [NoticiasPublicoController::class, 'listado']],
    requirements: ['categoria' => '[a-z0-9\-]+'],
    methods: ['GET'],
));

$rutas->add('noticias.detalle', new Route(
    path: '/noticias/{slug}',
    defaults: ['_controlador' => [NoticiasPublicoController::class, 'detalle']],
    requirements: ['slug' => '[a-z0-9\-]+'],
    methods: ['GET'],
));

// --- Campañas y comunicados ---
//
// Las campañas se administraban desde el panel sin tener URL pública: la
// portada las anunciaba y el botón no llevaba a ninguna parte. El listado va
// partido por audiencia porque son dos públicos distintos, no un filtro:
// `/campanas` es la cara ciudadana y `/trabajadores-y-contratistas` la del
// talento humano (convocatorias, capacitación, bienestar), que es información
// pública y no una intranet.

$rutas->add('campanas', new Route(
    path: '/campanas',
    defaults: ['_controlador' => [CampanasPublicoController::class, 'listado']],
    methods: ['GET'],
));

$rutas->add('trabajadores', new Route(
    path: '/trabajadores-y-contratistas',
    defaults: ['_controlador' => [CampanasPublicoController::class, 'trabajadores']],
    methods: ['GET'],
));

$rutas->add('campanas.detalle', new Route(
    path: '/campanas/{slug}',
    defaults: ['_controlador' => [CampanasPublicoController::class, 'detalle']],
    requirements: ['slug' => '[a-z0-9\-]+'],
    methods: ['GET'],
));

// --- Directorio y servicios (Fase 2) ---

$listadoYDetalle = static function (string $nombre, string $prefijo, string $controlador) use ($rutas): void {
    $rutas->add($nombre, new Route(
        $prefijo,
        ['_controlador' => [$controlador, 'listado']],
        methods: ['GET'],
    ));
    $rutas->add($nombre . '.detalle', new Route(
        $prefijo . '/{slug}',
        ['_controlador' => [$controlador, 'detalle']],
        ['slug' => '[a-z0-9\-]+'],
        methods: ['GET'],
    ));
};

$listadoYDetalle('sedes', '/sedes', SedesPublicoController::class);
$listadoYDetalle('especialidades', '/especialidades', EspecialidadesPublicoController::class);
$listadoYDetalle('servicios', '/servicios', ServiciosPublicoController::class);
$listadoYDetalle('directorio', '/directorio', DirectorioPublicoController::class);

// --- Trámites y PQRSDF (Fase 5) ---

$rutas->add('atencion', new Route(
    path: '/atencion-ciudadania',
    defaults: ['_controlador' => [TramitesPublicoController::class, 'indiceAtencion']],
    methods: ['GET'],
));

$rutas->add('atencion.faq', new Route(
    path: '/atencion-ciudadania/preguntas-frecuentes',
    defaults: ['_controlador' => [FaqPublicoController::class, 'listado']],
    methods: ['GET'],
));

$rutas->add('tramites.listado', new Route(
    path: '/atencion-ciudadania/tramites',
    defaults: ['_controlador' => [TramitesPublicoController::class, 'listado']],
    methods: ['GET'],
));

$rutas->add('tramites.exito', new Route(
    path: '/atencion-ciudadania/tramites/{slug}/radicado',
    defaults: ['_controlador' => [TramitesPublicoController::class, 'radicadoExitoso']],
    requirements: ['slug' => '[a-z0-9\-]+'],
    methods: ['GET'],
));

$rutas->add('tramites.formulario', new Route(
    path: '/atencion-ciudadania/tramites/{slug}',
    defaults: ['_controlador' => [TramitesPublicoController::class, 'formulario']],
    requirements: ['slug' => '[a-z0-9\-]+'],
    methods: ['GET'],
));

$rutas->add('tramites.radicar', new Route(
    path: '/atencion-ciudadania/tramites/{slug}',
    defaults: ['_controlador' => [TramitesPublicoController::class, 'radicar']],
    requirements: ['slug' => '[a-z0-9\-]+'],
    methods: ['POST'],
));

$rutas->add('tramites.consultar', new Route(
    path: '/atencion-ciudadania/pqrsdf/consultar',
    defaults: ['_controlador' => [TramitesPublicoController::class, 'consultar']],
    methods: ['GET', 'POST'],
));

$rutas->add('tramites.pqrsdf', new Route(
    path: '/atencion-ciudadania/pqrsdf',
    defaults: ['_controlador' => [TramitesPublicoController::class, 'atajoPqrsdf']],
    methods: ['GET'],
));

$rutas->add('tramites.citas', new Route(
    path: '/citas',
    defaults: ['_controlador' => [TramitesPublicoController::class, 'atajoCitas']],
    methods: ['GET'],
));

// --- Políticas públicas (Fase 6): cada ruta fija su tipo ---

foreach ([
    'politica.tratamiento' => ['/politica-tratamiento-datos', 'tratamiento_datos'],
    'politica.privacidad' => ['/politica-privacidad-web', 'aviso_privacidad'],
    'politica.cookies' => ['/politica-de-cookies', 'cookies'],
    'politica.accesibilidad' => ['/accesibilidad', 'accesibilidad'],
] as $nombrePolitica => [$rutaPolitica, $tipoPolitica]) {
    $rutas->add($nombrePolitica, new Route(
        path: $rutaPolitica,
        defaults: [
            '_controlador' => [PoliticasPublicoController::class, 'mostrar'],
            '_tipo_politica' => $tipoPolitica,
        ],
        methods: ['GET'],
    ));
}

// --- Transparencia y datos abiertos (Fase 4) ---

$rutas->add('transparencia', new Route(
    path: '/transparencia',
    defaults: ['_controlador' => [TransparenciaPublicoController::class, 'index']],
    methods: ['GET'],
));

$rutas->add('transparencia.datos', new Route(
    path: '/transparencia/datos-abiertos',
    defaults: ['_controlador' => [TransparenciaPublicoController::class, 'datosAbiertos']],
    methods: ['GET'],
));

$rutas->add('transparencia.datos.descargar', new Route(
    path: '/transparencia/datos/{id}/descargar',
    defaults: ['_controlador' => [TransparenciaPublicoController::class, 'descargarRecurso']],
    requirements: ['id' => '\d+'],
    methods: ['GET'],
));

// Las sub-URL con nombre llegan con las redirecciones de la Fase 7;
// mientras tanto llevan al menú completo en lugar de dar 404.
$rutas->add('transparencia.subseccion', new Route(
    path: '/transparencia/{resto}',
    defaults: ['_controlador' => [TransparenciaPublicoController::class, 'subseccion']],
    requirements: ['resto' => '[a-z0-9\-/]+'],
    methods: ['GET'],
));

// --- Buscador documental (Fase 3) ---

$rutas->add('documentos.buscador', new Route(
    path: '/documentos',
    defaults: ['_controlador' => [DocumentosPublicoController::class, 'buscador']],
    methods: ['GET'],
));

$rutas->add('documentos.descargar', new Route(
    path: '/documentos/{id}/descargar',
    defaults: ['_controlador' => [DocumentosPublicoController::class, 'descargar']],
    requirements: ['id' => '\d+'],
    methods: ['GET'],
));

$rutas->add('documentos.ficha', new Route(
    path: '/documentos/{id}-{slug}',
    defaults: ['_controlador' => [DocumentosPublicoController::class, 'ficha']],
    requirements: ['id' => '\d+', 'slug' => '[a-z0-9\-]+'],
    methods: ['GET'],
));

// --- SEO técnico (Fase 7) ---

$rutas->add('seo.robots', new Route(
    path: '/robots.txt',
    defaults: ['_controlador' => [SeoController::class, 'robots']],
    methods: ['GET'],
));

$rutas->add('seo.sitemap', new Route(
    path: '/sitemap.xml',
    defaults: ['_controlador' => [SeoController::class, 'sitemap']],
    methods: ['GET'],
));

$rutas->add('urgencias', new Route(
    path: '/urgencias',
    defaults: ['_controlador' => [UrgenciasContactoController::class, 'urgencias']],
    methods: ['GET'],
));

$rutas->add('contacto', new Route(
    path: '/contacto',
    defaults: ['_controlador' => [UrgenciasContactoController::class, 'contacto']],
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

// --- Panel: contenido editorial (páginas y campañas, mismo flujo que noticias) ---

$crudEditorial = static function (string $nombre, string $prefijo, string $controlador) use ($rutas): void {
    $privada = static fn (string $metodo, ?string $permiso = null): array => [
        '_controlador' => [$controlador, $metodo],
        '_acceso' => 'privado',
        '_permiso' => $permiso ?? 'contenido.editar',
    ];

    $rutas->add($nombre, new Route($prefijo, $privada('listado', 'contenido.ver'), methods: ['GET']));
    $rutas->add($nombre . '.nueva', new Route($prefijo . '/nueva', $privada('formularioNueva'), methods: ['GET']));
    $rutas->add($nombre . '.guardar', new Route($prefijo, $privada('guardar'), methods: ['POST']));
    $rutas->add($nombre . '.editar', new Route($prefijo . '/{id}', $privada('formularioEditar'), ['id' => '\d+'], methods: ['GET']));
    $rutas->add($nombre . '.actualizar', new Route($prefijo . '/{id}', $privada('actualizar'), ['id' => '\d+'], methods: ['POST']));
    $rutas->add($nombre . '.estado', new Route($prefijo . '/{id}/estado', $privada('cambiarEstado'), ['id' => '\d+'], methods: ['POST']));
    $rutas->add($nombre . '.restaurar', new Route($prefijo . '/{id}/restaurar', $privada('restaurar'), ['id' => '\d+'], methods: ['POST']));
    $rutas->add($nombre . '.eliminar', new Route($prefijo . '/{id}/eliminar', $privada('eliminar'), ['id' => '\d+'], methods: ['POST']));
};

$crudEditorial('admin.paginas', '/admin/paginas', PaginasAdminController::class);
$crudEditorial('admin.campanas', '/admin/campanas', CampanasController::class);

// --- Panel: avisos ---

$rutas->add('admin.avisos', new Route(
    path: '/admin/avisos',
    defaults: [
        '_controlador' => [AvisosController::class, 'listado'],
        '_acceso' => 'privado',
        '_permiso' => 'contenido.ver',
    ],
    methods: ['GET'],
));

$rutas->add('admin.avisos.crear', new Route(
    path: '/admin/avisos',
    defaults: [
        '_controlador' => [AvisosController::class, 'guardar'],
        '_acceso' => 'privado',
        '_permiso' => 'contenido.editar',
    ],
    methods: ['POST'],
));

$rutas->add('admin.avisos.guardar', new Route(
    path: '/admin/avisos/{id}',
    defaults: [
        '_controlador' => [AvisosController::class, 'guardar'],
        '_acceso' => 'privado',
        '_permiso' => 'contenido.editar',
    ],
    requirements: ['id' => '\d+'],
    methods: ['POST'],
));

// --- Panel: ventanas emergentes ---

$rutas->add('admin.popups', new Route(
    path: '/admin/popups',
    defaults: [
        '_controlador' => [PopupsController::class, 'listado'],
        '_acceso' => 'privado',
        '_permiso' => 'contenido.ver',
    ],
    methods: ['GET'],
));

$rutas->add('admin.popups.crear', new Route(
    path: '/admin/popups',
    defaults: [
        '_controlador' => [PopupsController::class, 'guardar'],
        '_acceso' => 'privado',
        '_permiso' => 'contenido.editar',
    ],
    methods: ['POST'],
));

$rutas->add('admin.popups.guardar', new Route(
    path: '/admin/popups/{id}',
    defaults: [
        '_controlador' => [PopupsController::class, 'guardar'],
        '_acceso' => 'privado',
        '_permiso' => 'contenido.editar',
    ],
    requirements: ['id' => '\d+'],
    methods: ['POST'],
));

$rutas->add('admin.popups.eliminar', new Route(
    path: '/admin/popups/{id}/eliminar',
    defaults: [
        '_controlador' => [PopupsController::class, 'eliminar'],
        '_acceso' => 'privado',
        // Borrar no es editar: pide el permiso de publicación, el mismo que
        // hace falta para retirar contenido del sitio.
        '_permiso' => 'contenido.publicar',
    ],
    requirements: ['id' => '\d+'],
    methods: ['POST'],
));

// --- Panel: preguntas frecuentes ---

$rutas->add('admin.faq', new Route(
    path: '/admin/faq',
    defaults: [
        '_controlador' => [FaqController::class, 'listado'],
        '_acceso' => 'privado',
        '_permiso' => 'contenido.ver',
    ],
    methods: ['GET'],
));

$rutas->add('admin.faq.crear', new Route(
    path: '/admin/faq',
    defaults: [
        '_controlador' => [FaqController::class, 'guardar'],
        '_acceso' => 'privado',
        '_permiso' => 'contenido.editar',
    ],
    methods: ['POST'],
));

$rutas->add('admin.faq.guardar', new Route(
    path: '/admin/faq/{id}',
    defaults: [
        '_controlador' => [FaqController::class, 'guardar'],
        '_acceso' => 'privado',
        '_permiso' => 'contenido.editar',
    ],
    requirements: ['id' => '\d+'],
    methods: ['POST'],
));

$rutas->add('admin.faq.eliminar', new Route(
    path: '/admin/faq/{id}/eliminar',
    defaults: [
        '_controlador' => [FaqController::class, 'eliminar'],
        '_acceso' => 'privado',
        '_permiso' => 'contenido.editar',
    ],
    requirements: ['id' => '\d+'],
    methods: ['POST'],
));

// --- Panel: categorías ---

$rutas->add('admin.categorias', new Route(
    path: '/admin/categorias',
    defaults: [
        '_controlador' => [CategoriasController::class, 'listado'],
        '_acceso' => 'privado',
        '_permiso' => 'contenido.ver',
    ],
    methods: ['GET'],
));

$rutas->add('admin.categorias.crear', new Route(
    path: '/admin/categorias',
    defaults: [
        '_controlador' => [CategoriasController::class, 'guardar'],
        '_acceso' => 'privado',
        '_permiso' => 'contenido.editar',
    ],
    methods: ['POST'],
));

$rutas->add('admin.categorias.guardar', new Route(
    path: '/admin/categorias/{id}',
    defaults: [
        '_controlador' => [CategoriasController::class, 'guardar'],
        '_acceso' => 'privado',
        '_permiso' => 'contenido.editar',
    ],
    requirements: ['id' => '\d+'],
    methods: ['POST'],
));

// --- Panel: directorio (sedes, especialidades, servicios, profesionales) ---

$crudDirectorio = static function (string $nombre, string $prefijo, string $controlador) use ($rutas): void {
    $privada = static fn (string $metodo, string $permiso): array => [
        '_controlador' => [$controlador, $metodo],
        '_acceso' => 'privado',
        '_permiso' => $permiso,
    ];

    $rutas->add($nombre, new Route($prefijo, $privada('listado', 'directorio.ver'), methods: ['GET']));
    $rutas->add($nombre . '.crear', new Route($prefijo, $privada('guardar', 'directorio.editar'), methods: ['POST']));
    $rutas->add($nombre . '.guardar', new Route($prefijo . '/{id}', $privada('guardar', 'directorio.editar'), ['id' => '\d+'], methods: ['POST']));
};

$crudDirectorio('admin.sedes', '/admin/sedes', SedesController::class);
$crudDirectorio('admin.especialidades', '/admin/especialidades', EspecialidadesController::class);
$crudDirectorio('admin.servicios', '/admin/servicios', ServiciosController::class);
$crudDirectorio('admin.profesionales', '/admin/profesionales', ProfesionalesController::class);

// --- Panel: gestión documental ---

$permisoDoc = static fn (string $metodo, string $permiso = 'documentos.editar'): array => [
    '_controlador' => [DocumentosController::class, $metodo],
    '_acceso' => 'privado',
    '_permiso' => $permiso,
];

$rutas->add('admin.documentos', new Route('/admin/documentos', $permisoDoc('listado', 'documentos.ver'), methods: ['GET']));
$rutas->add('admin.documentos.nuevo', new Route('/admin/documentos/nuevo', $permisoDoc('formularioNueva'), methods: ['GET']));
$rutas->add('admin.documentos.guardar', new Route('/admin/documentos', $permisoDoc('guardar'), methods: ['POST']));
$rutas->add('admin.documentos.editar', new Route('/admin/documentos/{id}', $permisoDoc('formularioEditar'), ['id' => '\d+'], methods: ['GET']));
$rutas->add('admin.documentos.actualizar', new Route('/admin/documentos/{id}', $permisoDoc('actualizar'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.documentos.publicar', new Route('/admin/documentos/{id}/publicar', $permisoDoc('publicar', 'documentos.publicar'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.documentos.estado', new Route('/admin/documentos/{id}/estado', $permisoDoc('cambiarEstado', 'documentos.publicar'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.documentos.version', new Route('/admin/documentos/{id}/version', $permisoDoc('nuevaVersion'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.documentos.eliminar', new Route('/admin/documentos/{id}/eliminar', $permisoDoc('eliminar'), ['id' => '\d+'], methods: ['POST']));

// --- Panel: bandeja de trámites ---

$permisoTram = static fn (string $metodo): array => [
    '_controlador' => [BandejaTramitesController::class, $metodo],
    '_acceso' => 'privado',
    '_permiso' => 'tramites.gestionar',
];

$rutas->add('admin.tramites', new Route('/admin/tramites', $permisoTram('bandeja'), methods: ['GET']));
$rutas->add('admin.tramites.detalle', new Route('/admin/tramites/{id}', $permisoTram('detalle'), ['id' => '\d+'], methods: ['GET']));
$rutas->add('admin.tramites.estado', new Route('/admin/tramites/{id}/estado', $permisoTram('cambiarEstado'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.tramites.asignar', new Route('/admin/tramites/{id}/asignar', $permisoTram('asignar'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.tramites.nota', new Route('/admin/tramites/{id}/nota', $permisoTram('agregarNota'), ['id' => '\d+'], methods: ['POST']));

// --- Panel: tipos de trámite configurables ---

$permisoTipos = static fn (string $metodo): array => [
    '_controlador' => [TiposTramiteController::class, $metodo],
    '_acceso' => 'privado',
    '_permiso' => 'tramites.configurar',
];

$rutas->add('admin.tipostramite', new Route('/admin/tipos-tramite', $permisoTipos('listado'), methods: ['GET']));
$rutas->add('admin.tipostramite.crear', new Route('/admin/tipos-tramite', $permisoTipos('guardar'), methods: ['POST']));
$rutas->add('admin.tipostramite.guardar', new Route('/admin/tipos-tramite/{id}', $permisoTipos('guardar'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.tipostramite.campo.crear', new Route('/admin/tipos-tramite/{id}/campos', $permisoTipos('crearCampo'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.tipostramite.campo.eliminar', new Route('/admin/tipos-tramite/campos/{id}/eliminar', $permisoTipos('eliminarCampo'), ['id' => '\d+'], methods: ['POST']));

// --- Panel: cuentas de cobro (administración, fase 1 del módulo) ---

$permisoCuentas = static fn (string $controlador, string $metodo): array => [
    '_controlador' => [$controlador, $metodo],
    '_acceso' => 'privado',
    '_permiso' => 'cuentas.administrar',
];

$rutas->add('admin.cc.contratos', new Route('/admin/cuentas-cobro/contratos', $permisoCuentas(CuentasContratosController::class, 'listado'), methods: ['GET']));
$rutas->add('admin.cc.contratos.nuevo', new Route('/admin/cuentas-cobro/contratos/nuevo', $permisoCuentas(CuentasContratosController::class, 'formulario'), methods: ['GET']));
$rutas->add('admin.cc.contratos.crear', new Route('/admin/cuentas-cobro/contratos', $permisoCuentas(CuentasContratosController::class, 'guardar'), methods: ['POST']));
$rutas->add('admin.cc.contratos.editar', new Route('/admin/cuentas-cobro/contratos/{id}/editar', $permisoCuentas(CuentasContratosController::class, 'formulario'), ['id' => '\d+'], methods: ['GET']));
$rutas->add('admin.cc.contratos.ficha', new Route('/admin/cuentas-cobro/contratos/{id}', $permisoCuentas(CuentasContratosController::class, 'ficha'), ['id' => '\d+'], methods: ['GET']));
$rutas->add('admin.cc.contratos.guardar', new Route('/admin/cuentas-cobro/contratos/{id}', $permisoCuentas(CuentasContratosController::class, 'guardar'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.cc.contratos.activar', new Route('/admin/cuentas-cobro/contratos/{id}/activar', $permisoCuentas(CuentasContratosController::class, 'activar'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.cc.contratos.acta', new Route('/admin/cuentas-cobro/contratos/{id}/acta', $permisoCuentas(CuentasContratosController::class, 'registrarActa'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.cc.contratos.imputacion', new Route('/admin/cuentas-cobro/contratos/{id}/imputaciones', $permisoCuentas(CuentasContratosController::class, 'agregarImputacion'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.cc.contratos.anular', new Route('/admin/cuentas-cobro/contratos/{id}/anular', $permisoCuentas(CuentasContratosController::class, 'anular'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.cc.contratistas', new Route('/admin/cuentas-cobro/contratistas', $permisoCuentas(CuentasContratistasController::class, 'listado'), methods: ['GET']));
$rutas->add('admin.cc.contratistas.crear', new Route('/admin/cuentas-cobro/contratistas', $permisoCuentas(CuentasContratistasController::class, 'guardar'), methods: ['POST']));
$rutas->add('admin.cc.contratistas.guardar', new Route('/admin/cuentas-cobro/contratistas/{id}', $permisoCuentas(CuentasContratistasController::class, 'guardar'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.cc.contratistas.activo', new Route('/admin/cuentas-cobro/contratistas/{id}/activo', $permisoCuentas(CuentasContratistasController::class, 'cambiarActivo'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.cc.supervisores', new Route('/admin/cuentas-cobro/supervisores', $permisoCuentas(CuentasSupervisoresController::class, 'listado'), methods: ['GET']));
$rutas->add('admin.cc.supervisores.crear', new Route('/admin/cuentas-cobro/supervisores', $permisoCuentas(CuentasSupervisoresController::class, 'guardar'), methods: ['POST']));
$rutas->add('admin.cc.supervisores.guardar', new Route('/admin/cuentas-cobro/supervisores/{id}', $permisoCuentas(CuentasSupervisoresController::class, 'guardar'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.cc.parametros', new Route('/admin/cuentas-cobro/parametros', $permisoCuentas(CuentasParametrosController::class, 'index'), methods: ['GET']));
$rutas->add('admin.cc.parametros.anio', new Route('/admin/cuentas-cobro/parametros/anio', $permisoCuentas(CuentasParametrosController::class, 'guardarAnio'), methods: ['POST']));
$rutas->add('admin.cc.parametros.plantilla', new Route('/admin/cuentas-cobro/parametros/plantillas/{clave}', $permisoCuentas(CuentasParametrosController::class, 'guardarPlantilla'), ['clave' => '[A-Z_]+'], methods: ['POST']));

$rutas->add('admin.cc.contratos.novedad', new Route('/admin/cuentas-cobro/contratos/{id}/novedades', $permisoCuentas(CuentasContratosController::class, 'registrarNovedad'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.cc.cuentas.anular', new Route('/admin/cuentas-cobro/cuentas/{id}/anular', $permisoCuentas(CuentasContratosController::class, 'anularCuenta'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.cc.reportes', new Route('/admin/cuentas-cobro/reportes', $permisoCuentas(CuentasReportesController::class, 'index'), methods: ['GET']));

// --- Panel: bandeja de supervisión de cuentas de cobro (fase 3) ---

$permisoSupervision = static fn (string $metodo): array => [
    '_controlador' => [CuentasSupervisionController::class, $metodo],
    '_acceso' => 'privado',
    '_permiso' => 'cuentas.supervisar',
];

$rutas->add('admin.cc.bandeja', new Route('/admin/cuentas-cobro/bandeja', $permisoSupervision('bandeja'), methods: ['GET']));
$rutas->add('admin.cc.bandeja.firma', new Route('/admin/cuentas-cobro/bandeja/firma', $permisoSupervision('guardarMiFirma'), methods: ['POST']));
$rutas->add('admin.cc.bandeja.revision', new Route('/admin/cuentas-cobro/bandeja/{id}', $permisoSupervision('revision'), ['id' => '\d+'], methods: ['GET']));
$rutas->add('admin.cc.bandeja.previa', new Route('/admin/cuentas-cobro/bandeja/{id}/vista-previa', $permisoSupervision('vistaPrevia'), ['id' => '\d+'], methods: ['GET']));
$rutas->add('admin.cc.bandeja.aprobar', new Route('/admin/cuentas-cobro/bandeja/{id}/aprobar', $permisoSupervision('aprobar'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.cc.bandeja.devolver', new Route('/admin/cuentas-cobro/bandeja/{id}/devolver', $permisoSupervision('devolver'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.cc.bandeja.entregada', new Route('/admin/cuentas-cobro/bandeja/{id}/entregada', $permisoSupervision('marcarEntregada'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.cc.bandeja.descargar', new Route('/admin/cuentas-cobro/bandeja/{id}/descargar/{documento}', $permisoSupervision('descargar'), ['id' => '\d+', 'documento' => 'cuenta|informe|supervision|paquete'], methods: ['GET']));
$rutas->add('admin.cc.adjunto', new Route('/admin/cuentas-cobro/adjuntos/{id}', $permisoSupervision('adjunto'), ['id' => '\d+'], methods: ['GET']));
$rutas->add('admin.cc.miscontratos', new Route('/admin/cuentas-cobro/mis-contratos', $permisoSupervision('misContratos'), methods: ['GET']));
$rutas->add('admin.cc.miscontratos.acta', new Route('/admin/cuentas-cobro/mis-contratos/{id}/acta', $permisoSupervision('registrarActa'), ['id' => '\d+'], methods: ['POST']));

// --- Panel: políticas ---

$rutas->add('admin.politicas', new Route('/admin/politicas', [
    '_controlador' => [PoliticasController::class, 'listado'],
    '_acceso' => 'privado',
    '_permiso' => 'cumplimiento.politicas',
], methods: ['GET']));
$rutas->add('admin.politicas.publicar', new Route('/admin/politicas', [
    '_controlador' => [PoliticasController::class, 'publicarVersion'],
    '_acceso' => 'privado',
    '_permiso' => 'cumplimiento.politicas',
], methods: ['POST']));

// --- Panel: transparencia ---

$permisoTransp = static fn (string $metodo): array => [
    '_controlador' => [TransparenciaController::class, $metodo],
    '_acceso' => 'privado',
    '_permiso' => 'transparencia.editar',
];

$rutas->add('admin.transparencia', new Route('/admin/transparencia', [
    '_controlador' => [TransparenciaController::class, 'listado'],
    '_acceso' => 'privado',
    '_permiso' => 'transparencia.ver',
], methods: ['GET']));
$rutas->add('admin.transparencia.seccion', new Route('/admin/transparencia/secciones/{id}', $permisoTransp('actualizarSeccion'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.transparencia.item.crear', new Route('/admin/transparencia/secciones/{id}/items', $permisoTransp('crearItem'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.transparencia.item.eliminar', new Route('/admin/transparencia/items/{id}/eliminar', $permisoTransp('eliminarItem'), ['id' => '\d+'], methods: ['POST']));

// --- Panel: datos abiertos ---

$permisoDatos = static fn (string $metodo): array => [
    '_controlador' => [DatasetsController::class, $metodo],
    '_acceso' => 'privado',
    '_permiso' => 'transparencia.editar',
];

$rutas->add('admin.datasets', new Route('/admin/datasets', [
    '_controlador' => [DatasetsController::class, 'listado'],
    '_acceso' => 'privado',
    '_permiso' => 'transparencia.ver',
], methods: ['GET']));
$rutas->add('admin.datasets.crear', new Route('/admin/datasets', $permisoDatos('guardar'), methods: ['POST']));
$rutas->add('admin.datasets.guardar', new Route('/admin/datasets/{id}', $permisoDatos('guardar'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.datasets.recursos.crear', new Route('/admin/datasets/{id}/recursos', $permisoDatos('agregarRecurso'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.datasets.recursos.eliminar', new Route('/admin/datasets/recursos/{id}/eliminar', $permisoDatos('eliminarRecurso'), ['id' => '\d+'], methods: ['POST']));

// --- Panel: asistente de IA ---
// Configurarlo exige permiso de sistema; pedir sugerencias solo exige estar
// autenticado: aplicarlas sigue limitado por los permisos de cada formulario.

$rutas->add('admin.ia', new Route('/admin/ia', [
    '_controlador' => [IaController::class, 'configuracion'],
    '_acceso' => 'privado',
    '_permiso' => 'sistema.ia',
], methods: ['GET']));

$rutas->add('admin.ia.guardar', new Route('/admin/ia', [
    '_controlador' => [IaController::class, 'guardarConfiguracion'],
    '_acceso' => 'privado',
    '_permiso' => 'sistema.ia',
], methods: ['POST']));

$rutas->add('admin.ia.sugerir', new Route('/admin/ia/sugerir', [
    '_controlador' => [IaController::class, 'sugerir'],
    '_acceso' => 'privado',
], methods: ['POST']));

// --- Panel: dependencias ---

$rutas->add('admin.dependencias', new Route(
    path: '/admin/dependencias',
    defaults: [
        '_controlador' => [DependenciasController::class, 'listado'],
        '_acceso' => 'privado',
        '_permiso' => 'sistema.dependencias',
    ],
    methods: ['GET'],
));

$rutas->add('admin.dependencias.crear', new Route(
    path: '/admin/dependencias',
    defaults: [
        '_controlador' => [DependenciasController::class, 'guardar'],
        '_acceso' => 'privado',
        '_permiso' => 'sistema.dependencias',
    ],
    methods: ['POST'],
));

$rutas->add('admin.dependencias.guardar', new Route(
    path: '/admin/dependencias/{id}',
    defaults: [
        '_controlador' => [DependenciasController::class, 'guardar'],
        '_acceso' => 'privado',
        '_permiso' => 'sistema.dependencias',
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

// Borrar de la biblioteca. El controlador se niega si la imagen está en uso:
// ocho de las claves foráneas que apuntan a `archivos` son ON DELETE SET NULL
// y dejarían en blanco contenido publicado sin decir nada.
$rutas->add('admin.medios.eliminar', new Route(
    path: '/admin/medios/{id}/eliminar',
    defaults: [
        '_controlador' => [MediosController::class, 'eliminar'],
        '_acceso' => 'privado',
        '_permiso' => 'contenido.editar',
    ],
    requirements: ['id' => '\d+'],
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

// --- Convocatorias, eventos y encuestas (Fase 9, bloques B4, B6 y D2) ---

$rutas->add('convocatorias', new Route(
    path: '/convocatorias',
    defaults: ['_controlador' => [ConvocatoriasPublicoController::class, 'listado']],
    methods: ['GET'],
));

$rutas->add('convocatorias.detalle', new Route(
    path: '/convocatorias/{slug}',
    defaults: ['_controlador' => [ConvocatoriasPublicoController::class, 'detalle']],
    requirements: ['slug' => '[a-z0-9\-]+'],
    methods: ['GET'],
));

$rutas->add('eventos', new Route(
    path: '/eventos',
    defaults: ['_controlador' => [EventosPublicoController::class, 'agenda']],
    methods: ['GET'],
));

$rutas->add('eventos.detalle', new Route(
    path: '/eventos/{slug}',
    defaults: ['_controlador' => [EventosPublicoController::class, 'detalle']],
    requirements: ['slug' => '[a-z0-9\-]+'],
    methods: ['GET'],
));

$rutas->add('encuestas', new Route(
    path: '/encuestas',
    defaults: ['_controlador' => [EncuestasPublicoController::class, 'listado']],
    methods: ['GET'],
));

// Antes que `/encuestas/{slug}`: si no, «resultados» se leería como un slug.
$rutas->add('encuestas.resultados', new Route(
    path: '/encuestas/{slug}/resultados',
    defaults: ['_controlador' => [EncuestasPublicoController::class, 'resultados']],
    requirements: ['slug' => '[a-z0-9\-]+'],
    methods: ['GET'],
));

// Antes que `/encuestas/{slug}`: si no, «gracias» se leería como un slug.
$rutas->add('encuestas.gracias', new Route(
    path: '/encuestas/{slug}/gracias',
    defaults: ['_controlador' => [EncuestasPublicoController::class, 'gracias']],
    requirements: ['slug' => '[a-z0-9\-]+'],
    methods: ['GET'],
));

$rutas->add('encuestas.formulario', new Route(
    path: '/encuestas/{slug}',
    defaults: ['_controlador' => [EncuestasPublicoController::class, 'formulario']],
    requirements: ['slug' => '[a-z0-9\-]+'],
    methods: ['GET'],
));

$rutas->add('encuestas.responder', new Route(
    path: '/encuestas/{slug}',
    defaults: ['_controlador' => [EncuestasPublicoController::class, 'responder']],
    requirements: ['slug' => '[a-z0-9\-]+'],
    methods: ['POST'],
));

$rutas->add('servidores-publicos', new Route(
    path: '/servidores-publicos',
    defaults: ['_controlador' => [ServidoresPublicosController::class, 'listado']],
    methods: ['GET'],
));

// --- Panel: convocatorias, eventos, encuestas y servidores públicos (Fase 9) ---
// Permisos propios y no `*`: el funcionario que publica una convocatoria no
// tiene por qué poder leer resultados de encuestas ni tocar salarios.

$panel = static fn (string $controlador, string $metodo, string $permiso): array => [
    '_controlador' => [$controlador, $metodo],
    '_acceso' => 'privado',
    '_permiso' => $permiso,
];

$rutas->add('admin.convocatorias', new Route('/admin/convocatorias', $panel(ConvocatoriasAdminController::class, 'listado', 'convocatorias.ver'), methods: ['GET']));
$rutas->add('admin.convocatorias.crear', new Route('/admin/convocatorias', $panel(ConvocatoriasAdminController::class, 'guardar', 'convocatorias.editar'), methods: ['POST']));
$rutas->add('admin.convocatorias.guardar', new Route('/admin/convocatorias/{id}', $panel(ConvocatoriasAdminController::class, 'guardar', 'convocatorias.editar'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.convocatorias.eliminar', new Route('/admin/convocatorias/{id}/eliminar', $panel(ConvocatoriasAdminController::class, 'eliminar', 'convocatorias.editar'), ['id' => '\d+'], methods: ['POST']));

// El héroe de la portada: varias diapositivas con imagen, orden y vigencia.
// Va con los permisos de contenido y no con uno propio: es contenido de la
// portada, y quien edita campañas y noticias es quien lo edita.
$rutas->add('admin.heroes', new Route('/admin/heroes', $panel(HeroesController::class, 'listado', 'contenido.ver'), methods: ['GET']));
$rutas->add('admin.heroes.crear', new Route('/admin/heroes', $panel(HeroesController::class, 'guardar', 'contenido.editar'), methods: ['POST']));
$rutas->add('admin.heroes.guardar', new Route('/admin/heroes/{id}', $panel(HeroesController::class, 'guardar', 'contenido.editar'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.heroes.eliminar', new Route('/admin/heroes/{id}/eliminar', $panel(HeroesController::class, 'eliminar', 'contenido.editar'), ['id' => '\d+'], methods: ['POST']));

$rutas->add('admin.eventos', new Route('/admin/eventos', $panel(EventosAdminController::class, 'listado', 'eventos.ver'), methods: ['GET']));
$rutas->add('admin.eventos.crear', new Route('/admin/eventos', $panel(EventosAdminController::class, 'guardar', 'eventos.editar'), methods: ['POST']));
$rutas->add('admin.eventos.guardar', new Route('/admin/eventos/{id}', $panel(EventosAdminController::class, 'guardar', 'eventos.editar'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.eventos.eliminar', new Route('/admin/eventos/{id}/eliminar', $panel(EventosAdminController::class, 'eliminar', 'eventos.editar'), ['id' => '\d+'], methods: ['POST']));

$rutas->add('admin.encuestas', new Route('/admin/encuestas', $panel(EncuestasAdminController::class, 'listado', 'encuestas.ver'), methods: ['GET']));
$rutas->add('admin.encuestas.crear', new Route('/admin/encuestas', $panel(EncuestasAdminController::class, 'guardar', 'encuestas.editar'), methods: ['POST']));
// Antes que `/admin/encuestas/{id}`: si no, «preguntas» se leería como id.
$rutas->add('admin.encuestas.pregunta.eliminar', new Route('/admin/encuestas/preguntas/{id}/eliminar', $panel(EncuestasAdminController::class, 'eliminarPregunta', 'encuestas.editar'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.encuestas.pregunta.crear', new Route('/admin/encuestas/{id}/preguntas', $panel(EncuestasAdminController::class, 'crearPregunta', 'encuestas.editar'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.encuestas.detalle', new Route('/admin/encuestas/{id}', $panel(EncuestasAdminController::class, 'detalle', 'encuestas.ver'), ['id' => '\d+'], methods: ['GET']));
$rutas->add('admin.encuestas.guardar', new Route('/admin/encuestas/{id}', $panel(EncuestasAdminController::class, 'guardar', 'encuestas.editar'), ['id' => '\d+'], methods: ['POST']));

$rutas->add('admin.indicadores', new Route('/admin/indicadores', $panel(IndicadoresAdminController::class, 'listado', 'indicadores.ver'), methods: ['GET']));
$rutas->add('admin.indicadores.crear', new Route('/admin/indicadores', $panel(IndicadoresAdminController::class, 'guardar', 'indicadores.editar'), methods: ['POST']));
$rutas->add('admin.indicadores.guardar', new Route('/admin/indicadores/{id}', $panel(IndicadoresAdminController::class, 'guardar', 'indicadores.editar'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.indicadores.eliminar', new Route('/admin/indicadores/{id}/eliminar', $panel(IndicadoresAdminController::class, 'eliminar', 'indicadores.editar'), ['id' => '\d+'], methods: ['POST']));

$rutas->add('admin.servidores', new Route('/admin/servidores-publicos', $panel(ServidoresPublicosAdminController::class, 'listado', 'directorio.ver'), methods: ['GET']));
$rutas->add('admin.servidores.crear', new Route('/admin/servidores-publicos', $panel(ServidoresPublicosAdminController::class, 'guardar', 'directorio.editar'), methods: ['POST']));
$rutas->add('admin.servidores.guardar', new Route('/admin/servidores-publicos/{id}', $panel(ServidoresPublicosAdminController::class, 'guardar', 'directorio.editar'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.servidores.eliminar', new Route('/admin/servidores-publicos/{id}/eliminar', $panel(ServidoresPublicosAdminController::class, 'eliminar', 'directorio.editar'), ['id' => '\d+'], methods: ['POST']));

// --- Panel: sistema (cuentas, permisos, ajustes y auditoría) ---
// Lo que hasta la Fase 9 solo existía por consola o en la base de datos. Los
// permisos son propios y separados: quien administra usuarios no tiene por qué
// poder reescribir los datos de contacto de la entidad, ni al revés.

$sistema = static fn (string $controlador, string $metodo, string $permiso): array => [
    '_controlador' => [$controlador, $metodo],
    '_acceso' => 'privado',
    '_permiso' => $permiso,
];

$rutas->add('admin.usuarios', new Route('/admin/usuarios', $sistema(UsuariosController::class, 'listado', 'sistema.usuarios'), methods: ['GET']));
$rutas->add('admin.usuarios.crear', new Route('/admin/usuarios', $sistema(UsuariosController::class, 'crear', 'sistema.usuarios'), methods: ['POST']));
// Antes que `/admin/usuarios/{id}`: si no, «restablecer» nunca se alcanzaría.
$rutas->add('admin.usuarios.restablecer', new Route('/admin/usuarios/{id}/restablecer', $sistema(UsuariosController::class, 'restablecer', 'sistema.usuarios'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.usuarios.desbloquear', new Route('/admin/usuarios/{id}/desbloquear', $sistema(UsuariosController::class, 'desbloquear', 'sistema.usuarios'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.usuarios.eliminar', new Route('/admin/usuarios/{id}/eliminar', $sistema(UsuariosController::class, 'eliminar', 'sistema.usuarios'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.usuarios.restaurar', new Route('/admin/usuarios/{id}/restaurar', $sistema(UsuariosController::class, 'restaurar', 'sistema.usuarios'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.usuarios.actualizar', new Route('/admin/usuarios/{id}', $sistema(UsuariosController::class, 'actualizar', 'sistema.usuarios'), ['id' => '\d+'], methods: ['POST']));

$rutas->add('admin.roles', new Route('/admin/roles', $sistema(RolesController::class, 'listado', 'sistema.roles'), methods: ['GET']));
$rutas->add('admin.roles.crear', new Route('/admin/roles', $sistema(RolesController::class, 'crear', 'sistema.roles'), methods: ['POST']));
$rutas->add('admin.roles.eliminar', new Route('/admin/roles/{id}/eliminar', $sistema(RolesController::class, 'eliminar', 'sistema.roles'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.roles.actualizar', new Route('/admin/roles/{id}', $sistema(RolesController::class, 'actualizar', 'sistema.roles'), ['id' => '\d+'], methods: ['POST']));

$rutas->add('admin.configuracion', new Route('/admin/configuracion', $sistema(ConfiguracionController::class, 'formulario', 'sistema.configuracion'), methods: ['GET']));
$rutas->add('admin.configuracion.guardar', new Route('/admin/configuracion', $sistema(ConfiguracionController::class, 'guardar', 'sistema.configuracion'), methods: ['POST']));

$rutas->add('admin.auditoria', new Route('/admin/auditoria', $sistema(AuditoriaAdminController::class, 'listado', 'sistema.auditoria'), methods: ['GET']));

$rutas->add('admin.redirecciones', new Route('/admin/redirecciones', $sistema(RedireccionesController::class, 'listado', 'sistema.redirecciones'), methods: ['GET']));
$rutas->add('admin.redirecciones.crear', new Route('/admin/redirecciones', $sistema(RedireccionesController::class, 'guardar', 'sistema.redirecciones'), methods: ['POST']));
$rutas->add('admin.redirecciones.eliminar', new Route('/admin/redirecciones/{id}/eliminar', $sistema(RedireccionesController::class, 'eliminar', 'sistema.redirecciones'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('admin.redirecciones.guardar', new Route('/admin/redirecciones/{id}', $sistema(RedireccionesController::class, 'guardar', 'sistema.redirecciones'), ['id' => '\d+'], methods: ['POST']));

// La cuenta propia no lleva permiso: todo el que entra al panel tiene derecho a
// ver sus datos y a cambiar su contraseña, incluso con el rol más limitado.
// Condicionarlo a un permiso dejaría cuentas que solo un administrador podría
// rescatar.
$propia = static fn (string $metodo): array => [
    '_controlador' => [PerfilController::class, $metodo],
    '_acceso' => 'privado',
];

$rutas->add('admin.perfil', new Route('/admin/perfil', $propia('index'), methods: ['GET']));
$rutas->add('admin.perfil.guardar', new Route('/admin/perfil', $propia('guardar'), methods: ['POST']));
$rutas->add('admin.perfil.password', new Route('/admin/perfil/password', $propia('formularioPassword'), methods: ['GET']));
$rutas->add('admin.perfil.password.guardar', new Route('/admin/perfil/password', $propia('cambiarPassword'), methods: ['POST']));

// --- Información estadística ---
// Ruta fija y no página: el contenido son cifras de la tabla `indicadores`,
// que el hospital carga desde el panel.
$rutas->add('estadisticas', new Route(
    path: '/estadisticas',
    defaults: ['_controlador' => [EstadisticasController::class, 'mostrar']],
    methods: ['GET'],
));

$rutas->add('galeria', new Route(
    path: '/galeria',
    defaults: ['_controlador' => [GaleriaController::class, 'mostrar']],
    methods: ['GET'],
));

// --- Portal de cuentas de cobro (fase 2 del módulo) ---
// Rutas públicas: la autenticación del contratista (cédula + contrato +
// código) la hace el propio portal, no el candado del panel.

$rutaCuentas = static fn (string $controlador, string $metodo): array => [
    '_controlador' => [$controlador, $metodo],
];

$rutas->add('cuentas.ingreso', new Route('/cuentas/ingreso', $rutaCuentas(CuentasPortalController::class, 'ingreso'), methods: ['GET']));
$rutas->add('cuentas.ingreso.codigo', new Route('/cuentas/ingreso', $rutaCuentas(CuentasPortalController::class, 'solicitarCodigo'), methods: ['POST']));
$rutas->add('cuentas.codigo', new Route('/cuentas/codigo', $rutaCuentas(CuentasPortalController::class, 'verificarCodigo'), methods: ['POST']));
$rutas->add('cuentas.salir', new Route('/cuentas/salir', $rutaCuentas(CuentasPortalController::class, 'salir'), methods: ['POST']));
$rutas->add('cuentas.inicio', new Route('/cuentas/inicio', $rutaCuentas(CuentasPortalController::class, 'inicio'), methods: ['GET']));
$rutas->add('cuentas.misdatos', new Route('/cuentas/mis-datos', $rutaCuentas(CuentasPortalController::class, 'misDatos'), methods: ['GET']));
$rutas->add('cuentas.misdatos.guardar', new Route('/cuentas/mis-datos/{paso}', $rutaCuentas(CuentasPortalController::class, 'guardarMisDatos'), ['paso' => '[1-3]'], methods: ['POST']));
$rutas->add('cuentas.nueva1', new Route('/cuentas/nueva/1', $rutaCuentas(CuentasNuevaController::class, 'paso1'), methods: ['GET']));
$rutas->add('cuentas.nueva1.confirmar', new Route('/cuentas/nueva/1', $rutaCuentas(CuentasNuevaController::class, 'confirmarPaso1'), methods: ['POST']));
$rutas->add('cuentas.nueva.reportar', new Route('/cuentas/nueva/reportar', $rutaCuentas(CuentasNuevaController::class, 'reportarProblema'), methods: ['POST']));
$rutas->add('cuentas.nueva2', new Route('/cuentas/nueva/2', $rutaCuentas(CuentasNuevaController::class, 'paso2'), methods: ['GET']));
$rutas->add('cuentas.nueva2.guardar', new Route('/cuentas/nueva/2', $rutaCuentas(CuentasNuevaController::class, 'guardarPaso2'), methods: ['POST']));
$rutas->add('cuentas.autoguardar', new Route('/cuentas/nueva/autoguardar', $rutaCuentas(CuentasNuevaController::class, 'autoguardar'), methods: ['POST']));
$rutas->add('cuentas.nueva3', new Route('/cuentas/nueva/3', $rutaCuentas(CuentasNuevaController::class, 'paso3'), methods: ['GET']));
$rutas->add('cuentas.nueva3.guardar', new Route('/cuentas/nueva/3', $rutaCuentas(CuentasNuevaController::class, 'guardarPaso3'), methods: ['POST']));
$rutas->add('cuentas.nueva4', new Route('/cuentas/nueva/4', $rutaCuentas(CuentasNuevaController::class, 'paso4'), methods: ['GET']));
$rutas->add('cuentas.vistaprevia', new Route('/cuentas/nueva/vista-previa/{documento}', $rutaCuentas(CuentasNuevaController::class, 'vistaPrevia'), ['documento' => 'cuenta|informe'], methods: ['GET']));
$rutas->add('cuentas.enviar', new Route('/cuentas/nueva/enviar', $rutaCuentas(CuentasNuevaController::class, 'enviar'), methods: ['POST']));
$rutas->add('cuentas.enviada', new Route('/cuentas/enviada/{id}', $rutaCuentas(CuentasNuevaController::class, 'enviada'), ['id' => '\d+'], methods: ['GET']));
$rutas->add('cuentas.enviada.correo', new Route('/cuentas/enviada/{id}/correo', $rutaCuentas(CuentasNuevaController::class, 'enviarACorreo'), ['id' => '\d+'], methods: ['POST']));
$rutas->add('cuentas.pdf', new Route('/cuentas/pdf/{id}/{documento}', $rutaCuentas(CuentasNuevaController::class, 'descargarPdf'), ['id' => '\d+', 'documento' => 'cuenta|informe'], methods: ['GET']));
$rutas->add('cuentas.descarga', new Route('/cuentas/descarga/{token}', $rutaCuentas(CuentasDescargaController::class, 'descargar'), ['token' => '[a-f0-9]{64}'], methods: ['GET']));

// --- Mapa del sitio (Fase 9) ---
// Ruta fija y no página: se arma desde los menús vigentes, así que añadir un
// ítem en el panel lo refleja sin redactar nada.
$rutas->add('mapa-sitio', new Route(
    path: '/mapa-del-sitio',
    defaults: ['_controlador' => [MapaSitioController::class, 'mostrar']],
    methods: ['GET'],
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

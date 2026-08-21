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
use App\Http\Admin\FaqController;
use App\Http\Admin\LoginController;
use App\Http\Admin\MediosController;
use App\Http\Admin\MenusController;
use App\Http\Admin\NoticiasController;
use App\Http\Admin\DatasetsController;
use App\Http\Admin\DependenciasController;
use App\Http\Admin\DocumentosController;
use App\Http\Admin\BandejaTramitesController;
use App\Http\Admin\TiposTramiteController;
use App\Http\Admin\TransparenciaController;
use App\Http\Admin\EspecialidadesController;
use App\Http\Admin\PaginasAdminController;
use App\Http\Admin\PanelController;
use App\Http\Admin\PoliticasController;
use App\Http\Admin\ProfesionalesController;
use App\Http\Admin\SedesController;
use App\Http\Admin\ServiciosController;
use App\Http\Web\ArchivoController;
use App\Http\Web\BuscarController;
use App\Http\Web\DirectorioPublicoController;
use App\Http\Web\FaqPublicoController;
use App\Http\Web\DocumentosPublicoController;
use App\Http\Web\EspecialidadesPublicoController;
use App\Http\Web\InicioController;
use App\Http\Web\MuestraDisenoController;
use App\Http\Web\NoticiasPublicoController;
use App\Http\Web\PaginasController;
use App\Http\Web\PoliticasPublicoController;
use App\Http\Web\SedesPublicoController;
use App\Http\Web\SeoController;
use App\Http\Web\ServiciosPublicoController;
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

$rutas->add('noticias.detalle', new Route(
    path: '/noticias/{slug}',
    defaults: ['_controlador' => [NoticiasPublicoController::class, 'detalle']],
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

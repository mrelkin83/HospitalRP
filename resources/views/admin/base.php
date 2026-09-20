<?php
/**
 * Cascarón del panel administrativo.
 * Espera: $titulo (string), $usuarioNombre (string), $tokenCsrf (string),
 * $contenido (HTML ya compuesto y escapado por la vista de página).
 * Opcional: $conEditor (bool) — carga CKEditor 5 autoalojado.
 * Opcional: $puede (callable(string): bool) — filtra el menú por permisos.
 *           Si no llega se muestra completo: las pantallas anteriores a la
 *           Fase 10 renderizan esta plantilla sin él, y un menú de más no deja
 *           pasar a nadie (el Kernel sigue exigiendo el permiso de la ruta).
 */

use App\Core\Support\Recursos;

$conEditor ??= false;
$puede ??= static fn (string $permiso): bool => true;

/**
 * El menú, agrupado. Antes eran veintiséis enlaces en una sola fila con
 * desplazamiento horizontal: encontrar «Menús» exigía arrastrar a ciegas.
 *
 * @var array<string, array<string, array{0: string, 1: ?string}>>
 */
$secciones = [
    'Contenido' => [
        'Héroe de la portada' => ['/admin/heroes', 'contenido.ver'],
        'Noticias' => ['/admin/noticias', 'contenido.ver'],
        'Páginas' => ['/admin/paginas', 'contenido.ver'],
        'Campañas' => ['/admin/campanas', 'contenido.ver'],
        'Avisos' => ['/admin/avisos', 'contenido.ver'],
        'Ventanas emergentes' => ['/admin/popups', 'contenido.ver'],
        'FAQ' => ['/admin/faq', 'contenido.ver'],
        'Categorías' => ['/admin/categorias', 'contenido.ver'],
        'Medios' => ['/admin/medios', 'contenido.editar'],
    ],
    'Atención' => [
        'Bandeja de trámites' => ['/admin/tramites', 'tramites.gestionar'],
        'Tipos de trámite' => ['/admin/tipos-tramite', 'tramites.configurar'],
        'Encuestas' => ['/admin/encuestas', 'encuestas.ver'],
    ],
    'Transparencia' => [
        'Menú de transparencia' => ['/admin/transparencia', 'transparencia.ver'],
        'Documentos' => ['/admin/documentos', 'documentos.ver'],
        'Datos abiertos' => ['/admin/datasets', 'transparencia.ver'],
        'Indicadores' => ['/admin/indicadores', 'indicadores.ver'],
        'Políticas del sitio' => ['/admin/politicas', 'cumplimiento.politicas'],
    ],
    'Institucional' => [
        'Sedes' => ['/admin/sedes', 'directorio.ver'],
        'Servicios' => ['/admin/servicios', 'directorio.ver'],
        'Especialidades' => ['/admin/especialidades', 'directorio.ver'],
        'Directorio médico' => ['/admin/profesionales', 'directorio.ver'],
        'Servidores públicos' => ['/admin/servidores-publicos', 'directorio.ver'],
        'Dependencias' => ['/admin/dependencias', 'sistema.dependencias'],
        'Convocatorias' => ['/admin/convocatorias', 'convocatorias.ver'],
        'Eventos' => ['/admin/eventos', 'eventos.ver'],
    ],
    'Cuentas de cobro' => [
        'Contratos' => ['/admin/cuentas-cobro/contratos', 'cuentas.administrar'],
        'Contratistas' => ['/admin/cuentas-cobro/contratistas', 'cuentas.administrar'],
        'Supervisores' => ['/admin/cuentas-cobro/supervisores', 'cuentas.administrar'],
        'Parámetros' => ['/admin/cuentas-cobro/parametros', 'cuentas.administrar'],
    ],
    'Sistema' => [
        'Ajustes del sitio' => ['/admin/configuracion', 'sistema.configuracion'],
        'Usuarios' => ['/admin/usuarios', 'sistema.usuarios'],
        'Roles y permisos' => ['/admin/roles', 'sistema.roles'],
        'Auditoría' => ['/admin/auditoria', 'sistema.auditoria'],
        'Menús del sitio' => ['/admin/menus', 'sistema.menus'],
        'Redirecciones' => ['/admin/redirecciones', 'sistema.redirecciones'],
        'Asistente de IA' => ['/admin/ia', 'sistema.ia'],
    ],
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="csrf" content="<?= $e($tokenCsrf) ?>">
    <title><?= $e($titulo) ?> — Panel · E.S.E. Hospital Rosario Pumarejo de López</title>
    <link rel="stylesheet" href="<?= $e(Recursos::url('/build/app.css')) ?>">
    <script src="/js/menu.js" defer></script>
    <script src="/js/ia.js" defer></script>
    <?php if ($conEditor) { ?>
        <link rel="stylesheet" href="/vendor/ckeditor5/ckeditor5.css">
        <script src="/vendor/ckeditor5/ckeditor5.umd.js" defer></script>
        <script src="/vendor/ckeditor5/translations/es.umd.js" defer></script>
        <script src="/js/editor.js" defer></script>
    <?php } ?>
</head>
<body class="fondo-degradado-marca">
    <a class="salto-contenido" href="#contenido">Saltar al contenido</a>

    <header class="border-b borde-suave fondo-superficie">
        <div class="mx-auto max-w-6xl px-4 py-3 flex flex-wrap items-center gap-x-4 gap-y-2">
            <a href="/admin" class="font-bold texto-titular no-underline">
                Panel <span class="font-normal texto-suave">· E.S.E. Hospital Rosario Pumarejo de López</span>
            </a>

            <div class="ms-auto flex items-center gap-3">
                <a href="/admin/perfil" class="text-sm font-semibold texto-titular"><?= $e($usuarioNombre) ?></a>
                <form method="post" action="/admin/salir">
                    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                    <button type="submit" class="boton boton-secundario">Cerrar sesión</button>
                </form>
            </div>
        </div>

        <nav aria-label="Secciones del panel" class="mx-auto max-w-6xl px-4 pb-3">
            <?php /* El submenú va flotando (`absolute`) y no dentro del flujo:
                     desplegado, el ancho del ítem —y con él el de toda la
                     barra— cambiaba y la fila saltaba. Flotando, abrir una
                     sección no mueve nada de lo que hay alrededor. */ ?>
            <ul class="flex flex-wrap items-center gap-2 text-sm">
                <li>
                    <a href="/admin" class="inline-block rounded-md px-3 py-2 font-semibold no-underline hover:bg-primario-50 dark:hover:bg-primario-800">Inicio</a>
                </li>
                <?php foreach ($secciones as $nombre => $enlaces) {
                    $visibles = [];

                    foreach ($enlaces as $etiqueta => [$url, $permiso]) {
                        if ($permiso === null || $puede($permiso)) {
                            $visibles[$etiqueta] = $url;
                        }
                    }

                    if ($visibles === []) {
                        continue;
                    }
                    ?>
                    <li class="relative">
                        <details class="menu-panel" name="menu-panel">
                            <summary class="cursor-pointer rounded-md px-3 py-2 font-semibold hover:bg-primario-50 dark:hover:bg-primario-800"><?= $e($nombre) ?></summary>
                            <ul class="absolute start-0 top-full z-30 mt-1 w-56 rounded-md border borde-suave fondo-superficie p-2 shadow-lg">
                                <?php foreach ($visibles as $etiqueta => $url) { ?>
                                    <li>
                                        <a href="<?= $e($url) ?>" class="block rounded-md px-3 py-2 no-underline hover:bg-primario-50 dark:hover:bg-primario-800"><?= $e($etiqueta) ?></a>
                                    </li>
                                <?php } ?>
                            </ul>
                        </details>
                    </li>
                <?php } ?>
            </ul>
        </nav>
    </header>

    <main id="contenido" class="mx-auto max-w-6xl px-4 py-8">
        <?= $contenido ?>
    </main>
</body>
</html>

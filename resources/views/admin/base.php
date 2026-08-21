<?php
/**
 * Cascarón del panel administrativo.
 * Espera: $titulo (string), $usuarioNombre (string), $tokenCsrf (string),
 * $contenido (HTML ya compuesto y escapado por la vista de página).
 * Opcional: $conEditor (bool) — carga CKEditor 5 autoalojado.
 */
$conEditor ??= false;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= $e($titulo) ?> — Panel · E.S.E. Hospital Rosario Pumarejo de López</title>
    <link rel="stylesheet" href="/build/app.css">
    <?php if ($conEditor) { ?>
        <link rel="stylesheet" href="/vendor/ckeditor5/ckeditor5.css">
        <script src="/vendor/ckeditor5/ckeditor5.umd.js" defer></script>
        <script src="/vendor/ckeditor5/translations/es.umd.js" defer></script>
        <script src="/js/editor.js" defer></script>
    <?php } ?>
</head>
<body>
    <a class="salto-contenido" href="#contenido">Saltar al contenido</a>

    <header class="border-b borde-suave fondo-superficie">
        <div class="mx-auto max-w-6xl px-4 py-3 flex flex-wrap items-center gap-x-4 gap-y-2">
            <a href="/admin" class="font-bold texto-titular no-underline">
                Panel <span class="font-normal texto-suave">· E.S.E. Hospital Rosario Pumarejo de López</span>
            </a>

            <form method="post" action="/admin/salir" class="ms-auto flex items-center gap-3">
                <span class="text-sm texto-suave"><?= $e($usuarioNombre) ?></span>
                <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                <button type="submit" class="boton boton-secundario">Cerrar sesión</button>
            </form>
        </div>

        <nav aria-label="Secciones del panel" class="mx-auto max-w-6xl px-4 pb-2">
            <ul class="flex gap-1 overflow-x-auto text-sm font-semibold">
                <li><a href="/admin" class="inline-block rounded-md px-3 py-2 no-underline hover:bg-primario-50 dark:hover:bg-primario-800">Inicio</a></li>
                <li><a href="/admin/noticias" class="inline-block rounded-md px-3 py-2 no-underline hover:bg-primario-50 dark:hover:bg-primario-800">Noticias</a></li>
                <li><a href="/admin/paginas" class="inline-block rounded-md px-3 py-2 no-underline hover:bg-primario-50 dark:hover:bg-primario-800">Páginas</a></li>
                <li><a href="/admin/campanas" class="inline-block rounded-md px-3 py-2 no-underline hover:bg-primario-50 dark:hover:bg-primario-800">Campañas</a></li>
                <li><a href="/admin/avisos" class="inline-block rounded-md px-3 py-2 no-underline hover:bg-primario-50 dark:hover:bg-primario-800">Avisos</a></li>
                <li><a href="/admin/faq" class="inline-block rounded-md px-3 py-2 no-underline hover:bg-primario-50 dark:hover:bg-primario-800">FAQ</a></li>
                <li><a href="/admin/categorias" class="inline-block rounded-md px-3 py-2 no-underline hover:bg-primario-50 dark:hover:bg-primario-800">Categorías</a></li>
                <li><a href="/admin/tramites" class="inline-block rounded-md px-3 py-2 no-underline hover:bg-primario-50 dark:hover:bg-primario-800">Trámites</a></li>
                <li><a href="/admin/tipos-tramite" class="inline-block rounded-md px-3 py-2 no-underline hover:bg-primario-50 dark:hover:bg-primario-800">Tipos de trámite</a></li>
                <li><a href="/admin/documentos" class="inline-block rounded-md px-3 py-2 no-underline hover:bg-primario-50 dark:hover:bg-primario-800">Documentos</a></li>
                <li><a href="/admin/politicas" class="inline-block rounded-md px-3 py-2 no-underline hover:bg-primario-50 dark:hover:bg-primario-800">Políticas</a></li>
                <li><a href="/admin/transparencia" class="inline-block rounded-md px-3 py-2 no-underline hover:bg-primario-50 dark:hover:bg-primario-800">Transparencia</a></li>
                <li><a href="/admin/datasets" class="inline-block rounded-md px-3 py-2 no-underline hover:bg-primario-50 dark:hover:bg-primario-800">Datos abiertos</a></li>
                <li><a href="/admin/dependencias" class="inline-block rounded-md px-3 py-2 no-underline hover:bg-primario-50 dark:hover:bg-primario-800">Dependencias</a></li>
                <li><a href="/admin/sedes" class="inline-block rounded-md px-3 py-2 no-underline hover:bg-primario-50 dark:hover:bg-primario-800">Sedes</a></li>
                <li><a href="/admin/especialidades" class="inline-block rounded-md px-3 py-2 no-underline hover:bg-primario-50 dark:hover:bg-primario-800">Especialidades</a></li>
                <li><a href="/admin/servicios" class="inline-block rounded-md px-3 py-2 no-underline hover:bg-primario-50 dark:hover:bg-primario-800">Servicios</a></li>
                <li><a href="/admin/profesionales" class="inline-block rounded-md px-3 py-2 no-underline hover:bg-primario-50 dark:hover:bg-primario-800">Directorio médico</a></li>
                <li><a href="/admin/medios" class="inline-block rounded-md px-3 py-2 no-underline hover:bg-primario-50 dark:hover:bg-primario-800">Medios</a></li>
                <li><a href="/admin/menus" class="inline-block rounded-md px-3 py-2 no-underline hover:bg-primario-50 dark:hover:bg-primario-800">Menús</a></li>
                <?php /* Trámites, documentos y transparencia se añaden fase por fase. */ ?>
            </ul>
        </nav>
    </header>

    <main id="contenido" class="mx-auto max-w-6xl px-4 py-8">
        <?= $contenido ?>
    </main>
</body>
</html>

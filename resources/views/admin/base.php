<?php
/**
 * Cascarón del panel administrativo.
 * Espera: $titulo (string), $usuarioNombre (string), $tokenCsrf (string),
 * $contenido (HTML ya compuesto y escapado por la vista de página).
 */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= $e($titulo) ?> — Panel · E.S.E. Hospital Rosario Pumarejo de López</title>
    <link rel="stylesheet" href="/build/app.css">
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
                <li><a href="/admin" aria-current="page" class="inline-block rounded-md px-3 py-2 no-underline bg-primario-50 text-primario-700 dark:bg-primario-800 dark:text-primario-100">Inicio</a></li>
                <?php /* Los módulos (Contenido, Trámites, Documentos…) se añaden fase por fase. */ ?>
            </ul>
        </nav>
    </header>

    <main id="contenido" class="mx-auto max-w-6xl px-4 py-8">
        <?= $contenido ?>
    </main>
</body>
</html>

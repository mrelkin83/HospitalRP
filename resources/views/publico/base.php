<?php
/**
 * Layout del sitio público (Fase 1, paso 8).
 * Espera: $titulo, $descripcion (?string), $contenido (HTML compuesto),
 *         $noindex (bool), $esPortada (bool), $aviso (?Aviso), $emergencia (bool),
 *         $menuSuperior/$menuPrincipal/$menuPie1/$menuPie2 (list<item>),
 *         $config (ConfigDinamica).
 *
 * Encabezado y pie según Res. 1519: menús obligatorios, NIT, dirección,
 * canales de atención, políticas y notificaciones judiciales.
 */
$nombreEntidad = $config->texto('entidad.nombre', 'E.S.E. Hospital Rosario Pumarejo de López');
$tituloCompleto = $esPortada ? $nombreEntidad : $titulo . ' — ' . $nombreEntidad;
$enlaceNav = static fn (array $item): string => sprintf(
    '<a class="inline-block whitespace-nowrap rounded-md px-3 py-2 no-underline font-semibold texto-titular hover:bg-primario-50 dark:hover:bg-primario-800"%s href="%s">%s</a>',
    $item['nueva_pestana'] ? ' target="_blank" rel="noopener"' : '',
    htmlspecialchars($item['url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    htmlspecialchars($item['etiqueta'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($noindex) { ?><meta name="robots" content="noindex"><?php } ?>
    <title><?= $e($tituloCompleto) ?></title>
    <?php if ($descripcion !== null) { ?>
        <meta name="description" content="<?= $e($descripcion) ?>">
    <?php } ?>
    <?php if (!$noindex) { ?>
        <link rel="canonical" href="<?= $e($urlCanonica) ?>">
    <?php } ?>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= $e($nombreEntidad) ?>">
    <meta property="og:title" content="<?= $e($tituloCompleto) ?>">
    <?php if ($descripcion !== null) { ?>
        <meta property="og:description" content="<?= $e($descripcion) ?>">
    <?php } ?>
    <meta property="og:url" content="<?= $e($urlCanonica) ?>">
    <meta property="og:locale" content="es_CO">
    <meta name="theme-color" content="#0A192F">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/iconos/icono-192.png" type="image/png">
    <link rel="apple-touch-icon" href="/iconos/icono-192.png">
    <?php if ($esPortada) {
        // Schema.org del hospital con los datos administrables del panel.
        $esquema = [
            '@context' => 'https://schema.org',
            '@type' => 'Hospital',
            'name' => $nombreEntidad,
            'url' => $urlCanonica,
            'telephone' => $config->texto('contacto.pbx'),
            'email' => $config->texto('contacto.correo'),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $config->texto('contacto.direccion'),
                'addressLocality' => 'Valledupar',
                'addressRegion' => 'Cesar',
                'addressCountry' => 'CO',
            ],
        ];
    ?>
        <script type="application/ld+json"><?= json_encode($esquema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?></script>
    <?php } ?>
    <link rel="stylesheet" href="/build/app.css">
    <script src="/js/sitio.js"></script>
    <script src="/js/filtro.js" defer></script>
    <script src="/js/consentimiento.js" defer></script>
</head>
<body>
    <a class="salto-contenido" href="#contenido">Saltar al contenido</a>

    <?php /* --- Barra superior: accesibilidad + teléfonos (prototipo) ---
             El cian solo aparece aquí y en el pie, sobre el azul 900: es el
             único fondo donde alcanza contraste AA (12,7:1). --- */ ?>
    <div class="bg-primario-900 text-white">
        <div class="mx-auto max-w-7xl px-4 py-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
            <div class="flex items-center gap-1" role="group" aria-label="Opciones de accesibilidad">
                <button type="button" class="rounded px-2 py-1 hover:bg-primario-800" data-alternar="contraste">Alto contraste</button>
                <button type="button" class="rounded px-2 py-1 hover:bg-primario-800" data-alternar="fuente-menos" aria-label="Reducir tamaño de letra">A−</button>
                <button type="button" class="rounded px-2 py-1 hover:bg-primario-800" data-alternar="fuente-mas" aria-label="Aumentar tamaño de letra">A+</button>
                <button type="button" class="rounded px-2 py-1 hover:bg-primario-800" data-alternar="movimiento">Reducir movimiento</button>
                <button type="button" class="rounded px-2 py-1 hover:bg-primario-800" data-alternar="tema">Tema oscuro</button>
            </div>
            <nav aria-label="Enlaces institucionales" class="hidden md:flex items-center gap-3">
                <?php foreach ($menuSuperior as $item) { ?>
                    <a class="text-primario-100 no-underline hover:underline" href="<?= $e($item['url']) ?>"><?= $e($item['etiqueta']) ?></a>
                <?php } ?>
            </nav>
            <p class="ms-auto flex flex-wrap items-center gap-x-4 gap-y-1">
                <span class="text-primario-100">Citas:
                    <a class="text-[color:var(--color-marca-cian)] font-bold" href="tel:<?= $e(preg_replace('/\D+/', '', $config->texto('contacto.call_center'))) ?>"><?= $e($config->texto('contacto.call_center')) ?></a>
                </span>
                <span class="text-primario-100">PBX:
                    <a class="text-white font-semibold" href="tel:<?= $e(preg_replace('/\D+/', '', $config->texto('contacto.pbx'))) ?>"><?= $e($config->texto('contacto.pbx')) ?></a>
                </span>
            </p>
        </div>
    </div>

    <?php /* --- Encabezado: panel de vidrio flotante ---
             El prototipo lo trae `fixed`. Aquí va `sticky`: un encabezado
             fijo tapa el destino del «Saltar al contenido» y empuja fuera de
             pantalla el aviso de emergencia, que es lo más importante que
             puede mostrar este sitio. --- */ ?>
    <header class="lg:sticky lg:top-0 z-40 pt-3 pb-2">
        <div class="mx-auto max-w-7xl px-4">
            <div class="panel-vidrio rounded-2xl px-4 py-3 flex flex-wrap items-center gap-x-6 gap-y-2">
                <?php /* Logotipo y PQRSDF comparten fila en móvil: separados,
                         la cabecera crecía hasta media pantalla. */ ?>
                <a href="/" class="no-underline flex min-w-0 flex-1 items-center gap-3 lg:flex-none">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-primario-700 to-primario-500 text-white" aria-hidden="true">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 12h4l2-6 4 12 2-6h6"/>
                        </svg>
                    </span>
                    <span class="min-w-0">
                        <span class="block font-bold leading-tight texto-titular"><?= $e($nombreEntidad) ?></span>
                        <span class="hidden text-sm texto-suave sm:block"><?= $e($config->texto('entidad.eslogan')) ?></span>
                    </span>
                </a>

                <a class="boton boton-primario shrink-0 order-2 lg:order-none lg:ms-0" href="/atencion-ciudadania/pqrsdf">PQRSDF</a>

                <nav aria-label="Navegación principal" class="order-3 w-full lg:order-none lg:w-auto lg:ms-auto">
                    <ul class="flex gap-1 overflow-x-auto">
                        <?php foreach ($menuPrincipal as $item) { ?>
                            <li><?= $enlaceNav($item) ?></li>
                        <?php } ?>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <?php /* --- Modo emergencia: el aviso crítico manda sobre todo --- */ ?>
    <?php if ($emergencia && $aviso !== null) { ?>
        <section role="alert" aria-labelledby="emergencia-titulo" class="bg-error-600 text-white">
            <div class="mx-auto max-w-7xl px-4 py-6">
                <h2 id="emergencia-titulo" class="text-white text-2xl font-bold"><?= $e($aviso->titulo) ?></h2>
                <p class="mt-2 max-w-3xl"><?= $e($aviso->mensaje) ?></p>
                <p class="mt-4 flex flex-wrap gap-3">
                    <a class="boton bg-white text-error-600 font-bold"
                       href="tel:<?= $e(preg_replace('/\D+/', '', $config->texto('contacto.emergencias'))) ?>">
                        Emergencias: <?= $e($config->texto('contacto.emergencias')) ?>
                    </a>
                    <?php if ($aviso->url !== null) { ?>
                        <a class="boton border border-white text-white" href="<?= $e($aviso->url) ?>">
                            <?= $e($aviso->textoBoton ?? 'Más información') ?>
                        </a>
                    <?php } ?>
                </p>
            </div>
        </section>
    <?php } elseif ($aviso !== null) { ?>
        <div class="<?= $aviso->nivel === 'alerta' ? 'bg-aviso-100' : 'bg-primario-50 dark:bg-primario-800' ?>">
            <p class="mx-auto max-w-7xl px-4 py-2.5 text-sm flex flex-wrap items-center gap-2" role="status">
                <strong class="<?= $aviso->nivel === 'alerta' ? 'text-aviso-900' : '' ?>"><?= $e($aviso->titulo) ?>:</strong>
                <span><?= $e($aviso->mensaje) ?></span>
                <?php if ($aviso->url !== null) { ?>
                    <a href="<?= $e($aviso->url) ?>"><?= $e($aviso->textoBoton ?? 'Más información') ?></a>
                <?php } ?>
            </p>
        </div>
    <?php } ?>

    <main id="contenido">
        <?= $contenido ?>
    </main>

    <?php /* --- Pie normativo (Res. 1519) --- */ ?>
    <footer class="mt-16 bg-primario-900 text-white">
        <div class="mx-auto max-w-7xl px-4 py-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <h2 class="text-[color:var(--color-marca-cian)] text-base font-bold">República de Colombia</h2>
                <p class="mt-2 text-sm text-primario-100 leading-relaxed">
                    <?= $e($nombreEntidad) ?><br>
                    NIT: <?= $e($config->texto('entidad.nit')) ?><br>
                    <?= $e($config->texto('contacto.direccion')) ?><br>
                    <?= $e($config->texto('contacto.horario')) ?>
                </p>
            </div>
            <nav aria-labelledby="pie-enlaces">
                <h2 id="pie-enlaces" class="text-[color:var(--color-marca-cian)] text-base font-bold">Enlaces de interés</h2>
                <ul class="mt-2 flex flex-col gap-1.5 text-sm">
                    <?php foreach ($menuPie1 as $item) { ?>
                        <li><a class="text-primario-100 no-underline hover:underline" href="<?= $e($item['url']) ?>"><?= $e($item['etiqueta']) ?></a></li>
                    <?php } ?>
                </ul>
            </nav>
            <nav aria-labelledby="pie-normatividad">
                <h2 id="pie-normatividad" class="text-[color:var(--color-marca-cian)] text-base font-bold">Normatividad</h2>
                <ul class="mt-2 flex flex-col gap-1.5 text-sm">
                    <?php foreach ($menuPie2 as $item) { ?>
                        <li><a class="text-primario-100 no-underline hover:underline" href="<?= $e($item['url']) ?>"><?= $e($item['etiqueta']) ?></a></li>
                    <?php } ?>
                </ul>
            </nav>
            <div>
                <h2 class="text-[color:var(--color-marca-cian)] text-base font-bold">Atención al ciudadano</h2>
                <ul class="mt-2 flex flex-col gap-1.5 text-sm text-primario-100">
                    <li>PBX: <?= $e($config->texto('contacto.pbx')) ?></li>
                    <li>Citas (call center): <?= $e($config->texto('contacto.call_center')) ?></li>
                    <li class="break-words">Línea ética anticorrupción:<br>
                        <?= $e($config->texto('contacto.linea_etica_telefono')) ?> ·
                        <a class="text-primario-100 no-underline hover:underline" href="mailto:<?= $e($config->texto('contacto.linea_etica_correo')) ?>"><?= $e($config->texto('contacto.linea_etica_correo')) ?></a>
                    </li>
                    <li><a class="text-primario-100 no-underline hover:underline break-words" href="mailto:<?= $e($config->texto('contacto.correo')) ?>"><?= $e($config->texto('contacto.correo')) ?></a></li>
                    <li class="break-words">Notificaciones judiciales:<br>
                        <a class="text-primario-100 no-underline hover:underline" href="mailto:<?= $e($config->texto('contacto.correo_notificaciones')) ?>"><?= $e($config->texto('contacto.correo_notificaciones')) ?></a>
                    </li>
                </ul>
                <?php
                $redes = array_filter([
                    'Facebook' => $config->texto('redes.facebook'),
                    'Instagram' => $config->texto('redes.instagram'),
                    'YouTube' => $config->texto('redes.youtube'),
                    'X' => $config->texto('redes.x'),
                    'LinkedIn' => $config->texto('redes.linkedin'),
                ]);
                ?>
                <?php if ($redes !== []) { ?>
                    <p class="mt-4 flex flex-wrap gap-3 text-sm">
                        <?php foreach ($redes as $red => $url) { ?>
                            <a class="text-primario-100 no-underline hover:underline" href="<?= $e($url) ?>" rel="noopener" target="_blank"><?= $e($red) ?></a>
                        <?php } ?>
                    </p>
                <?php } ?>
            </div>
        </div>
        <div class="border-t border-primario-800">
            <p class="mx-auto max-w-7xl px-4 py-4 text-sm text-primario-200 flex flex-wrap gap-x-6 gap-y-1">
                <span>© <?= date('Y') ?> <?= $e($nombreEntidad) ?>. Todos los derechos reservados.</span>
                <span>Cumplimos con la Ley 1712 de 2014 y la Resolución 1519 de 2020.</span>
            </p>
        </div>
    </footer>

    <?php /* --- Aviso de cookies (Fase 6): oculto hasta que el JS confirme
             que no hay consentimiento previo. Sin JS no hay scripts de
             terceros, así que tampoco hace falta el aviso. --- */ ?>
    <div data-banner-cookies hidden
         class="fixed inset-x-0 bottom-0 z-50 border-t borde-suave fondo-superficie shadow-lg"
         role="region" aria-label="Aviso de cookies">
        <div class="mx-auto max-w-7xl px-4 py-4 flex flex-wrap items-center gap-4">
            <p class="min-w-0 flex-1 text-sm">
                Este sitio usa cookies esenciales para funcionar. Las analíticas son
                opcionales y solo se activan si las aceptas.
                <a href="/politica-de-cookies">Conoce la política de cookies</a>.
            </p>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="analiticas" value="1">
                Permitir analíticas
            </label>
            <span class="flex gap-2">
                <button type="button" class="boton boton-secundario" data-cookies="seleccion">Guardar selección</button>
                <button type="button" class="boton boton-primario" data-cookies="todas">Aceptar todas</button>
            </span>
        </div>
    </div>
</body>
</html>

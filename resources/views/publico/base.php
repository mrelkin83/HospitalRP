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
use App\Core\Support\Recursos;

$nombreEntidad = $config->texto('entidad.nombre', 'E.S.E. Hospital Rosario Pumarejo de López');

// Marca y textos del pie: administrables en Panel → Ajustes del sitio. El
// segundo argumento de texto() es lo que se ve si la clave no existe todavía,
// así que una instalación sin sembrar sigue mostrando el pie completo.
$logoEntidad = $config->texto('marca.logo', '/img/logo_pequeno.jpeg');
$govcoUrl = $config->texto('govco.url', 'https://www.gov.co/');
$govcoLeyenda = $config->texto('govco.leyenda', 'Portal Único del Estado Colombiano');
$govcoLogo = $config->texto('govco.logo', '/img/logo_gov.co.png');
$tituloCompleto = $esPortada ? $nombreEntidad : $titulo . ' — ' . $nombreEntidad;
$enlaceNav = static fn (array $item): string => sprintf(
    '<a class="inline-block whitespace-nowrap px-3 py-2 no-underline font-semibold menu-principal-enlace texto-titular"%s href="%s">%s</a>',
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
    <link rel="stylesheet" href="<?= $e(Recursos::url('/build/app.css')) ?>">
    <script src="<?= $e(Recursos::url('/js/sitio.js')) ?>"></script>
    <script src="<?= $e(Recursos::url('/js/menu.js')) ?>" defer></script>
    <script src="<?= $e(Recursos::url('/js/filtro.js')) ?>" defer></script>
    <script src="<?= $e(Recursos::url('/js/consentimiento.js')) ?>" defer></script>
    <?php if ($esPortada) { ?>
        <?php /* Alpine solo donde hay algo que animar: las pestañas de
                 «Campañas y comunicados». Es la compilación CSP (sin `eval`),
                 autoalojada, y el orden importa: comunicaciones.js registra el
                 componente en `alpine:init`, que Alpine dispara al arrancar.
                 Los dos van con `defer`, así que se ejecutan en este orden y
                 después de tener el HTML. */ ?>
        <?php /* El héroe rota sin Alpine: es un atributo que cambia, no un
                 componente con estado. Va aparte para que siga funcionando
                 aunque algún día se retire Alpine de la portada. */ ?>
        <script src="<?= $e(Recursos::url('/js/heroes.js')) ?>" defer></script>
        <script src="<?= $e(Recursos::url('/js/comunicaciones.js')) ?>" defer></script>
        <script src="<?= $e(Recursos::url('/js/encuesta.js')) ?>" defer></script>
        <script src="<?= $e(Recursos::url('/js/alpine.js')) ?>" defer></script>
    <?php } ?>
</head>
<body>
    <!-- Barra Institucional Obligatoria del Portal Único del Estado Colombiano GOV.CO (MinTIC Colombia) -->
    <div class="bg-[#004884] text-white select-none border-b border-white/10">
        <div class="mx-auto max-w-7xl px-4 h-14 flex items-center justify-between">
            <a href="<?= $e($govcoUrl) ?>" target="_blank" rel="noopener" class="inline-flex items-center hover:opacity-85 no-underline transition-opacity" title="Ir a <?= $e($govcoLeyenda) ?> GOV.CO">
                <!-- Logotipo Oficial GOV.CO de Colombia - Tamaño imponente de alta legibilidad -->
                <img src="<?= $e($govcoLogo) ?>" alt="Logotipo Oficial GOV.CO" class="h-12 w-auto object-contain" width="240" height="48">
            </a>
            <span class="text-[9px] sm:text-[10px] uppercase font-bold tracking-widest text-white/70"><?= $e($govcoLeyenda) ?></span>
        </div>
    </div>

    <a class="salto-contenido" href="#contenido">Saltar al contenido</a>

    <?php /* --- Barra superior: accesibilidad + teléfonos ---
             Sobre este azul 700 el cian de marca mide 3,09:1 (Auditoría V9):
             aquí el texto va en blanco o primario-100, nunca en cian. --- */ ?>
    <?php /* Rediseño 2026-11-17: esta barra deja el azul 700 y pasa a claro.
             Pegada a la nueva cabecera azul, dos franjas del mismo azul se
             fundían en un bloque y el encabezado perdía su borde. Claro
             entre el azul marino de GOV.CO y el azul de la cabecera, las
             tres franjas se leen como tres. */ ?>
    <div class="bg-primario-50 dark:bg-primario-900 border-b borde-suave">
        <div class="mx-auto max-w-7xl px-4 py-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
            <?php /* Las etiquetas largas se abrevian en móvil con un <span>
                     oculto para lectores: ahí el nombre completo sigue
                     anunciándose y en pantalla caben las cinco opciones en una
                     línea en vez de tres. */ ?>
            <div class="flex shrink-0 items-center gap-1" role="group" aria-label="Opciones de accesibilidad">
                <button type="button" class="rounded px-2 py-1 font-semibold texto-suave hover:bg-primario-100 hover:texto-titular dark:hover:bg-primario-800" data-alternar="contraste">
                    <span class="sr-only sm:not-sr-only">Alto contraste</span><span aria-hidden="true" class="sm:hidden">◐</span>
                </button>
                <button type="button" class="rounded px-2 py-1 font-semibold texto-suave hover:bg-primario-100 hover:texto-titular dark:hover:bg-primario-800" data-alternar="fuente-menos" aria-label="Reducir tamaño de letra">A−</button>
                <button type="button" class="rounded px-2 py-1 font-semibold texto-suave hover:bg-primario-100 hover:texto-titular dark:hover:bg-primario-800" data-alternar="fuente-mas" aria-label="Aumentar tamaño de letra">A+</button>
                <button type="button" class="rounded px-2 py-1 font-semibold texto-suave hover:bg-primario-100 hover:texto-titular dark:hover:bg-primario-800" data-alternar="movimiento">
                    <span class="sr-only sm:not-sr-only">Reducir movimiento</span><span aria-hidden="true" class="sm:hidden">⏸</span>
                </button>
                <button type="button" class="rounded px-2 py-1 font-semibold texto-suave hover:bg-primario-100 hover:texto-titular dark:hover:bg-primario-800" data-alternar="tema">
                    <span class="sr-only sm:not-sr-only">Tema oscuro</span><span aria-hidden="true" class="sm:hidden">☾</span>
                </button>
            </div>
            <nav aria-label="Enlaces institucionales" class="hidden md:flex items-center gap-3">
                <?php foreach ($menuSuperior as $item) { ?>
                    <a class="texto-suave font-semibold no-underline hover:underline" href="<?= $e($item['url']) ?>"><?= $e($item['etiqueta']) ?></a>
                <?php } ?>
            </nav>
            <?php /* El número en cian sobre este azul medía 3,09:1 (Auditoría
                     V9, CC5/WCAG 1.4.3): el texto va ahora en primario-100
                     (5,6:1). Los valores llegan con el +57 que exige el Anexo
                     2, 2.2.1(4); el href lo conserva en formato E.164. */ ?>
            <p class="ms-auto flex flex-wrap items-center gap-x-4 gap-y-1">
                <span class="texto-suave">Citas:
                    <a class="texto-titular font-bold" href="tel:+<?= $e(preg_replace('/\D+/', '', $config->texto('contacto.call_center'))) ?>"><?= $e($config->texto('contacto.call_center')) ?></a>
                </span>
                <span class="texto-suave">PBX:
                    <a class="texto-titular font-semibold" href="tel:+<?= $e(preg_replace('/\D+/', '', $config->texto('contacto.pbx'))) ?>"><?= $e($config->texto('contacto.pbx')) ?></a>
                </span>
            </p>
        </div>
    </div>

    <?php /* --- Encabezado: panel de vidrio flotante ---
             El prototipo lo trae `fixed`. Aquí va `sticky`: un encabezado
             fijo tapa el destino del «Saltar al contenido» y empuja fuera de
             pantalla el aviso de emergencia, que es lo más importante que
             puede mostrar este sitio. --- */ ?>
    <header class="cabecera-institucional lg:sticky lg:top-0 z-40">
        <div class="mx-auto max-w-7xl px-4">
            <div class="px-0 py-2.5 flex flex-wrap items-center gap-x-6 gap-y-2">
                <?php /* Logotipo y PQRSDF comparten fila en móvil: separados,
                         la cabecera crecía hasta media pantalla. */ ?>
                <?php /* Logotipo en PNG transparente, sin placa blanca: la
                         Auditoría V9 (sección 12) pidió integrarlo al panel.
                         Sube de 48 a 56 px para que el detalle de la marca se
                         lea; el máximo del estándar 2022 es 80-90 px. En tema
                         oscuro, .logo-entidad le da un halo claro (app.css). */ ?>
                <a href="/" class="no-underline flex min-w-0 flex-1 items-center gap-3 lg:flex-none">
                    <img src="<?= $e($logoEntidad) ?>" alt="Logotipo <?= $e($nombreEntidad) ?>" class="logo-entidad h-14 w-auto object-contain" width="56" height="56">
                    <span class="min-w-0">
                        <span class="block font-bold leading-tight texto-titular"><?= $e($nombreEntidad) ?></span>
                        <span class="hidden text-sm texto-suave sm:block"><?= $e($config->texto('entidad.eslogan')) ?></span>
                    </span>
                </a>

                <a class="boton boton-sobre-azul shrink-0 order-2 lg:order-3 lg:ms-auto" href="/atencion-ciudadania/pqrsdf">PQRSDF</a>

                <?php /* `min-w-0` no es cosmético: sin él este <nav> es un ítem
                         flex que no puede encoger por debajo de su contenido, el
                         desbordamiento del <ul> nunca entra en juego y los 11
                         ítems del menú empujaban la página 339 px a la derecha.

                         El menú ocupa SU PROPIA FILA a lo ancho, también en
                         escritorio: con once raíces, apretarlo junto al
                         logotipo lo partía en tres líneas con un ítem suelto.
                         A fila completa entra en una.

                         Y en móvil se desplaza en una sola fila en vez de
                         envolver: envolviendo once ítems la cabecera crecía a
                         332 px —más de un tercio de la pantalla— y tapaba el
                         contenido por el que la persona entró. */ ?>
                <?php /* Rediseño 2026-11-17, petición de la gerencia: el
                         logotipo va a la IZQUIERDA y el menú A SU LADO, no
                         debajo.

                         En escritorio el <nav> deja de ocupar fila propia y
                         pasa a ser la columna central: logotipo | menú |
                         PQRSDF. El menú de nueve raíces con los nombres
                         completos que exige la Res. 1519 no cabe en una sola
                         línea junto al logotipo —pide del orden de 1100 px y
                         quedan unos 750—, así que ENVUELVE en dos líneas
                         dentro de su columna (`lg:flex-wrap`, más abajo). Es
                         eso o encoger la letra por debajo de lo legible.

                         En móvil no cambia nada: el menú sigue ocupando el
                         ancho completo bajo el logotipo, que es donde el
                         cajón de /js/menu.js lo despliega. */ ?>
                <nav aria-label="Navegación principal" class="order-3 w-full min-w-0 lg:order-2 lg:w-auto lg:flex-1" data-menu-principal>
                    <?php /* Botón «Menú» (Auditoría V9, sección 11): en 390 px
                             la fila horizontal mostraba completas 3 de las 9
                             categorías y el resto exigía descubrir el gesto de
                             deslizar. El botón —visible, con icono y texto—
                             despliega la lista completa en vertical. Lo
                             gestiona /js/menu.js con aria-expanded; sin
                             JavaScript no se pinta y queda la fila original. */ ?>
                    <button type="button" class="menu-movil-boton" aria-expanded="false" aria-controls="menu-principal-lista" data-menu-movil>
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true" data-icono="abrir"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true" data-icono="cerrar" hidden><path d="M18 6 6 18M6 6l12 12"/></svg>
                        Menú
                    </button>
                    <ul id="menu-principal-lista" class="menu-principal-lista flex flex-nowrap gap-x-1 overflow-x-auto lg:flex-wrap lg:gap-y-0.5 lg:overflow-x-visible">
                        <?php foreach ($menuPrincipal as $item) { ?>
                            <?php if (($item['hijos'] ?? []) === []) { ?>
                                <li><?= $enlaceNav($item) ?></li>
                            <?php } else { ?>
                                <?php /* Desplegable con <details>: se abre sin JavaScript,
                                         se cierra con Escape y el teclado lo recorre solo.
                                         Los hijos ya estaban en la base —el panel permite
                                         anidar— pero la consulta los descartaba y el sitio
                                         enseñaba siete enlaces sueltos.

                                         La raíz sigue siendo un enlace de verdad: «El
                                         Hospital» lleva a su página, y el triángulo abre la
                                         lista. Un menú cuya cabecera solo abre un panel deja
                                         sin destino a quien la pulsa. */ ?>
                                <li class="menu-desplegable relative">
                                    <?php /* `name` compartido = acordeón exclusivo de HTML:
                                             abrir «Programas» cierra «El Hospital» sin una
                                             línea de JavaScript. Donde el atributo todavía
                                             se ignora, /js/menu.js hace lo mismo. */ ?>
                                    <details class="group" name="menu-principal">
                                        <summary class="inline-flex cursor-pointer list-none items-center gap-1 whitespace-nowrap px-3 py-2 font-semibold menu-principal-enlace texto-titular">
                                            <?= $e($item['etiqueta']) ?>
                                            <svg class="h-3.5 w-3.5 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                        </summary>
                                        <ul class="menu-submenu absolute start-0 top-full z-40 mt-1 w-64 rounded-2xl border borde-suave fondo-superficie p-2 shadow-xl">
                                            <?php /* Mismo rótulo que el control que lo abre: la
                                                     Auditoría V9 pidió unificar las denominaciones
                                                     de los enlaces que llevan al mismo destino
                                                     (Anexo 2, 2.4; Anexo 1, CC7). El «Ver …»
                                                     creaba un segundo nombre para la misma página. */ ?>
                                            <li>
                                                <a class="block rounded-lg px-3 py-2 text-sm font-semibold no-underline texto-titular hover:bg-primario-50 dark:hover:bg-primario-800" href="<?= $e($item['url']) ?>">
                                                    <?= $e($item['etiqueta']) ?>
                                                </a>
                                            </li>
                                            <?php foreach ($item['hijos'] as $hijo) { ?>
                                                <li>
                                                    <a class="block rounded-lg px-3 py-2 text-sm no-underline texto-suave hover:bg-primario-50 hover:texto-titular dark:hover:bg-primario-800"
                                                       href="<?= $e($hijo['url']) ?>"<?= $hijo['nueva_pestana'] ? ' target="_blank" rel="noopener"' : '' ?>>
                                                        <?= $e($hijo['etiqueta']) ?>
                                                    </a>
                                                </li>
                                            <?php } ?>
                                        </ul>
                                    </details>
                                </li>
                            <?php } ?>
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
                       href="tel:+<?= $e(preg_replace('/\D+/', '', $config->texto('contacto.emergencias'))) ?>">
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

    <?php /* --- Pie normativo (Res. 1519) con Logotipos de Marca y Gobierno --- */ ?>
    <footer class="mt-16 bg-primario-700 text-white border-t border-white/10">
        <div class="mx-auto max-w-7xl px-4 py-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
            <div class="flex flex-col gap-4">
                <!-- Logotipo del Hospital en el Footer con Relieve Blanco Curvado -->
                <div class="flex items-center gap-3">
                    <img src="<?= $e($logoEntidad) ?>" alt="Logotipo <?= $e($nombreEntidad) ?>" class="h-14 w-auto object-contain rounded-xl bg-white p-1.5 shadow-md shrink-0" width="56" height="56">
                    <div>
                        <h2 class="text-primario-200 text-base font-bold leading-tight"><?= $e($config->texto('pie.titulo_marca', 'República de Colombia')) ?></h2>
                        <span class="text-xs text-primario-200">E.S.E. Hospital Rosario Pumarejo</span>
                    </div>
                </div>
                <p class="text-sm text-primario-100 leading-relaxed">
                    <?= $e($nombreEntidad) ?><br>
                    NIT: <?= $e($config->texto('entidad.nit')) ?><br>
                    <?= $e($config->texto('contacto.direccion')) ?><br>
                    <?= $e($config->texto('contacto.horario')) ?>
                </p>
            </div>
            <nav aria-labelledby="pie-enlaces">
                <h2 id="pie-enlaces" class="text-primario-200 text-base font-bold"><?= $e($config->texto('pie.titulo_enlaces', 'Enlaces de interés')) ?></h2>
                <ul class="mt-2 flex flex-col gap-1.5 text-sm">
                    <?php foreach ($menuPie1 as $item) { ?>
                        <li><a class="texto-suave font-semibold no-underline hover:underline" href="<?= $e($item['url']) ?>"><?= $e($item['etiqueta']) ?></a></li>
                    <?php } ?>
                </ul>
            </nav>
            <nav aria-labelledby="pie-normatividad">
                <h2 id="pie-normatividad" class="text-primario-200 text-base font-bold"><?= $e($config->texto('pie.titulo_normatividad', 'Normatividad')) ?></h2>
                <ul class="mt-2 flex flex-col gap-1.5 text-sm">
                    <?php foreach ($menuPie2 as $item) { ?>
                        <li><a class="texto-suave font-semibold no-underline hover:underline" href="<?= $e($item['url']) ?>"><?= $e($item['etiqueta']) ?></a></li>
                    <?php } ?>
                </ul>
            </nav>
            <div>
                <h2 class="text-primario-200 text-base font-bold"><?= $e($config->texto('pie.titulo_atencion', 'Atención al ciudadano')) ?></h2>
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
                    'TikTok' => $config->texto('redes.tiktok'),
                    'YouTube' => $config->texto('redes.youtube'),
                    'X' => $config->texto('redes.x'),
                    'LinkedIn' => $config->texto('redes.linkedin'),
                ]);
                ?>
                <?php if ($redes !== []) {
                    /* Iconos de Font Awesome Free 7.3.1 (fontawesome.com, licencia
                       CC BY 4.0 para los iconos). Van incrustados y no por CDN: la
                       CSP del sitio es `default-src 'self'` y, además, un tercero
                       que sirve iconos ve la IP de cada visitante del hospital.

                       Los cuatro primeros son los que pidió el hospital en su
                       variante cuadrada; X y LinkedIn conservan el trazo anterior
                       porque no se pidió reemplazarlos. */
                    $iconosRedes = [
                        'Facebook' => '<svg class="h-5 w-5" fill="currentColor" viewBox="0 0 640 640" aria-hidden="true"><path d="M160 96C124.7 96 96 124.7 96 160L96 480C96 515.3 124.7 544 160 544L258.2 544L258.2 398.2L205.4 398.2L205.4 320L258.2 320L258.2 286.3C258.2 199.2 297.6 158.8 383.2 158.8C399.4 158.8 427.4 162 438.9 165.2L438.9 236C432.9 235.4 422.4 235 409.3 235C367.3 235 351.1 250.9 351.1 292.2L351.1 320L434.7 320L420.3 398.2L351 398.2L351 544L480 544C515.3 544 544 515.3 544 480L544 160C544 124.7 515.3 96 480 96L160 96z"/></svg>',
                        'Instagram' => '<svg class="h-5 w-5" fill="currentColor" viewBox="0 0 640 640" aria-hidden="true"><path d="M290.4 275.7C274 286 264.5 304.5 265.5 323.8C266.6 343.2 278.2 360.4 295.6 368.9C313.1 377.3 333.8 375.5 349.6 364.3C366 354 375.5 335.5 374.5 316.2C373.4 296.8 361.8 279.6 344.4 271.1C326.9 262.7 306.2 264.5 290.4 275.7zM432.7 207.3C427.5 202.1 421.2 198 414.3 195.3C396.2 188.2 356.7 188.5 331.2 188.8C327.1 188.8 323.3 188.9 320 188.9C316.7 188.9 312.8 188.9 308.6 188.8C283.1 188.5 243.8 188.1 225.7 195.3C218.8 198 212.6 202.1 207.3 207.3C202 212.5 198 218.8 195.3 225.7C188.2 243.8 188.6 283.4 188.8 308.9C188.8 313 188.9 316.8 188.9 320C188.9 323.2 188.9 327 188.8 331.1C188.6 356.6 188.2 396.2 195.3 414.3C198 421.2 202.1 427.4 207.3 432.7C212.5 438 218.8 442 225.7 444.7C243.8 451.8 283.3 451.5 308.8 451.2C312.9 451.2 316.7 451.1 320 451.1C323.3 451.1 327.2 451.1 331.4 451.2C356.9 451.5 396.2 451.9 414.3 444.7C421.2 442 427.4 437.9 432.7 432.7C438 427.5 442 421.2 444.7 414.3C451.9 396.3 451.5 356.9 451.2 331.3C451.2 327.1 451.1 323.2 451.1 319.9C451.1 316.6 451.1 312.8 451.2 308.5C451.5 283 451.9 243.6 444.7 225.5C442 218.6 437.9 212.4 432.7 207.1L432.7 207.3zM365.6 251.8C383.7 263.9 396.2 282.7 400.5 304C404.8 325.3 400.3 347.5 388.2 365.6C382.2 374.6 374.5 382.2 365.6 388.2C356.7 394.2 346.6 398.3 336 400.4C314.7 404.6 292.5 400.2 274.4 388.1C256.3 376 243.8 357.2 239.5 335.9C235.2 314.6 239.7 292.4 251.7 274.3C263.7 256.2 282.6 243.7 303.9 239.4C325.2 235.1 347.4 239.6 365.5 251.6L365.6 251.6zM394.8 250.5C391.7 248.4 389.2 245.4 387.7 241.9C386.2 238.4 385.9 234.6 386.6 230.8C387.3 227 389.2 223.7 391.8 221C394.4 218.3 397.9 216.5 401.6 215.8C405.3 215.1 409.2 215.4 412.7 216.9C416.2 218.4 419.2 220.8 421.3 223.9C423.4 227 424.5 230.7 424.5 234.5C424.5 237 424 239.5 423.1 241.8C422.2 244.1 420.7 246.2 419 248C417.3 249.8 415.1 251.2 412.8 252.2C410.5 253.2 408 253.7 405.5 253.7C401.7 253.7 398 252.6 394.9 250.5L394.8 250.5zM544 160C544 124.7 515.3 96 480 96L160 96C124.7 96 96 124.7 96 160L96 480C96 515.3 124.7 544 160 544L480 544C515.3 544 544 515.3 544 480L544 160zM453 453C434.3 471.7 411.6 477.6 386 478.9C359.6 480.4 280.4 480.4 254 478.9C228.4 477.6 205.7 471.7 187 453C168.3 434.3 162.4 411.6 161.2 386C159.7 359.6 159.7 280.4 161.2 254C162.5 228.4 168.3 205.7 187 187C205.7 168.3 228.5 162.4 254 161.2C280.4 159.7 359.6 159.7 386 161.2C411.6 162.5 434.3 168.3 453 187C471.7 205.7 477.6 228.4 478.8 254C480.3 280.3 480.3 359.4 478.8 385.9C477.5 411.5 471.7 434.2 453 452.9L453 453z"/></svg>',
                        'TikTok' => '<svg class="h-5 w-5" fill="currentColor" viewBox="0 0 640 640" aria-hidden="true"><path d="M544.5 273.9C500.5 274 457.5 260.3 421.7 234.7L421.7 413.4C421.7 446.5 411.6 478.8 392.7 506C373.8 533.2 347.1 554 316.1 565.6C285.1 577.2 251.3 579.1 219.2 570.9C187.1 562.7 158.3 545 136.5 520.1C114.7 495.2 101.2 464.1 97.5 431.2C93.8 398.3 100.4 365.1 116.1 336C131.8 306.9 156.1 283.3 185.7 268.3C215.3 253.3 248.6 247.8 281.4 252.3L281.4 342.2C266.4 337.5 250.3 337.6 235.4 342.6C220.5 347.6 207.5 357.2 198.4 369.9C189.3 382.6 184.4 398 184.5 413.8C184.6 429.6 189.7 444.8 199 457.5C208.3 470.2 221.4 479.6 236.4 484.4C251.4 489.2 267.5 489.2 282.4 484.3C297.3 479.4 310.4 469.9 319.6 457.2C328.8 444.5 333.8 429.1 333.8 413.4L333.8 64L421.8 64C421.7 71.4 422.4 78.9 423.7 86.2C426.8 102.5 433.1 118.1 442.4 131.9C451.7 145.7 463.7 157.5 477.6 166.5C497.5 179.6 520.8 186.6 544.6 186.6L544.6 274z"/></svg>',
                        'YouTube' => '<svg class="h-5 w-5" fill="currentColor" viewBox="0 0 640 640" aria-hidden="true"><path d="M378 320.2L282.8 266.1L282.8 374.3L378 320.2zM480 96L160 96C124.7 96 96 124.7 96 160L96 480C96 515.3 124.7 544 160 544L480 544C515.3 544 544 515.3 544 480L544 160C544 124.7 515.3 96 480 96zM494.4 232.1C502 260.7 502 320.3 502 320.3C502 320.3 502 379.9 494.4 408.4C490.2 424.2 477.9 436.1 462.2 440.3C433.9 448 320 448 320 448C320 448 206.1 448 177.8 440.4C162.1 436.2 149.8 424.3 145.6 408.5C138 379.9 138 320.3 138 320.3C138 320.3 138 260.6 145.6 232.1C149.8 216.3 162.1 203.9 177.8 199.7C206.1 192 320 192 320 192C320 192 433.9 192 462.2 199.7C477.9 203.9 490.2 216.3 494.4 232.1z"/></svg>',
                        'X' => '<svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 7.56 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.09L1.15 2.25h6.763l4.717 6.223L18.244 2.25zm-1.162 17.52h1.833L7.084 4.126H5.117L17.082 19.77z"/></svg>',
                        'LinkedIn' => '<svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path fill-rule="evenodd" d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.779-1.75-1.75s.784-1.75 1.75-1.75 1.75.779 1.75 1.75-.784 1.75-1.75 1.75zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z" clip-rule="evenodd" /></svg>',
                    ];
                    ?>
                    <div class="mt-4 flex flex-wrap gap-2.5" role="group" aria-label="Redes sociales oficiales del hospital">
                        <?php foreach ($redes as $red => $url) { ?>
                            <?php if (isset($iconosRedes[$red])) { ?>
                                <a class="flex h-10 w-10 items-center justify-center rounded-full bg-white/5 border border-white/10 text-primario-100 hover:text-white hover:bg-white/10 hover:border-white/25 transition-all duration-300 hover:scale-110 shadow-md" 
                                   href="<?= $e($url) ?>" rel="noopener" target="_blank" title="Visitar nuestro perfil de <?= $e($red) ?> en una nueva pestaña">
                                    <?= $iconosRedes[$red] ?>
                                    <span class="sr-only"><?= $e($red) ?></span>
                                </a>
                            <?php } ?>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </div>
        <!-- Barra Inferior de Derechos con GOV.CO y Marca País CO (Anexo 2, 2.2.1(1)) -->
        <div class="border-t border-primario-800">
            <div class="mx-auto max-w-7xl px-4 py-6 flex flex-col md:flex-row items-center justify-between gap-4">
                <p class="text-sm text-primario-200 flex flex-col gap-1 text-center md:text-left">
                    <span>© <?= date('Y') ?> <?= $e($nombreEntidad) ?>. <?= $e($config->texto('pie.derechos', 'Todos los derechos reservados.')) ?></span>
                    <span><?= $e($config->texto('pie.leyenda_normativa', 'Cumplimos con la Ley 1712 de 2014 y la Resolución 1519 de 2020.')) ?></span>
                </p>
                <?php /* GOV.CO + Marca País CO–Colombia + «Conoce a GOV.CO»:
                         la Auditoría V9 (secciones 10 y 16) señaló la Marca
                         País ausente, con sustento en el Anexo 2, numeral
                         2.2.1(1), y el enlace textual del estándar gráfico de
                         2022. La marca va sobre una pastilla blanca porque su
                         versión oficial es multicolor y sobre el azul del pie
                         perdería sus colores institucionales. */ ?>
                <div class="flex flex-wrap items-center justify-center gap-x-5 gap-y-3">
                    <a href="<?= $e($govcoUrl) ?>" target="_blank" rel="noopener" class="hover:opacity-85 transition-opacity" title="Ir a <?= $e($govcoLeyenda) ?> GOV.CO">
                        <img src="<?= $e($govcoLogo) ?>" alt="Logotipo Oficial GOV.CO" class="h-8 w-auto object-contain" width="160" height="32">
                    </a>
                    <a href="<?= $e($config->texto('marca_pais.url', 'https://www.colombia.co/')) ?>" target="_blank" rel="noopener" class="inline-flex items-center rounded-lg bg-white p-1 hover:opacity-85 transition-opacity" title="Ir al sitio oficial de la Marca País Colombia">
                        <img src="<?= $e($config->texto('marca_pais.logo', '/img/marca_co.svg')) ?>" alt="Marca País CO Colombia" class="h-9 w-auto object-contain" width="37" height="36">
                    </a>
                    <a class="text-sm font-semibold text-primario-100 no-underline hover:underline" href="<?= $e($govcoUrl) ?>" target="_blank" rel="noopener">Conoce a GOV.CO</a>
                </div>
            </div>
        </div>
    </footer>

    <?php /* Aquí vivía el «chatbot Rosario». Se retira, no se arregla:
             respondía con cuatro textos escritos a mano dentro del atributo
             —ante cualquier otra pregunta contestaba «no comprendo»— y
             repartía teléfonos y correo que nadie verificó contra los datos
             institucionales. Además usaba expresiones de Alpine dentro del
             HTML, que la compilación CSP del sitio no evalúa: al cargar
             Alpine de verdad (2026-09-15) la consola se llenó de errores y el
             panel del chat quedaba abierto en todas las páginas, porque su
             `style="display:none"` lo bloquea la CSP.

             El asistente de IA real existe y vive en el panel (Fase 8). Si se
             quiere una orientación al ciudadano, se conecta contra ese
             servicio con su registro de uso, no con respuestas de plantilla. */ ?>

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

    <?php if ($popup !== null) { ?>
        <?php /* Ventana emergente administrable (Panel → Ventanas emergentes).

                 Es un <dialog> y no un div con posición fija: el navegador ya
                 sabe atrapar el foco dentro, cerrarlo con Escape, volver
                 inerte el resto de la página y pintar el fondo con ::backdrop.
                 Rehacer todo eso a mano es la vía más corta a un modal que un
                 lector de pantalla atraviesa como si no existiera.

                 Nace cerrado y lo abre /js/popup.js, que es quien sabe si este
                 visitante ya lo cerró. Sin JavaScript no se ve nada —ni un
                 bloque suelto a mitad de página—, que es exactamente lo que
                 debe pasar con algo accesorio. */ ?>
        <dialog id="popup" class="popup" aria-labelledby="popup-titulo"
                data-clave="<?= $e($popup->clave()) ?>"
                data-frecuencia="<?= $e($popup->frecuencia) ?>">
            <form method="dialog" class="popup-cerrar-fila">
                <button class="popup-cerrar" value="cerrar" aria-label="Cerrar la ventana">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                         stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </form>

            <?php if ($popup->imagenId !== null) { ?>
                <img class="popup-imagen" src="/archivos/<?= $popup->imagenId ?>" alt=""
                     width="1200" height="630" loading="lazy">
            <?php } ?>

            <div class="popup-cuerpo">
                <h2 id="popup-titulo" class="text-xl font-extrabold texto-titular"><?= $e($popup->titulo) ?></h2>
                <p class="mt-2 texto-suave whitespace-pre-line"><?= $e($popup->mensaje) ?></p>

                <div class="mt-5 flex flex-wrap gap-3">
                    <?php if ($popup->tieneBoton()) { ?>
                        <a class="boton boton-primario" href="<?= $e((string) $popup->url) ?>">
                            <?= $e($popup->etiquetaBoton()) ?>
                        </a>
                    <?php } ?>
                    <form method="dialog">
                        <button class="boton boton-secundario" value="cerrar">Cerrar</button>
                    </form>
                </div>
            </div>
        </dialog>
        <script src="<?= $e(Recursos::url('/js/popup.js')) ?>" defer></script>
    <?php } ?>

    <?php /* --- Orientador del portal (rediseño 2026-11-17) ---
             Qué es y qué NO es, porque la diferencia importa:

             Es un enrutador guiado. Enseña los mismos accesos que administra
             el hospital en Panel → Menús (ubicación «accesos») y, si nada
             sirve, el teléfono de Atención al Usuario. Nada más.

             NO es un chatbot. No hay modelo de lenguaje, no hay campo de
             texto libre, no pregunta síntomas y no responde consultas
             clínicas. `CLAUDE.md` §7 deja «chatbot de cara al ciudadano»
             fuera de v1 y §2.6 prohíbe que un dato clínico entre al sistema:
             un árbol de decisión cerrado sobre rutas ya publicadas no cruza
             ninguna de las dos líneas.

             Sin JavaScript no se pinta: sería un botón que no abre nada. Los
             mismos accesos ya están en la portada y en el pie, así que no se
             pierde ninguna ruta. */ ?>
    <?php if ($menuAccesos !== []) { ?>
        <div class="orientador" id="orientador" data-abierto="no" hidden
             role="dialog" aria-label="Orientador del portal" aria-modal="false">
            <div class="orientador-cabeza">
                <img src="<?= $e($logoEntidad) ?>" alt="" width="38" height="38">
                <span class="orientador-rotulo">
                    <b>Orientador del portal</b>
                    <span>Te ayuda a encontrar lo que buscas</span>
                </span>
                <button type="button" aria-label="Cerrar el orientador" data-orientador-cerrar>
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </button>
            </div>

            <div class="orientador-hilo" id="orientador-hilo" aria-live="polite">
                <p class="burbuja asistente">
                    Hola. Te ayudo a encontrar un trámite, un teléfono o una página
                    de este portal. <strong>No doy información médica ni resultados.</strong>
                    ¿Qué necesitas?
                </p>
            </div>

            <?php /* Las opciones se pintan en el HTML, no desde JavaScript:
                     así el contenido lo manda el servidor —y el panel— y el
                     JS solo mueve lo que ya está escrito. */ ?>
            <div class="orientador-opciones" id="orientador-opciones">
                <?php foreach ($menuAccesos as $acceso) { ?>
                    <button type="button"
                            data-orientador-opcion
                            data-url="<?= $e($acceso['url']) ?>"
                            data-detalle="<?= $e($acceso['descripcion'] ?? '') ?>">
                        <?= $e($acceso['etiqueta']) ?>
                    </button>
                <?php } ?>
                <?php /* Salida siempre disponible: cuando ninguna ruta sirve,
                         lo que toca es una persona, no otra pantalla. */ ?>
                <button type="button"
                        data-orientador-opcion
                        data-url="/contacto"
                        data-detalle="Atención al Usuario (SIAU) te atiende en el PBX <?= $e($config->texto('contacto.pbx')) ?> y en <?= $e($config->texto('contacto.correo')) ?>. <?= $e($config->texto('contacto.horario')) ?>">
                    Hablar con una persona
                </button>
            </div>

            <p class="orientador-aviso">
                Canal de orientación, no de atención en salud. Si se trata de una
                urgencia médica, llama al
                <a href="tel:+<?= $e(preg_replace('/\D+/', '', $config->texto('contacto.emergencias'))) ?>"><?= $e($config->texto('contacto.emergencias')) ?></a>
                o acude al servicio de urgencias.
            </p>
        </div>

        <button class="orientador-boton" type="button" id="orientador-abrir" hidden
                aria-expanded="false" aria-controls="orientador">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 15a2 2 0 01-2 2H8l-4 4V5a2 2 0 012-2h12a2 2 0 012 2z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M8.5 9h7M8.5 12.5h4.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            <span class="orientador-boton-texto">¿Te orientamos?</span>
        </button>

        <script src="<?= $e(Recursos::url('/js/orientador.js')) ?>" defer></script>
    <?php } ?>
</body>
</html>

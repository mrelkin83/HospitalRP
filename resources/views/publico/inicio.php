<?php
/**
 * Home. Lenguaje visual premium pro max.
 * Héroe configurable desde panel, con animaciones y transiciones avanzadas.
 */

use App\Core\Support\Fechas;

// Accesos
$accesos = [
    ['Citas', 'Solicita o consulta tu cita médica', '/citas',
        'M8 2v3M16 2v3M3.5 9h17M5 4.5h14a1.5 1.5 0 0 1 1.5 1.5v13A1.5 1.5 0 0 1 19 20.5H5A1.5 1.5 0 0 1 3.5 19V6A1.5 1.5 0 0 1 5 4.5Z',
        'bg-white text-primario-700 dark:bg-primario-900 dark:text-primario-100'],
    ['Urgencias', 'Atención inmediata 24/7', '/urgencias',
        'M12 4v16M4 12h16',
        'bg-error-100 text-error-600 dark:bg-error-900 dark:text-white'],
    ['Especialidades', 'Conoce nuestras especialidades', '/especialidades',
        'M6 3v6a6 6 0 0 0 12 0V3M12 15v3a3.5 3.5 0 0 0 7 0v-2',
        'bg-exito-100 text-exito-600 dark:bg-exito-900 dark:text-white'],
    ['Directorio médico', 'Profesionales a tu disposición', '/directorio',
        'M12 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM5 20.5a7 7 0 0 1 14 0',
        'bg-white text-primario-700 dark:bg-primario-900 dark:text-primario-100'],
    ['Trámites', 'Información y requisitos', '/atencion-ciudadania/tramites',
        'M7 3.5h7L18.5 8v12a1 1 0 0 1-1 1h-10.5a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1ZM14 3.5V8h4.5M8.5 12.5h7M8.5 16h7',
        'bg-aviso-100 text-aviso-600 dark:bg-aviso-900 dark:text-white'],
    ['Consulta tu trámite', 'Sigue tu radicado en línea', '/atencion-ciudadania/pqrsdf/consultar',
        'M10.5 17a6.5 6.5 0 1 0 0-13 6.5 6.5 0 0 0 0 13ZM15.5 15.5 21 21',
        'bg-white text-primario-700 dark:bg-primario-900 dark:text-primario-100'],
    ['PQRSDF', 'Peticiones, quejas, reclamos y más', '/atencion-ciudadania/pqrsdf',
        'M4 5.5h16v11H9l-5 4v-15ZM8 9.5h8M8 12.5h5',
        'bg-white text-primario-700 dark:bg-primario-900 dark:text-primario-100'],
    ['Transparencia', 'Información pública y rendición de cuentas', '/transparencia',
        'M12 3 5 6v5c0 4.5 3 8.5 7 9.5 4-1 7-5 7-9.5V6l-7-3ZM9 12l2 2 4-4',
        'bg-exito-100 text-exito-600 dark:bg-exito-900 dark:text-white'],
];

// Valores del Héroe configurables desde el panel
$heroPildora = $config->texto('hero.pildora', 'Atención humana y segura');
$heroTitulo1 = $config->texto('hero.titulo1', 'Tu salud,');
$heroTituloDegradado = $config->texto('hero.titulo_degradado', 'nuestra prioridad');
$heroEslogan = $config->texto('hero.eslogan', 'En la ' . $config->texto('entidad.nombre') . ' brindamos atención médica de calidad, humana y segura, con la mejor tecnología y un equipo comprometido contigo.');
$heroBoton1 = $config->texto('hero.boton1', 'Solicitar cita');
$heroEnlace1 = $config->texto('hero.enlace1', '/citas');
$heroBoton2 = $config->texto('hero.boton2', 'Conoce nuestros servicios');
$heroEnlace2 = $config->texto('hero.enlace2', '/servicios');
?>

<section class="relative overflow-hidden bg-gradiente-premium" aria-labelledby="heroe-titulo">
    <div class="pointer-events-none absolute inset-0 hidden lg:block" aria-hidden="true">
        <span class="mancha h-[500px] w-[500px] -top-20 -left-20 bg-[color:var(--color-marca-cian)] opacity-20"></span>
        <span class="mancha h-[600px] w-[600px] top-20 -right-10 bg-primario-500 opacity-15"></span>
    </div>

    <div class="relative mx-auto max-w-7xl px-4 py-16 lg:py-24 grid gap-10 lg:grid-cols-2 lg:items-center">
        <div class="animar-fade-in-up" style="animation-delay: 0.1s;">
            <p class="inline-flex items-center gap-2 rounded-full border border-primario-200 bg-[color:var(--fondo)]/80 backdrop-blur-md px-4 py-1.5 text-sm font-semibold text-primario-700 dark:border-primario-700 dark:text-primario-100 shadow-sm">
                <span class="relative flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[color:var(--color-marca-cian)] opacity-75" aria-hidden="true"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-[color:var(--color-marca-cian)]" aria-hidden="true"></span>
                </span>
                <?= $e($heroPildora) ?>
            </p>

            <h1 id="heroe-titulo" class="mt-6 font-extrabold tracking-tight">
                <?= $e($heroTitulo1) ?> <span class="texto-degradado block sm:inline"><?= $e($heroTituloDegradado) ?></span>
            </h1>

            <p class="mt-6 text-xl texto-suave max-w-prose leading-relaxed">
                <?= $e($heroEslogan) ?>
            </p>

            <p class="mt-8 flex flex-wrap gap-4">
                <a class="boton boton-primario text-lg px-8 py-3 rounded-xl shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all" href="<?= $e($heroEnlace1) ?>"><?= $e($heroBoton1) ?></a>
                <a class="boton boton-secundario text-lg px-8 py-3 rounded-xl hover:bg-primario-50 dark:hover:bg-primario-800 transition-all" href="<?= $e($heroEnlace2) ?>"><?= $e($heroBoton2) ?></a>
            </p>

            <form class="mt-10 flex max-w-md bg-[color:var(--fondo)] rounded-2xl shadow-sm border border-primario-100 dark:border-primario-800 p-1 focus-within:ring-2 focus-within:ring-primario-500 transition-shadow" action="/buscar" method="get" role="search">
                <label class="sr-only" for="buscar-q">¿Qué estás buscando?</label>
                <div class="flex-1 flex items-center px-4">
                    <svg class="h-5 w-5 text-primario-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" /></svg>
                    <input class="w-full bg-transparent border-0 focus:ring-0 py-2.5 px-3 text-primario-900 dark:text-primario-100 placeholder-primario-400" type="search" id="buscar-q" name="q" placeholder="Buscar trámites, médicos, noticias...">
                </div>
                <button type="submit" class="boton boton-primario rounded-xl px-6 py-2.5 m-0.5">Buscar</button>
            </form>
        </div>

        <div class="hidden lg:block relative animar-fade-in-up" style="animation-delay: 0.3s;">
            <div class="relative mx-auto w-full max-w-xl h-[480px] flex items-center justify-center">
                <!-- Capa 1: Fondo holográfico de marca -->
                <div class="absolute inset-0 rounded-[2.5rem] bg-gradient-to-tr from-primario-900 via-primario-700 to-primario-800 shadow-3xl overflow-hidden border border-white/10" aria-hidden="true">
                    <!-- Cuadrícula médica/digital de fondo -->
                    <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.05)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.05)_1px,transparent_1px)] bg-[size:24px_24px] opacity-40"></div>
                    <!-- Resplandor ambiental -->
                    <div class="absolute -top-20 -left-20 w-64 h-64 bg-[color:var(--color-marca-cian)]/10 rounded-full blur-3xl"></div>
                    <div class="absolute -bottom-20 -right-20 w-64 h-64 bg-primario-500/20 rounded-full blur-3xl"></div>
                </div>

                <!-- Capa 2: Tarjeta Principal del Portal (Simulación de Dashboard de Paciente) -->
                <div class="panel-vidrio absolute w-[85%] h-[75%] top-[10%] left-[5%] rounded-3xl p-6 shadow-2xl border-white/20 flex flex-col justify-between backdrop-blur-xl">
                    <div class="flex items-center justify-between border-b border-white/10 pb-4">
                        <div class="flex items-center gap-3">
                            <span class="h-3 w-3 rounded-full bg-[color:var(--color-marca-cian)] animate-pulse"></span>
                            <span class="text-xs font-bold uppercase tracking-widest text-primario-200">Ecosistema Digital de Salud</span>
                        </div>
                        <span class="text-[10px] bg-white/10 px-2 py-1 rounded-md text-white/80 font-mono">PORTAL V2.6</span>
                    </div>

                    <!-- Gráfico simulado con CSS -->
                    <div class="my-4 flex-1 flex flex-col justify-center">
                        <span class="text-3xl font-extrabold text-white leading-tight">Rosario Digital</span>
                        <span class="text-sm text-primario-200 mt-1 block">Tu portal clínico unificado e interactivo.</span>
                        
                        <!-- Barras de estadísticas premium -->
                        <div class="mt-6 space-y-3">
                            <div>
                                <div class="flex justify-between text-xs text-primario-100 mb-1 font-medium">
                                    <span>Satisfacción del Paciente</span>
                                    <span>98.4%</span>
                                </div>
                                <div class="w-full bg-white/10 h-2 rounded-full overflow-hidden">
                                    <div class="bg-gradient-to-r from-[color:var(--color-marca-cian)] to-primario-400 h-full rounded-full" style="width: 98.4%;"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-xs text-primario-100 mb-1 font-medium">
                                    <span>Disponibilidad Médica</span>
                                    <span>95%</span>
                                </div>
                                <div class="w-full bg-white/10 h-2 rounded-full overflow-hidden">
                                    <div class="bg-gradient-to-r from-[color:var(--color-marca-cian)] to-primario-400 h-full rounded-full" style="width: 95%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 text-xs text-primario-100 bg-white/5 p-3 rounded-xl border border-white/5">
                        <svg class="h-5 w-5 text-[color:var(--color-marca-cian)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                        <span>Protección de datos conforme a la Ley 1581</span>
                    </div>
                </div>

                <!-- Capa 3: Tarjeta Flotante Pequeña (Widget de Atención Activa) -->
                <div class="absolute -bottom-4 -right-4 bg-white dark:bg-primario-900 border border-primario-100 dark:border-primario-800 rounded-2xl p-4 shadow-3xl flex items-center gap-3 animar-escala-hover cursor-pointer z-20">
                    <span class="relative flex h-3 w-3 shrink-0">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-exito-500 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-exito-500"></span>
                    </span>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-exito-600 dark:text-exito-400">Atención en Sede</span>
                        <span class="block text-sm font-extrabold text-primario-900 dark:text-white">Normal (Sin Esperas)</span>
                    </div>
                </div>

                <!-- Capa 4: Mini-widget de Citas Médicas -->
                <div class="absolute -top-6 -right-6 bg-white dark:bg-primario-900 border border-primario-100 dark:border-primario-800 rounded-2xl p-4 shadow-3xl flex items-center gap-3 animar-escala-hover cursor-pointer z-20">
                    <span class="p-2 bg-primario-50 dark:bg-primario-800 rounded-xl text-primario-600 dark:text-primario-200">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                    </span>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-primario-500">Próxima Cita</span>
                        <span class="block text-sm font-extrabold text-primario-900 dark:text-white">Agendar en 2 Min</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="relative z-10 mx-auto -mt-12 max-w-7xl px-4 pb-12" aria-labelledby="accesos-titulo">
    <h2 id="accesos-titulo" class="sr-only">Accesos rápidos</h2>
    <ul class="grid gap-5 grid-cols-2 md:grid-cols-4 animar-fade-in" style="animation-delay: 0.5s;">
        <?php foreach ($accesos as [$titulo, $descripcion, $url, $trazo, $azulejo]) { ?>
            <li>
                <a class="tarjeta-acceso group flex h-full flex-col gap-4 rounded-3xl p-6 no-underline transition-all duration-300 hover:-translate-y-2 hover:shadow-[0_20px_40px_-15px_rgba(10,25,47,0.15)] dark:hover:shadow-[0_20px_40px_-15px_rgba(0,0,0,0.5)] border border-primario-200/50 dark:border-primario-700/50 bg-[color:var(--fondo)]/80 backdrop-blur-sm" href="<?= $e($url) ?>">
                    <span class="grid h-14 w-14 place-items-center rounded-2xl <?= $e($azulejo) ?> shadow-sm group-hover:scale-110 transition-transform duration-300" aria-hidden="true">
                        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="<?= $e($trazo) ?>"/>
                        </svg>
                    </span>
                    <div>
                        <span class="block font-bold text-lg texto-titular group-hover:text-primario-600 dark:group-hover:text-primario-400 transition-colors"><?= $e($titulo) ?></span>
                        <span class="block mt-1 text-sm texto-suave leading-snug"><?= $e($descripcion) ?></span>
                    </div>
                </a>
            </li>
        <?php } ?>
    </ul>
</section>

<section class="mx-auto max-w-7xl px-4 py-16" aria-labelledby="bento-titulo">
    <h2 id="bento-titulo" class="sr-only">Urgencias, noticias y sedes</h2>

    <div class="grid gap-8 lg:grid-cols-3">
        <article class="relative overflow-hidden rounded-[2.5rem] bg-gradient-to-br from-primario-800 via-primario-700 to-primario-900 p-10 text-white shadow-2xl animar-escala-hover group">
            <span class="mancha h-72 w-72 -right-10 -top-10 bg-[color:var(--color-marca-cian)] opacity-30 group-hover:opacity-40 transition-opacity duration-500 blur-3xl" aria-hidden="true"></span>
            <div class="relative z-10 flex flex-col h-full justify-between">
                <div>
                    <span class="inline-flex items-center justify-center p-3 bg-white/10 rounded-2xl backdrop-blur-md mb-6 shadow-inner">
                        <svg class="h-10 w-10 text-[color:var(--color-marca-cian)]" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 8v5M12 16.5v.01M12 3.5 21 20H3l9-16.5Z"/>
                        </svg>
                    </span>
                    <h3 class="text-white text-3xl font-extrabold tracking-tight">Urgencias 24h</h3>
                    <p class="mt-4 text-primario-100 text-lg leading-relaxed">
                        Atención prioritaria y especializada sin esperas. Llámanos o acércate a nuestra sede principal.
                    </p>
                </div>
                <div class="mt-10 flex flex-col sm:flex-row gap-4">
                    <a class="boton bg-white text-primario-800 font-bold shadow-lg hover:shadow-xl hover:bg-primario-50 transition-all text-lg py-3 flex-1 justify-center"
                       href="tel:<?= $e(preg_replace('/\D+/', '', $config->texto('contacto.emergencias'))) ?>">
                        <svg class="h-5 w-5 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                        <?= $e($config->texto('contacto.emergencias')) ?>
                    </a>
                    <a class="boton border-2 border-white/30 hover:border-white text-white no-underline transition-all bg-white/5 hover:bg-white/10 text-lg py-3 flex-1 justify-center" href="/urgencias">Ruta de acceso</a>
                </div>
            </div>
        </article>

        <div class="lg:col-span-2 grid gap-8 sm:grid-cols-2">
            <div class="panel-vidrio rounded-[2.5rem] p-8 shadow-lg hover:shadow-xl transition-shadow border-white/40 dark:border-primario-700/40">
                <div class="flex items-center justify-between border-b border-primario-100 dark:border-primario-800 pb-4 mb-6">
                    <h3 id="noticias-titulo" class="text-xl font-bold flex items-center gap-2">
                        <svg class="h-6 w-6 text-primario-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2M18 14h-8M15 18h-5"/></svg>
                        Noticias recientes
                    </h3>
                    <a class="text-sm font-semibold text-primario-600 hover:text-primario-700 dark:text-primario-400 hover:underline inline-flex items-center" href="/noticias">Ver todas <svg class="ml-1 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
                </div>
                <?php if ($noticias === []) { ?>
                    <p class="mt-4 text-sm texto-suave">Aún no hay noticias publicadas.</p>
                <?php } else { ?>
                    <ul class="flex flex-col gap-6" aria-labelledby="noticias-titulo">
                        <?php foreach (array_slice($noticias, 0, 2) as $noticia) { ?>
                            <li class="group">
                                <article class="flex flex-col gap-3">
                                    <?php if ($noticia->imagenId !== null) { ?>
                                        <div class="overflow-hidden rounded-2xl relative aspect-[16/9]">
                                            <img class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105" src="/archivos/<?= $noticia->imagenId ?>"
                                                 alt="<?= $e($imagenes[$noticia->imagenId]->textoAlt ?? '') ?>"
                                                 loading="lazy" width="600" height="337">
                                            <div class="absolute inset-0 ring-1 ring-inset ring-black/10 rounded-2xl"></div>
                                        </div>
                                    <?php } ?>
                                    <div>
                                        <p class="text-xs font-semibold text-primario-500 uppercase tracking-wider mb-1"><?= $e(Fechas::larga($noticia->publicarEn ?? $noticia->creadoEn)) ?></p>
                                        <h4 class="text-lg font-bold leading-tight">
                                            <a class="texto-titular no-underline group-hover:text-primario-600 transition-colors" href="/noticias/<?= $e($noticia->slug) ?>">
                                                <?= $e($noticia->titulo) ?>
                                            </a>
                                        </h4>
                                    </div>
                                </article>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </div>

            <div class="panel-vidrio rounded-[2.5rem] p-8 shadow-lg hover:shadow-xl transition-shadow border-white/40 dark:border-primario-700/40">
                <div class="flex items-center justify-between border-b border-primario-100 dark:border-primario-800 pb-4 mb-6">
                    <h3 id="sedes-titulo" class="text-xl font-bold flex items-center gap-2">
                        <svg class="h-6 w-6 text-primario-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        <?= count($sedes) === 1 ? 'Nuestra sede' : 'Nuestras sedes' ?>
                    </h3>
                    <?php if (count($sedes) > 1) { ?>
                        <a class="text-sm font-semibold text-primario-600 hover:text-primario-700 dark:text-primario-400 hover:underline inline-flex items-center" href="/sedes">Ver todas <svg class="ml-1 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
                    <?php } ?>
                </div>
                <?php if ($sedes === []) { ?>
                    <p class="mt-4 text-sm texto-suave">
                        Consulta nuestra dirección y horarios en <a href="/contacto" class="text-primario-600 font-semibold hover:underline">contacto</a>.
                    </p>
                <?php } else { ?>
                    <ul class="flex flex-col gap-4" aria-labelledby="sedes-titulo">
                        <?php foreach ($sedes as $sede) { ?>
                            <li class="transform transition-transform hover:-translate-y-1">
                                <?= $vistas->render('publico/parciales/tarjeta-sede', ['sede' => $sede]) ?>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </div>
        </div>
    </div>
</section>

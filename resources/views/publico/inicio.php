<?php
/**
 * Home (Fase 1, paso 9), según frontend.png: héroe, buscador, ocho accesos,
 * noticias recientes. La franja de sedes llega con la Fase 2.
 *
 * Nota de alcance: el diseño traía un acceso «Resultados»; los resultados de
 * laboratorio son dato clínico y están fuera de v1 (CLAUDE.md §7), así que
 * ese acceso es «Consulta tu trámite» (radicado), que sí existe.
 *
 * Espera: $noticias (list<Noticia>), $config (ConfigDinamica),
 *         $imagenes (array<int, Archivo> imágenes de las noticias por id),
 *         $sedes (list<Sede>, máximo 3 para la franja).
 */

use App\Core\Support\Fechas;

$accesos = [
    ['Citas', 'Solicita o consulta tu cita médica', '/citas',
        'M8 2v3M16 2v3M3.5 9h17M5 4.5h14a1.5 1.5 0 0 1 1.5 1.5v13A1.5 1.5 0 0 1 19 20.5H5A1.5 1.5 0 0 1 3.5 19V6A1.5 1.5 0 0 1 5 4.5Z'],
    ['Urgencias', 'Atención inmediata 24/7', '/urgencias',
        'M12 4v16M4 12h16'],
    ['Especialidades', 'Conoce nuestras especialidades', '/especialidades',
        'M6 3v6a6 6 0 0 0 12 0V3M12 15v3a3.5 3.5 0 0 0 7 0v-2'],
    ['Directorio médico', 'Profesionales a tu disposición', '/directorio',
        'M12 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM5 20.5a7 7 0 0 1 14 0'],
    ['Trámites', 'Información y requisitos', '/atencion-ciudadania/tramites',
        'M7 3.5h7L18.5 8v12a1 1 0 0 1-1 1h-10.5a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1ZM14 3.5V8h4.5M8.5 12.5h7M8.5 16h7'],
    ['Consulta tu trámite', 'Sigue tu radicado en línea', '/atencion-ciudadania/pqrsdf/consultar',
        'M10.5 17a6.5 6.5 0 1 0 0-13 6.5 6.5 0 0 0 0 13ZM15.5 15.5 21 21'],
    ['PQRSDF', 'Peticiones, quejas, reclamos y más', '/atencion-ciudadania/pqrsdf',
        'M4 5.5h16v11H9l-5 4v-15ZM8 9.5h8M8 12.5h5'],
    ['Transparencia', 'Información pública y rendición de cuentas', '/transparencia',
        'M12 3 5 6v5c0 4.5 3 8.5 7 9.5 4-1 7-5 7-9.5V6l-7-3ZM9 12l2 2 4-4'],
];
?>
<?php /* --- Héroe --- */ ?>
<section class="bg-[color:var(--superficie)]" aria-labelledby="heroe-titulo">
    <div class="mx-auto max-w-6xl px-4 py-10 lg:py-16 grid gap-10 lg:grid-cols-2 lg:items-center">
        <div>
            <h1 id="heroe-titulo">Tu salud, <span class="text-primario-500">nuestra prioridad</span></h1>
            <p class="mt-4 text-lg texto-suave max-w-prose">
                En la <?= $e($config->texto('entidad.nombre')) ?> brindamos atención médica de
                calidad, humana y segura, con la mejor tecnología y un equipo comprometido contigo.
            </p>
            <p class="mt-6 flex flex-wrap gap-3">
                <a class="boton boton-primario" href="/citas">Solicitar cita</a>
                <a class="boton boton-secundario" href="/servicios">Conoce nuestros servicios</a>
            </p>

            <form class="mt-8 flex max-w-md" action="/buscar" method="get" role="search">
                <label class="sr-only" for="buscar-q">¿Qué estás buscando?</label>
                <input class="campo rounded-e-none" type="search" id="buscar-q" name="q"
                       placeholder="¿Qué estás buscando?">
                <button type="submit" class="boton boton-primario rounded-s-none">Buscar</button>
            </form>
        </div>

        <div class="hidden lg:block" aria-hidden="true">
            <div class="relative rounded-2xl bg-gradient-to-br from-primario-500 to-primario-800 p-10 min-h-72 flex items-center justify-center">
                <svg class="h-32 w-32 text-white/90" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 3.5h6v5.5H20.5v6H15v5.5H9V15H3.5V9H9V3.5Z"/>
                </svg>
                <div class="absolute -bottom-5 right-6 tarjeta flex items-center gap-3 shadow-lg">
                    <span class="insignia insignia-exito">✓</span>
                    <span>
                        <strong class="block text-sm texto-titular">Calidad y seguridad</strong>
                        <span class="block text-sm texto-suave">Comprometidos con estándares de calidad</span>
                    </span>
                </div>
            </div>
        </div>
    </div>
</section>

<?php /* --- Ocho accesos: lo único destacado (CLAUDE.md §6) --- */ ?>
<section class="mx-auto max-w-6xl px-4 py-10" aria-labelledby="accesos-titulo">
    <h2 id="accesos-titulo" class="sr-only">Accesos rápidos</h2>
    <ul class="grid gap-3 grid-cols-2 lg:grid-cols-4">
        <?php foreach ($accesos as [$titulo, $descripcion, $url, $trazo]) { ?>
            <li>
                <a class="tarjeta flex h-full flex-col items-center gap-2 text-center no-underline hover:border-primario-500 transition-colors" href="<?= $e($url) ?>">
                    <svg class="h-8 w-8 text-primario-500" viewBox="0 0 24 24" fill="none" aria-hidden="true"
                         stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="<?= $e($trazo) ?>"/>
                    </svg>
                    <span class="font-semibold texto-titular"><?= $e($titulo) ?></span>
                    <span class="text-sm texto-suave"><?= $e($descripcion) ?></span>
                </a>
            </li>
        <?php } ?>
    </ul>
</section>

<?php /* --- Noticias recientes --- */ ?>
<?php if ($noticias !== []) { ?>
    <section class="mx-auto max-w-6xl px-4 py-6" aria-labelledby="noticias-titulo">
        <div class="flex items-baseline gap-4">
            <h2 id="noticias-titulo">Noticias recientes</h2>
            <a class="ms-auto whitespace-nowrap" href="/noticias">Ver todas →</a>
        </div>
        <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($noticias as $noticia) { ?>
                <li>
                    <article class="tarjeta h-full p-0 overflow-hidden">
                        <?php if ($noticia->imagenId !== null) { ?>
                            <img class="h-40 w-full object-cover" src="/archivos/<?= $noticia->imagenId ?>"
                                 alt="<?= $e($imagenes[$noticia->imagenId]->textoAlt ?? '') ?>"
                                 loading="lazy" width="600" height="400">
                        <?php } ?>
                        <div class="p-4">
                            <h3 class="text-base leading-snug">
                                <a class="texto-titular no-underline hover:underline" href="/noticias/<?= $e($noticia->slug) ?>">
                                    <?= $e($noticia->titulo) ?>
                                </a>
                            </h3>
                            <p class="mt-2 text-sm texto-suave"><?= $e(Fechas::larga($noticia->publicarEn ?? $noticia->creadoEn)) ?></p>
                        </div>
                    </article>
                </li>
            <?php } ?>
        </ul>
    </section>
<?php } ?>

<?php /* --- Nuestras sedes (frontend.png, bloque 4) --- */ ?>
<?php if ($sedes !== []) { ?>
    <section class="mx-auto max-w-6xl px-4 py-6 pb-12" aria-labelledby="sedes-titulo">
        <div class="flex items-baseline gap-4">
            <h2 id="sedes-titulo">Nuestras sedes</h2>
            <a class="ms-auto whitespace-nowrap" href="/sedes">Ver todas →</a>
        </div>
        <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($sedes as $sede) { ?>
                <li><?= $vistas->render('publico/parciales/tarjeta-sede', ['sede' => $sede]) ?></li>
            <?php } ?>
        </ul>
    </section>
<?php } ?>

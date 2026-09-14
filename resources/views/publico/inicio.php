<?php
/**
 * Home. Lenguaje visual del prototipo `hospital-prototype` (2026-09-14):
 * héroe con titular en degradado, accesos en paneles de vidrio y retícula
 * bento para urgencias, noticias y sedes.
 *
 * Tres cosas del prototipo NO se copiaron, y no por descuido:
 *
 * - El acceso «Resultados — consulta laboratorio»: los resultados son dato
 *   clínico y están fuera de v1 (CLAUDE.md §2.6 y §7). En su lugar va
 *   «Consulta tu trámite», que sigue un radicado y sí existe.
 * - «Agendar cita ahora»: el agendamiento real también está fuera de v1;
 *   `/citas` radica una SOLICITUD y el botón lo dice.
 * - Las fotos de Unsplash: son peticiones a un tercero que rompen el
 *   presupuesto de LCP < 2,5 s en 3G y la CSP. El héroe se dibuja con SVG
 *   y degradados, que pesan cero.
 *
 * Espera: $noticias (list<Noticia>), $config (ConfigDinamica),
 *         $imagenes (array<int, Archivo> imágenes de las noticias por id),
 *         $sedes (list<Sede>, máximo 3 para la franja).
 */

use App\Core\Support\Fechas;

// [título, descripción, ruta, trazo SVG, clases del azulejo del icono]
$accesos = [
    ['Citas', 'Solicita o consulta tu cita médica', '/citas',
        'M8 2v3M16 2v3M3.5 9h17M5 4.5h14a1.5 1.5 0 0 1 1.5 1.5v13A1.5 1.5 0 0 1 19 20.5H5A1.5 1.5 0 0 1 3.5 19V6A1.5 1.5 0 0 1 5 4.5Z',
        'bg-primario-50 text-primario-700 dark:bg-primario-800 dark:text-primario-100'],
    ['Urgencias', 'Atención inmediata 24/7', '/urgencias',
        'M12 4v16M4 12h16',
        'bg-error-100 text-error-600 dark:bg-error-900 dark:text-white'],
    ['Especialidades', 'Conoce nuestras especialidades', '/especialidades',
        'M6 3v6a6 6 0 0 0 12 0V3M12 15v3a3.5 3.5 0 0 0 7 0v-2',
        'bg-exito-100 text-exito-600 dark:bg-exito-900 dark:text-white'],
    ['Directorio médico', 'Profesionales a tu disposición', '/directorio',
        'M12 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM5 20.5a7 7 0 0 1 14 0',
        'bg-primario-50 text-primario-700 dark:bg-primario-800 dark:text-primario-100'],
    ['Trámites', 'Información y requisitos', '/atencion-ciudadania/tramites',
        'M7 3.5h7L18.5 8v12a1 1 0 0 1-1 1h-10.5a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1ZM14 3.5V8h4.5M8.5 12.5h7M8.5 16h7',
        'bg-aviso-100 text-aviso-600 dark:bg-aviso-900 dark:text-white'],
    ['Consulta tu trámite', 'Sigue tu radicado en línea', '/atencion-ciudadania/pqrsdf/consultar',
        'M10.5 17a6.5 6.5 0 1 0 0-13 6.5 6.5 0 0 0 0 13ZM15.5 15.5 21 21',
        'bg-primario-50 text-primario-700 dark:bg-primario-800 dark:text-primario-100'],
    ['PQRSDF', 'Peticiones, quejas, reclamos y más', '/atencion-ciudadania/pqrsdf',
        'M4 5.5h16v11H9l-5 4v-15ZM8 9.5h8M8 12.5h5',
        'bg-primario-50 text-primario-700 dark:bg-primario-800 dark:text-primario-100'],
    ['Transparencia', 'Información pública y rendición de cuentas', '/transparencia',
        'M12 3 5 6v5c0 4.5 3 8.5 7 9.5 4-1 7-5 7-9.5V6l-7-3ZM9 12l2 2 4-4',
        'bg-exito-100 text-exito-600 dark:bg-exito-900 dark:text-white'],
];
?>
<?php /* --- Héroe --- */ ?>
<section class="relative overflow-hidden fondo-superficie" aria-labelledby="heroe-titulo">
    <?php /* Manchas de color del prototipo. Decorativas y ocultas en móvil:
             un desenfoque grande cuesta caro en gama baja. */ ?>
    <div class="pointer-events-none absolute inset-0 hidden lg:block" aria-hidden="true">
        <span class="mancha h-72 w-72 top-10 left-10 bg-[color:var(--color-marca-cian)] opacity-15"></span>
        <span class="mancha h-96 w-96 top-24 right-10 bg-primario-500 opacity-10"></span>
    </div>

    <div class="relative mx-auto max-w-7xl px-4 py-12 lg:py-20 grid gap-10 lg:grid-cols-2 lg:items-center">
        <div>
            <p class="inline-flex items-center gap-2 rounded-full border border-primario-200 bg-[color:var(--fondo)] px-4 py-1.5 text-sm font-semibold text-primario-700 dark:border-primario-700 dark:text-primario-100">
                <span class="h-2 w-2 rounded-full bg-[color:var(--color-marca-cian)]" aria-hidden="true"></span>
                Atención humana y segura
            </p>

            <h1 id="heroe-titulo" class="mt-5">Tu salud, <span class="texto-degradado">nuestra prioridad</span></h1>

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

        <div class="hidden lg:block">
            <div class="relative mx-auto aspect-square w-full max-w-md">
                <div class="absolute inset-0 rounded-[2rem] bg-gradient-to-br from-primario-700 to-primario-500 grid place-items-center" aria-hidden="true">
                    <svg class="h-32 w-32 text-white/90" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 3.5h6v5.5H20.5v6H15v5.5H9V15H3.5V9H9V3.5Z"/>
                    </svg>
                </div>

                <?php /* Tarjeta flotante del prototipo, sin la animación de
                         rebote: un elemento que salta sin parar molesta y no
                         aporta nada. */ ?>
                <div class="panel-vidrio absolute -bottom-5 -left-5 flex items-center gap-3 rounded-2xl p-5">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-exito-100 text-exito-600 dark:bg-exito-900 dark:text-white" aria-hidden="true">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4.5 12.5 10 18 19.5 6.5"/>
                        </svg>
                    </span>
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
<section class="relative z-10 mx-auto -mt-8 max-w-7xl px-4 pb-4" aria-labelledby="accesos-titulo">
    <h2 id="accesos-titulo" class="sr-only">Accesos rápidos</h2>
    <ul class="grid gap-4 grid-cols-2 lg:grid-cols-4">
        <?php foreach ($accesos as [$titulo, $descripcion, $url, $trazo, $azulejo]) { ?>
            <li>
                <a class="panel-vidrio group flex h-full flex-col gap-3 rounded-2xl p-5 no-underline transition-transform hover:-translate-y-1" href="<?= $e($url) ?>">
                    <span class="grid h-12 w-12 place-items-center rounded-2xl <?= $e($azulejo) ?>" aria-hidden="true">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="<?= $e($trazo) ?>"/>
                        </svg>
                    </span>
                    <span class="font-bold texto-titular"><?= $e($titulo) ?></span>
                    <span class="text-sm texto-suave"><?= $e($descripcion) ?></span>
                </a>
            </li>
        <?php } ?>
    </ul>
</section>

<?php /* --- Retícula bento: urgencias + noticias + sedes --- */ ?>
<section class="mx-auto max-w-7xl px-4 py-10" aria-labelledby="bento-titulo">
    <h2 id="bento-titulo" class="sr-only">Urgencias, noticias y sedes</h2>

    <div class="grid gap-6 lg:grid-cols-3">
        <?php /* La tarjeta oscura del prototipo se usa para urgencias, que es
                 lo que de verdad necesita destacar en el sitio de un
                 hospital, y no para un aviso genérico. */ ?>
        <article class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-primario-700 to-primario-900 p-8 text-white">
            <span class="mancha h-64 w-64 -right-16 -top-16 bg-[color:var(--color-marca-cian)] opacity-20" aria-hidden="true"></span>
            <div class="relative">
                <svg class="h-10 w-10 text-[color:var(--color-marca-cian)]" viewBox="0 0 24 24" fill="none" aria-hidden="true"
                     stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 8v5M12 16.5v.01M12 3.5 21 20H3l9-16.5Z"/>
                </svg>
                <h3 class="mt-6 text-white text-2xl">Urgencias 24 horas</h3>
                <p class="mt-3 text-primario-100">
                    Si es una urgencia, no esperes turno en línea. Llámanos o acércate
                    directamente a la sede.
                </p>
                <p class="mt-6 flex flex-wrap gap-3">
                    <a class="boton bg-white text-primario-700 font-bold"
                       href="tel:<?= $e(preg_replace('/\D+/', '', $config->texto('contacto.emergencias'))) ?>">
                        <?= $e($config->texto('contacto.emergencias')) ?>
                    </a>
                    <a class="boton border border-white text-white no-underline" href="/urgencias">Cómo llegar</a>
                </p>
            </div>
        </article>

        <div class="lg:col-span-2 grid gap-6 sm:grid-cols-2">
            <?php /* Noticias */ ?>
            <div class="panel-vidrio rounded-3xl p-6">
                <div class="flex items-baseline gap-4">
                    <h3 id="noticias-titulo">Noticias recientes</h3>
                    <a class="ms-auto whitespace-nowrap text-sm" href="/noticias">Ver todas →</a>
                </div>
                <?php if ($noticias === []) { ?>
                    <p class="mt-4 text-sm texto-suave">Aún no hay noticias publicadas.</p>
                <?php } else { ?>
                    <ul class="mt-4 flex flex-col gap-5" aria-labelledby="noticias-titulo">
                        <?php foreach (array_slice($noticias, 0, 2) as $noticia) { ?>
                            <li>
                                <article>
                                    <?php if ($noticia->imagenId !== null) { ?>
                                        <img class="mb-3 h-32 w-full rounded-xl object-cover" src="/archivos/<?= $noticia->imagenId ?>"
                                             alt="<?= $e($imagenes[$noticia->imagenId]->textoAlt ?? '') ?>"
                                             loading="lazy" width="600" height="400">
                                    <?php } ?>
                                    <h4 class="text-base leading-snug">
                                        <a class="texto-titular no-underline hover:underline" href="/noticias/<?= $e($noticia->slug) ?>">
                                            <?= $e($noticia->titulo) ?>
                                        </a>
                                    </h4>
                                    <p class="mt-1 text-sm texto-suave"><?= $e(Fechas::larga($noticia->publicarEn ?? $noticia->creadoEn)) ?></p>
                                </article>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </div>

            <?php /* Sedes. El prototipo inventaba tres; el hospital tiene
                     SEDE ÚNICA (docs/08), así que el título y el enlace
                     «ver todas» se adaptan al número real. */ ?>
            <div class="panel-vidrio rounded-3xl p-6">
                <div class="flex items-baseline gap-4">
                    <h3 id="sedes-titulo"><?= count($sedes) === 1 ? 'Nuestra sede' : 'Nuestras sedes' ?></h3>
                    <?php if (count($sedes) > 1) { ?>
                        <a class="ms-auto whitespace-nowrap text-sm" href="/sedes">Ver todas →</a>
                    <?php } ?>
                </div>
                <?php if ($sedes === []) { ?>
                    <p class="mt-4 text-sm texto-suave">
                        Consulta nuestra dirección y horarios en <a href="/contacto">contacto</a>.
                    </p>
                <?php } else { ?>
                    <ul class="mt-4 flex flex-col gap-3" aria-labelledby="sedes-titulo">
                        <?php foreach ($sedes as $sede) { ?>
                            <li><?= $vistas->render('publico/parciales/tarjeta-sede', ['sede' => $sede]) ?></li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </div>
        </div>
    </div>
</section>

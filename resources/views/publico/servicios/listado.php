<?php
/**
 * /servicios — Espera: $servicios (list<Servicio>), $sedes (list<Sede>),
 *              $imagenes (array<int, Archivo> indexado por id).
 */
?>
<div class="mx-auto max-w-6xl px-4 py-10">
    <!-- Cabecera de Servicios Premium -->
    <div class="relative overflow-hidden rounded-[2.5rem] cabecera-seccion p-10 text-white mb-10 shadow-2xl border border-white/10">
        <span class="mancha h-64 w-64 -right-16 -top-16 bg-[color:var(--color-marca-cian)] opacity-30 blur-3xl" aria-hidden="true"></span>
        <div class="relative z-10 max-w-2xl">
            <span class="text-xs font-bold uppercase tracking-widest text-white bg-white/10 px-3 py-1 rounded-full border border-white/10">Portafolio Clínico</span>
            <h1 class="text-white mt-4 text-4xl lg:text-5xl font-extrabold tracking-tight">Nuestros Servicios</h1>
            <p class="mt-4 text-lg text-primario-100 leading-relaxed">
                Ofrecemos atención médica integral respaldada por especialistas comprometidos con tu bienestar, equipamiento moderno e instalaciones seguras.
            </p>
        </div>
    </div>

    <!-- Filtros Interactivos -->
    <?php /* El filtro solo aparece con MÁS de una sede. El hospital tiene
             sede única (docs/08): un desplegable de «Todas las sedes» más una
             opción ofrece una elección que no existe. Se decide por los datos,
             así que si abren una segunda sede el filtro vuelve solo. */ ?>
    <?php if (count($sedes) > 1) { ?>
        <div class="mb-10 panel-vidrio p-6 rounded-3xl border-white/20 max-w-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex-1">
                <label class="etiqueta flex items-center gap-2 mb-1" for="filtro-sede">
                    <svg class="h-5 w-5 text-primario-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    Ubicación del Servicio
                </label>
                <p class="text-xs texto-suave">Selecciona una de nuestras sedes para filtrar los servicios disponibles.</p>
            </div>
            <div class="sm:max-w-xs w-full">
                <select class="campo" id="filtro-sede" data-filtro="lista-servicios">
                    <option value="">Todas las sedes</option>
                    <?php foreach ($sedes as $sede) { ?>
                        <option value="<?= $sede->id ?>"><?= $e($sede->nombre) ?></option>
                    <?php } ?>
                </select>
                <p class="mt-2 text-xs text-primario-600 dark:text-primario-400 font-semibold" aria-live="polite" data-filtro-resultado="lista-servicios"></p>
            </div>
        </div>
    <?php } ?>

    <!-- Rejilla de Servicios Bento -->
    <?php if ($servicios === []) { ?>
        <p class="texto-suave bg-white dark:bg-primario-900 border border-primario-100 dark:border-primario-800 p-6 rounded-2xl">
            Pronto publicaremos nuestro portafolio de servicios médicos especializados.
        </p>
    <?php } else { ?>
        <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3" id="lista-servicios">
            <?php foreach ($servicios as $servicio) {
                $foto = $imagenes[$servicio->imagenId] ?? null;
                ?>
                <li data-valores="<?= implode(',', $servicio->sedeIds) ?>" class="transform transition duration-300 hover:-translate-y-2">
                    <article class="tarjeta h-full flex flex-col justify-between group border-primario-100/50 dark:border-primario-700/50 bg-[color:var(--fondo)]/80 backdrop-blur-sm overflow-hidden p-0">
                        <?php if ($foto !== null) { ?>
                            <?php /* Relación fija: las fotos del portafolio vienen en formatos
                                     distintos y sin altura reservada la rejilla salta al cargar
                                     (CLAUDE.md §6, Frontend: cero layout shift). */ ?>
                            <div class="aspect-[16/10] overflow-hidden bg-primario-50 dark:bg-primario-900">
                                <img class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105"
                                     src="/archivos/<?= $foto->id ?>" alt="<?= $e($foto->textoAlt ?? '') ?>"
                                     width="<?= $foto->ancho ?? 1600 ?>" height="<?= $foto->alto ?? 1000 ?>" loading="lazy" decoding="async">
                            </div>
                        <?php } ?>
                        <div class="flex flex-1 flex-col justify-between p-6">
                            <div>
                                <?php if ($foto === null) { ?>
                                    <!-- Icono Clínico Ilustrativo -->
                                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-primario-50 text-primario-600 dark:bg-primario-800 dark:text-primario-200 group-hover:scale-110 transition-transform mb-4">
                                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                        </svg>
                                    </span>
                                <?php } ?>
                                <h2 class="text-xl font-bold leading-tight">
                                    <a class="texto-titular no-underline group-hover:text-primario-600 transition-colors" href="/servicios/<?= $e($servicio->slug) ?>">
                                        <?= $e($servicio->nombre) ?>
                                    </a>
                                </h2>
                                <?php if ($servicio->descripcion !== null) { ?>
                                    <p class="mt-3 text-sm texto-suave"><?= $e(mb_strimwidth($servicio->descripcion, 0, 160, '…')) ?></p>
                                <?php } ?>
                                <?php if ($servicio->destacados !== []) { ?>
                                    <ul class="mt-4 flex flex-wrap gap-2">
                                        <?php foreach (array_slice($servicio->destacados, 0, 3) as $destacado) { ?>
                                            <li class="rounded-xl bg-primario-50 dark:bg-primario-800/60 px-3 py-1.5 text-xs leading-tight">
                                                <?php if ($destacado['cifra'] !== '') { ?>
                                                    <strong class="text-primario-700 dark:text-primario-200"><?= $e($destacado['cifra']) ?></strong>
                                                <?php } ?>
                                                <span class="texto-suave"><?= $e($destacado['texto']) ?></span>
                                            </li>
                                        <?php } ?>
                                    </ul>
                                <?php } ?>
                                <?php if ($servicio->horario !== null) { ?>
                                    <p class="mt-3 text-sm texto-suave flex items-center gap-2">
                                        <svg class="h-4 w-4 text-primario-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                        <?= $e($servicio->horario) ?>
                                    </p>
                                <?php } ?>
                            </div>
                            <div class="mt-6 pt-4 border-t border-primario-100 dark:border-primario-800 flex justify-between items-center">
                                <span class="text-xs text-primario-500 font-bold uppercase tracking-wider">Atención Especializada</span>
                                <a class="text-sm font-semibold text-primario-600 dark:text-primario-400 group-hover:translate-x-1 transition-transform inline-flex items-center no-underline" href="/servicios/<?= $e($servicio->slug) ?>">
                                    Ver Detalle
                                    <svg class="ml-1 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                </a>
                            </div>
                        </div>
                    </article>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>
</div>

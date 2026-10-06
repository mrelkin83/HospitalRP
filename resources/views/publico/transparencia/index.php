<?php
/**
 * /transparencia — menú completo del Anexo 2.
 * Espera: $arbol (list<seccion con hijas e items publicados>).
 */
?>
<div class="mx-auto max-w-6xl px-4 py-10">
    <!-- Cabecera de Transparencia Premium -->
    <div class="relative overflow-hidden rounded-[2.5rem] cabecera-seccion p-10 text-white mb-10 shadow-2xl border border-white/10">
        <span class="mancha h-64 w-64 -right-16 -top-16 bg-[color:var(--color-marca-cian)] opacity-30 blur-3xl" aria-hidden="true"></span>
        <div class="relative z-10 max-w-2xl">
            <span class="text-xs font-bold uppercase tracking-widest text-[color:var(--color-marca-cian)] bg-white/10 px-3 py-1 rounded-full border border-white/10">Ley 1712 de 2014</span>
            <h1 class="text-white mt-4 text-4xl lg:text-5xl font-extrabold tracking-tight">Transparencia</h1>
            <p class="mt-4 text-lg text-primario-100 leading-relaxed">
                Acceso a la información pública, rendición de cuentas y datos abiertos de la E.S.E. Hospital Rosario Pumarejo de López en cumplimiento de la Resolución 1519 de 2020.
            </p>
        </div>
    </div>

    <!-- Contenedor Asimétrico Bento Grid -->
    <div class="grid gap-8 lg:grid-cols-2">
        <?php foreach ($arbol as $seccion) { ?>
            <section class="tarjeta flex flex-col justify-between border-primario-100/50 dark:border-primario-700/50 bg-[color:var(--fondo)]/80 backdrop-blur-sm p-8" aria-labelledby="tp-<?= $seccion['id'] ?>" id="seccion-<?= $e((string) $seccion['codigo']) ?>">
                <div>
                    <div class="flex items-center gap-3 border-b border-primario-100 dark:border-primario-800 pb-4 mb-6">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primario-50 text-primario-600 dark:bg-primario-800 dark:text-primario-200 font-extrabold text-sm shadow-sm" aria-hidden="true">
                            <?= $e((string) $seccion['codigo']) ?>
                        </span>
                        <h2 id="tp-<?= $seccion['id'] ?>" class="text-xl font-extrabold tracking-tight texto-titular">
                            <?= $e((string) $seccion['nombre']) ?>
                        </h2>
                    </div>

                    <div class="space-y-6">
                        <?php /* Cada subsección lleva su propia ancla. El oficio
                                 de Supersalud exige «enlace directo y funcional»
                                 por criterio, y sin esto el enlace más preciso
                                 que se podía certificar era el de la sección
                                 entera. */ ?>
                        <?php foreach ($seccion['hijas'] as $hija) { ?>
                            <div id="seccion-<?= $e((string) $hija['codigo']) ?>"
                                 class="p-4 bg-white/50 dark:bg-primario-900/30 rounded-2xl border border-primario-100/20 dark:border-primario-700/20 scroll-mt-24">
                                <h3 class="text-base font-bold texto-titular flex items-center gap-2">
                                    <span class="h-1.5 w-1.5 rounded-full bg-[color:var(--color-marca-cian)]"></span>
                                    <?= $e((string) $hija['codigo']) ?>. <?= $e((string) $hija['nombre']) ?>
                                </h3>

                                <?php if ($hija['items'] === []) { ?>
                                    <p class="mt-2 text-xs text-primario-400 font-medium">Sección en actualización constante.</p>
                                <?php } else { ?>
                                    <ul class="mt-3 flex flex-col gap-3">
                                        <?php foreach ($hija['items'] as $item) { ?>
                                            <li class="group/item">
                                                <?php if ($item['documento_id'] !== null && $item['documento_slug'] !== null) { ?>
                                                    <a class="text-sm font-bold text-primario-600 dark:text-primario-400 hover:text-primario-800 dark:hover:text-primario-200 inline-flex items-center gap-1.5 no-underline hover:underline" href="/documentos/<?= (int) $item['documento_id'] ?>-<?= $e((string) $item['documento_slug']) ?>">
                                                        <svg class="h-4 w-4 text-primario-400 group-hover/item:translate-x-0.5 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                                        <?= $e((string) $item['titulo']) ?>
                                                    </a>
                                                <?php } else { ?>
                                                    <a class="text-sm font-bold text-primario-600 dark:text-primario-400 hover:text-primario-800 dark:hover:text-primario-200 inline-flex items-center gap-1.5 no-underline hover:underline" href="<?= $e((string) $item['url_externa']) ?>" target="_blank" rel="noopener">
                                                        <svg class="h-4 w-4 text-primario-400 group-hover/item:translate-x-0.5 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                                        <?= $e((string) $item['titulo']) ?>
                                                        <span class="text-xs text-primario-400 font-semibold">(externo)</span>
                                                    </a>
                                                <?php } ?>
                                                <?php if (($item['descripcion'] ?? null) !== null) { ?>
                                                    <p class="text-xs texto-suave mt-1 pl-5"><?= $e((string) $item['descripcion']) ?></p>
                                                <?php } ?>
                                            </li>
                                        <?php } ?>
                                    </ul>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>
                <div class="mt-8 pt-4 border-t border-primario-100 dark:border-primario-800 flex justify-between items-center text-xs text-primario-400">
                    <span>E.S.E. Hospital Rosario Pumarejo</span>
                    <span class="font-mono uppercase">LEY 1712</span>
                </div>
            </section>
        <?php } ?>
    </div>
</div>

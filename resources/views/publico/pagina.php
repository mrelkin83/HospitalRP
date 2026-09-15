<?php
/**
 * Página institucional por ruta dinámica.
 * Espera: $pagina (Pagina), $imagen (?Archivo).
 */

use App\Core\Support\Fechas;
?>
<div class="mx-auto max-w-6xl px-4 py-10">
    <!-- Cabecera de Página Dinámica Premium -->
    <div class="relative overflow-hidden rounded-[2.5rem] cabecera-seccion p-10 text-white mb-10 shadow-2xl border border-white/10">
        <span class="mancha h-64 w-64 -right-16 -top-16 bg-[color:var(--color-marca-cian)] opacity-30 blur-3xl" aria-hidden="true"></span>
        <div class="relative z-10 max-w-3xl">
            <span class="text-xs font-bold uppercase tracking-widest text-[color:var(--color-marca-cian)] bg-white/10 px-3 py-1 rounded-full border border-white/10">Institucional</span>
            <h1 class="text-white mt-4 text-4xl lg:text-5xl font-extrabold tracking-tight"><?= $e($pagina->titulo) ?></h1>
            <?php if ($pagina->resumen !== null) { ?>
                <p class="mt-4 text-lg text-primario-100 leading-relaxed"><?= $e($pagina->resumen) ?></p>
            <?php } ?>
        </div>
    </div>

    <div class="grid gap-10 lg:grid-cols-3 items-start">
        <!-- Contenido de la Página -->
        <div class="lg:col-span-2 tarjeta border-primario-100/50 dark:border-primario-700/50 bg-[color:var(--superficie)] p-8 shadow-xl">
            <?php if ($imagen !== null) { ?>
                <figure class="mb-8 overflow-hidden rounded-2xl relative aspect-[16/9] shadow-md">
                    <img class="h-full w-full object-cover" src="/archivos/<?= $imagen->id ?>"
                         alt="<?= $e($imagen->textoAlt ?? '') ?>"
                         width="<?= $imagen->ancho ?? 1200 ?>" height="<?= $imagen->alto ?? 675 ?>">
                    <div class="absolute inset-0 ring-1 ring-inset ring-black/10 rounded-2xl"></div>
                </figure>
            <?php } ?>

            <div class="prosa max-w-none text-primario-900 dark:text-primario-100 leading-relaxed">
                <?php /* Saneado con lista blanca antes de guardarse. */ ?>
                <?= $pagina->contenido ?>
            </div>

            <?php if ($pagina->actualizadoEn !== null) { ?>
                <div class="mt-10 pt-4 border-t border-primario-100 dark:border-primario-800 flex items-center justify-between text-xs text-primario-400">
                    <span>E.S.E. Hospital Rosario Pumarejo</span>
                    <span>Última actualización: <?= $e(Fechas::larga($pagina->actualizadoEn)) ?></span>
                </div>
            <?php } ?>
        </div>

        <!-- Barra Lateral Informativa del Hospital -->
        <aside class="panel-vidrio rounded-[2rem] p-6 border-white/20 space-y-6">
            <div class="flex items-center gap-3 border-b border-primario-100/30 pb-3">
                <span class="p-2 bg-primario-50 dark:bg-primario-800 rounded-xl text-primario-600 dark:text-primario-200">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </span>
                <h3 class="font-extrabold text-base texto-titular">Atención Segura</h3>
            </div>
            <p class="text-xs texto-suave leading-relaxed">
                Somos una institución pública de mediana y alta complejidad, comprometida con brindar la mejor experiencia en salud a todo el departamento del Cesar.
            </p>
            <div class="pt-4 border-t border-primario-100/30 flex flex-col gap-3">
                <a class="boton boton-primario text-sm py-2 px-4 rounded-xl flex justify-center w-full shadow-md" href="/citas">Solicitar Cita</a>
                <a class="boton boton-secundario text-sm py-2 px-4 rounded-xl flex justify-center w-full" href="/contacto">Canales de Contacto</a>
            </div>
        </aside>
    </div>
</div>

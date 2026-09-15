<?php
/**
 * Una campaña o comunicado dentro de un listado.
 * Espera: $campana (Campana), $imagenes (array<int, Archivo>), $esPersonal (bool).
 */

use App\Core\Support\Fechas;

$imagenes ??= [];
$esPersonal ??= false;
?>
<article class="tarjeta h-full flex flex-col gap-3 p-6 rounded-[1.75rem] border border-primario-100/50 dark:border-primario-700/50 bg-[color:var(--superficie)] shadow-md hover:shadow-xl transition-shadow">
    <?php if ($campana->imagenId !== null) { ?>
        <div class="overflow-hidden rounded-2xl relative aspect-[16/9] mb-1">
            <img class="h-full w-full object-cover" src="/archivos/<?= $campana->imagenId ?>"
                 alt="<?= $e($imagenes[$campana->imagenId]->textoAlt ?? '') ?>"
                 loading="lazy" width="600" height="337">
        </div>
    <?php } ?>

    <?php if ($campana->poblacionObjetivo !== null && $campana->poblacionObjetivo !== '') { ?>
        <span class="self-start text-[10px] font-extrabold uppercase tracking-widest px-3 py-1 rounded-full border <?= $esPersonal
            ? 'bg-aviso-100 text-aviso-900 border-aviso-200'
            : 'bg-[color:var(--color-marca-cian)]/10 text-primario-800 dark:text-white border-[color:var(--color-marca-cian)]/20' ?>">
            <?= $e($campana->poblacionObjetivo) ?>
        </span>
    <?php } ?>

    <h2 class="text-xl font-extrabold leading-tight texto-titular">
        <a class="no-underline hover:underline" href="/campanas/<?= $e($campana->slug) ?>"><?= $e($campana->titulo) ?></a>
    </h2>

    <?php if ($campana->resumen !== null && $campana->resumen !== '') { ?>
        <p class="text-sm texto-suave leading-relaxed"><?= $e($campana->resumen) ?></p>
    <?php } ?>

    <?php if ($campana->fin !== null) { ?>
        <p class="mt-auto pt-2 text-xs texto-suave">Vigente hasta el <?= $e(Fechas::larga($campana->fin)) ?></p>
    <?php } ?>
</article>

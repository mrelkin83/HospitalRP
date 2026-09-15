<?php
/**
 * Detalle público de una campaña o comunicado.
 * Espera: $campana (Campana), $imagen (?Archivo).
 */

use App\Core\Support\Fechas;
use App\Modules\Contenido\Entity\Audiencia;

$esPersonal = $campana->audiencia === Audiencia::Personal;
?>
<article class="mx-auto max-w-6xl px-4 py-10">
    <nav aria-label="Miga de pan" class="text-sm texto-suave">
        <a href="/">Inicio</a> ›
        <a href="<?= $e($campana->audiencia->ruta()) ?>"><?= $e($esPersonal ? 'Trabajadores y contratistas' : 'Campañas') ?></a>
    </nav>

    <?php if ($campana->poblacionObjetivo !== null && $campana->poblacionObjetivo !== '') { ?>
        <span class="mt-4 inline-block text-[10px] font-extrabold uppercase tracking-widest px-3 py-1 rounded-full border <?= $esPersonal
            ? 'bg-aviso-100 text-aviso-900 border-aviso-200'
            : 'bg-[color:var(--color-marca-cian)]/10 text-primario-800 dark:text-white border-[color:var(--color-marca-cian)]/20' ?>">
            <?= $e($campana->poblacionObjetivo) ?>
        </span>
    <?php } ?>

    <h1 class="mt-4 max-w-3xl"><?= $e($campana->titulo) ?></h1>

    <?php /* La vigencia es el dato que decide si alguien se desplaza al
             hospital: va arriba, no escondida al final. */ ?>
    <?php if ($campana->inicio !== null || $campana->fin !== null) { ?>
        <p class="mt-3 text-sm texto-suave">
            <?php if ($campana->inicio !== null && $campana->fin !== null) { ?>
                Del <?= $e(Fechas::larga($campana->inicio)) ?> al <?= $e(Fechas::larga($campana->fin)) ?>
            <?php } elseif ($campana->inicio !== null) { ?>
                Desde el <?= $e(Fechas::larga($campana->inicio)) ?>
            <?php } else { ?>
                Vigente hasta el <?= $e(Fechas::larga($campana->fin)) ?>
            <?php } ?>
        </p>
    <?php } ?>

    <?php if ($campana->resumen !== null && $campana->resumen !== '') { ?>
        <p class="mt-4 text-lg texto-suave max-w-3xl"><?= $e($campana->resumen) ?></p>
    <?php } ?>

    <?php if ($imagen !== null) { ?>
        <figure class="mt-6 max-w-3xl">
            <img class="w-full rounded-xl" src="/archivos/<?= $imagen->id ?>"
                 alt="<?= $e($imagen->textoAlt ?? '') ?>"
                 width="<?= $imagen->ancho ?? 1200 ?>" height="<?= $imagen->alto ?? 675 ?>">
        </figure>
    <?php } ?>

    <div class="prosa mt-8">
        <?php /* Saneado con lista blanca ANTES de guardarse (SanitizadorHtml). */ ?>
        <?= $campana->contenido ?>
    </div>

    <p class="mt-10">
        <a class="boton boton-secundario no-underline" href="<?= $e($campana->audiencia->ruta()) ?>">
            <?= $e($esPersonal ? 'Ver todos los comunicados al personal' : 'Ver todas las campañas') ?>
        </a>
    </p>
</article>

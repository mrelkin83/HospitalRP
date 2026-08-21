<?php
/**
 * Página institucional por ruta dinámica.
 * Espera: $pagina (Pagina), $imagen (?Archivo).
 */

use App\Core\Support\Fechas;
?>
<article class="mx-auto max-w-6xl px-4 py-10">
    <h1 class="max-w-3xl"><?= $e($pagina->titulo) ?></h1>

    <?php if ($pagina->resumen !== null) { ?>
        <p class="mt-4 text-lg texto-suave max-w-3xl"><?= $e($pagina->resumen) ?></p>
    <?php } ?>

    <?php if ($imagen !== null) { ?>
        <figure class="mt-6 max-w-3xl">
            <img class="w-full rounded-xl" src="/archivos/<?= $imagen->id ?>"
                 alt="<?= $e($imagen->textoAlt ?? '') ?>"
                 width="<?= $imagen->ancho ?? 1200 ?>" height="<?= $imagen->alto ?? 675 ?>">
        </figure>
    <?php } ?>

    <div class="prosa mt-8">
        <?php /* Saneado con lista blanca antes de guardarse. */ ?>
        <?= $pagina->contenido ?>
    </div>

    <?php if ($pagina->actualizadoEn !== null) { ?>
        <p class="mt-10 text-sm texto-suave">
            Última actualización: <?= $e(Fechas::larga($pagina->actualizadoEn)) ?>
        </p>
    <?php } ?>
</article>

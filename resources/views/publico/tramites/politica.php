<?php
/**
 * /politica-tratamiento-datos — Espera: $politica (fila de politicas).
 */

use App\Core\Support\Fechas;
?>
<article class="mx-auto max-w-6xl px-4 py-10">
    <h1 class="max-w-3xl"><?= $e((string) $politica['titulo']) ?></h1>
    <p class="mt-2 text-sm texto-suave">
        Versión <?= $e((string) $politica['version']) ?> ·
        vigente desde <?= $e(Fechas::larga((string) $politica['vigente_desde'])) ?>
    </p>

    <div class="prosa mt-8">
        <?php /* Contenido administrado (semilla o panel), saneado al guardarse. */ ?>
        <?= $politica['contenido'] ?>
    </div>
</article>

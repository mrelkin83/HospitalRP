<?php
/**
 * Detalle público de una noticia.
 * Espera: $noticia (Noticia), $imagen (?Archivo).
 */

use App\Core\Support\Fechas;
?>
<article class="mx-auto max-w-6xl px-4 py-10">
    <nav aria-label="Miga de pan" class="text-sm texto-suave">
        <a href="/">Inicio</a> › <a href="/noticias">Noticias</a>
    </nav>

    <h1 class="mt-4 max-w-3xl"><?= $e($noticia->titulo) ?></h1>
    <p class="mt-3 text-sm texto-suave">
        Publicada el <?= $e(Fechas::larga($noticia->publicarEn ?? $noticia->creadoEn)) ?>
    </p>
    <p class="mt-4 text-lg texto-suave max-w-3xl"><?= $e($noticia->resumen) ?></p>

    <?php if ($imagen !== null) { ?>
        <figure class="mt-6 max-w-3xl">
            <img class="w-full rounded-xl" src="/archivos/<?= $imagen->id ?>"
                 alt="<?= $e($imagen->textoAlt ?? '') ?>"
                 width="<?= $imagen->ancho ?? 1200 ?>" height="<?= $imagen->alto ?? 675 ?>">
        </figure>
    <?php } ?>

    <div class="prosa mt-8">
        <?php /* Saneado con lista blanca ANTES de guardarse (SanitizadorHtml). */ ?>
        <?= $noticia->contenido ?>
    </div>
</article>

<?php
/**
 * /galeria — imágenes públicas de la biblioteca de medios.
 * Espera: $imagenes (list<Archivo>), $pagina, $totalPaginas, $total (int).
 */
?>
<div class="mx-auto max-w-7xl px-4 py-10">
    <h1>Galería de imágenes</h1>
    <p class="mt-3 texto-suave max-w-prose">
        Jornadas, campañas y actividades de la E.S.E. Hospital Rosario Pumarejo
        de López.
    </p>

    <?php if ($imagenes === []) { ?>
        <p class="mt-8 tarjeta texto-suave">
            Todavía no hay imágenes publicadas. Consulta las
            <a href="/noticias">noticias</a> para ver la actividad del hospital.
        </p>
    <?php } else { ?>
        <p class="mt-6 text-sm texto-suave"><?= (int) $total ?> imágenes publicadas.</p>

        <ul class="mt-6 grid gap-4 grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
            <?php foreach ($imagenes as $imagen) { ?>
                <li>
                    <figure class="panel-vidrio overflow-hidden rounded-2xl">
                        <?php /* `width` y `height` reales evitan que la página
                                 salte al cargar; `loading="lazy"` protege el
                                 presupuesto en 3G. El texto alternativo sale de
                                 la biblioteca: si falta, la imagen se marca
                                 como decorativa en vez de inventarle una
                                 descripción que engañaría al lector. */ ?>
                        <img class="aspect-[4/3] w-full object-cover"
                             src="/archivos/<?= (int) $imagen->id ?>"
                             alt="<?= $e($imagen->textoAlt ?? '') ?>"
                             loading="lazy"
                             <?php if ($imagen->ancho !== null && $imagen->alto !== null) { ?>
                                 width="<?= (int) $imagen->ancho ?>" height="<?= (int) $imagen->alto ?>"
                             <?php } ?>>
                        <?php if (($imagen->textoAlt ?? '') !== '') { ?>
                            <figcaption class="p-3 text-sm texto-suave"><?= $e($imagen->textoAlt) ?></figcaption>
                        <?php } ?>
                    </figure>
                </li>
            <?php } ?>
        </ul>

        <?php if ($totalPaginas > 1) { ?>
            <nav class="mt-8 flex items-center justify-between gap-4" aria-label="Paginación de la galería">
                <?php if ($pagina > 1) { ?>
                    <a rel="prev" href="/galeria?pagina=<?= $pagina - 1 ?>">&larr; Anteriores</a>
                <?php } else { ?><span></span><?php } ?>

                <span class="text-sm texto-suave">Página <?= $pagina ?> de <?= $totalPaginas ?></span>

                <?php if ($pagina < $totalPaginas) { ?>
                    <a rel="next" href="/galeria?pagina=<?= $pagina + 1 ?>">Siguientes &rarr;</a>
                <?php } else { ?><span></span><?php } ?>
            </nav>
        <?php } ?>
    <?php } ?>
</div>

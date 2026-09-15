<?php
/**
 * Listado público de noticias, entero o filtrado por categoría.
 * Espera: $noticias (list<Noticia>), $imagenes (array<int, Archivo>),
 *         $pagina (int), $totalPaginas (int),
 *         $categorias (list<{id,nombre,slug}>), $categoriaActiva (?array).
 */

use App\Core\Support\Fechas;

$categorias ??= [];
$categoriaActiva ??= null;
?>
<div class="mx-auto max-w-6xl px-4 py-10">
    <h1><?= $e($categoriaActiva === null ? 'Noticias' : $categoriaActiva['nombre']) ?></h1>

    <?php /* Los filtros solo aparecen si hay más de una categoría con algo
             publicado: un filtro de una sola opción no filtra nada. */ ?>
    <?php if (count($categorias) > 1) { ?>
        <nav class="mt-4 flex flex-wrap gap-2" aria-label="Filtrar noticias por categoría">
            <a href="/noticias" class="etiqueta<?= $categoriaActiva === null ? ' etiqueta-activa' : '' ?>"
               <?= $categoriaActiva === null ? 'aria-current="page"' : '' ?>>Todas</a>
            <?php foreach ($categorias as $categoria) { ?>
                <?php $activa = $categoriaActiva !== null && $categoriaActiva['slug'] === $categoria['slug']; ?>
                <a href="/noticias/categoria/<?= $e($categoria['slug']) ?>"
                   class="etiqueta<?= $activa ? ' etiqueta-activa' : '' ?>"
                   <?= $activa ? 'aria-current="page"' : '' ?>><?= $e($categoria['nombre']) ?></a>
            <?php } ?>
        </nav>
    <?php } ?>

    <?php if ($noticias === []) { ?>
        <p class="mt-6 texto-suave">Aún no hay noticias publicadas.</p>
    <?php } else { ?>
        <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($noticias as $noticia) { ?>
                <li>
                    <article class="tarjeta h-full p-0 overflow-hidden">
                        <?php if ($noticia->imagenId !== null) { ?>
                            <img class="h-44 w-full object-cover" src="/archivos/<?= $noticia->imagenId ?>"
                                 alt="<?= $e($imagenes[$noticia->imagenId]->textoAlt ?? '') ?>"
                                 loading="lazy" width="600" height="400">
                        <?php } ?>
                        <div class="p-4">
                            <h2 class="text-base leading-snug">
                                <a class="texto-titular no-underline hover:underline" href="/noticias/<?= $e($noticia->slug) ?>">
                                    <?= $e($noticia->titulo) ?>
                                </a>
                            </h2>
                            <p class="mt-2 text-sm texto-suave"><?= $e(Fechas::larga($noticia->publicarEn ?? $noticia->creadoEn)) ?></p>
                            <p class="mt-2 text-sm"><?= $e($noticia->resumen) ?></p>
                        </div>
                    </article>
                </li>
            <?php } ?>
        </ul>

        <?php if ($totalPaginas > 1) { ?>
            <nav class="mt-8 flex items-center gap-3" aria-label="Paginación de noticias">
                <?php if ($pagina > 1) { ?>
                    <a class="boton boton-secundario" href="/noticias?pagina=<?= $pagina - 1 ?>">← Más recientes</a>
                <?php } ?>
                <span class="text-sm texto-suave">Página <?= $pagina ?> de <?= $totalPaginas ?></span>
                <?php if ($pagina < $totalPaginas) { ?>
                    <a class="boton boton-secundario" href="/noticias?pagina=<?= $pagina + 1 ?>">Anteriores →</a>
                <?php } ?>
            </nav>
        <?php } ?>
    <?php } ?>
</div>

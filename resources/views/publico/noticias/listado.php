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
    <!-- Cabecera de Noticias Premium -->
    <div class="relative overflow-hidden rounded-[2.5rem] bg-gradient-to-br from-primario-900 via-primario-800 to-primario-950 p-10 text-white mb-10 shadow-2xl border border-white/10">
        <span class="mancha h-64 w-64 -right-16 -top-16 bg-[color:var(--color-marca-cian)] opacity-30 blur-3xl" aria-hidden="true"></span>
        <div class="relative z-10 max-w-2xl">
            <span class="text-xs font-bold uppercase tracking-widest text-[color:var(--color-marca-cian)] bg-white/10 px-3 py-1 rounded-full border border-white/10">Actualidad Hospitalaria</span>
            <h1 class="text-white mt-4 text-4xl lg:text-5xl font-extrabold tracking-tight">
                <?= $e($categoriaActiva === null ? 'Noticias y Prensa' : 'Categoría: ' . $categoriaActiva['nombre']) ?>
            </h1>
            <p class="mt-4 text-lg text-primario-100 leading-relaxed">
                Entérate de las últimas novedades, campañas de prevención, eventos y noticias de la E.S.E. Hospital Rosario Pumarejo de López.
            </p>
        </div>
    </div>

    <!-- Barra de Categorías Táctil Flotante -->
    <?php if (count($categorias) > 1) { ?>
        <nav class="mb-10 panel-vidrio p-4 rounded-2xl border-white/20 flex flex-wrap gap-2 items-center" aria-label="Filtrar noticias por categoría">
            <span class="text-xs font-extrabold uppercase tracking-widest text-primario-500 mr-2 flex items-center gap-1">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line><line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line><line x1="20" y1="21" x2="20" y2="16"></line><line x1="20" y1="12" x2="20" y2="3"></line><line x1="1" y1="14" x2="7" y2="14"></line><line x1="9" y1="8" x2="15" y2="8"></line><line x1="17" y1="16" x2="23" y2="16"></line></svg>
                Filtrar:
            </span>
            <a href="/noticias" class="boton py-1.5 px-4 text-sm rounded-xl transition-all <?= $categoriaActiva === null ? 'boton-primario scale-102 shadow-sm' : 'boton-secundario text-primario-700' ?>"
               <?= $categoriaActiva === null ? 'aria-current="page"' : '' ?>>Todas</a>
            <?php foreach ($categorias as $categoria) { ?>
                <?php $activa = $categoriaActiva !== null && $categoriaActiva['slug'] === $categoria['slug']; ?>
                <a href="/noticias/categoria/<?= $e($categoria['slug']) ?>"
                   class="boton py-1.5 px-4 text-sm rounded-xl transition-all <?= $activa ? 'boton-primario scale-102 shadow-sm' : 'boton-secundario text-primario-700' ?>"
                   <?= $activa ? 'aria-current="page"' : '' ?>><?= $e($categoria['nombre']) ?></a>
            <?php } ?>
        </nav>
    <?php } ?>

    <!-- Rejilla de Artículos Bento -->
    <?php if ($noticias === []) { ?>
        <p class="texto-suave bg-white dark:bg-primario-900 border border-primario-100 dark:border-primario-800 p-6 rounded-2xl">
            Aún no hay noticias publicadas en esta sección.
        </p>
    <?php } else { ?>
        <ul class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($noticias as $noticia) { ?>
                <li class="transform transition duration-300 hover:-translate-y-2 group">
                    <article class="tarjeta h-full p-0 overflow-hidden border-primario-100/50 dark:border-primario-700/50 bg-[color:var(--fondo)]/80 backdrop-blur-sm flex flex-col justify-between">
                        <div>
                            <?php if ($noticia->imagenId !== null) { ?>
                                <div class="overflow-hidden aspect-[16/10] relative">
                                    <img class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105" src="/archivos/<?= $noticia->imagenId ?>"
                                         alt="<?= $e($imagenes[$noticia->imagenId]->textoAlt ?? '') ?>"
                                         loading="lazy" width="600" height="400">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300" aria-hidden="true"></div>
                                </div>
                            <?php } ?>
                            <div class="p-6">
                                <span class="text-xs font-bold text-primario-500 uppercase tracking-wider block mb-2">
                                    <?= $e(Fechas::larga($noticia->publicarEn ?? $noticia->creadoEn)) ?>
                                </span>
                                <h2 class="text-xl font-extrabold leading-tight texto-titular mb-3">
                                    <a class="no-underline group-hover:text-primario-600 dark:group-hover:text-primario-400 transition-colors" href="/noticias/<?= $e($noticia->slug) ?>">
                                        <?= $e($noticia->titulo) ?>
                                    </a>
                                </h2>
                                <p class="text-sm texto-suave leading-relaxed line-clamp-3">
                                    <?= $e($noticia->resumen) ?>
                                </p>
                            </div>
                        </div>
                        <div class="p-6 pt-0 border-t border-primario-100 dark:border-primario-800 flex justify-between items-center">
                            <span class="text-xs text-primario-400 font-semibold">Hospital Rosario</span>
                            <a class="text-sm font-bold text-primario-600 dark:text-primario-400 inline-flex items-center no-underline" href="/noticias/<?= $e($noticia->slug) ?>">
                                Leer Más
                                <svg class="ml-1 h-4 w-4 group-hover:translate-x-1 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                            </a>
                        </div>
                    </article>
                </li>
            <?php } ?>
        </ul>

        <!-- Paginación Premium -->
        <?php if ($totalPaginas > 1) { ?>
            <nav class="mt-12 panel-vidrio p-4 rounded-2xl border-white/20 flex items-center justify-between gap-4" aria-label="Paginación de noticias">
                <?php if ($pagina > 1) { ?>
                    <a class="boton boton-secundario text-sm py-2 px-4 rounded-xl" href="/noticias?pagina=<?= $pagina - 1 ?>">← Recientes</a>
                <?php } else { ?><span></span><?php } ?>
                
                <span class="text-sm font-bold text-primario-600 dark:text-primario-400">Página <?= $pagina ?> de <?= $totalPaginas ?></span>
                
                <?php if ($pagina < $totalPaginas) { ?>
                    <a class="boton boton-secundario text-sm py-2 px-4 rounded-xl" href="/noticias?pagina=<?= $pagina + 1 ?>">Anteriores →</a>
                <?php } else { ?><span></span><?php } ?>
            </nav>
        <?php } ?>
    <?php } ?>
</div>

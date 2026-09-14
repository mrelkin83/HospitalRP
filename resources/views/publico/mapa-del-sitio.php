<?php
/**
 * /mapa-del-sitio — índice completo del sitio.
 * Espera: $menus (list<{nombre, items:[{etiqueta,url,nivel}]}>) y
 *         $fijas (array<grupo, array<ruta, etiqueta>>).
 */
?>
<div class="mx-auto max-w-6xl px-4 py-10">
    <h1>Mapa del sitio</h1>
    <p class="mt-3 texto-suave max-w-prose">
        Índice completo de las secciones del sitio. Si no encuentras lo que
        buscas, usa el <a href="/buscar">buscador</a> o escríbenos desde
        <a href="/contacto">contacto</a>.
    </p>

    <div class="mt-8 grid gap-8 md:grid-cols-2">
        <?php $n = 0; foreach ($fijas as $grupo => $enlaces) { $n++; ?>
            <section class="tarjeta" aria-labelledby="ms-f-<?= $n ?>">
                <h2 id="ms-f-<?= $n ?>" class="text-lg"><?= $e((string) $grupo) ?></h2>
                <ul class="mt-3 flex flex-col gap-1.5">
                    <?php foreach ($enlaces as $ruta => $etiqueta) { ?>
                        <li><a href="<?= $e((string) $ruta) ?>"><?= $e((string) $etiqueta) ?></a></li>
                    <?php } ?>
                </ul>
            </section>
        <?php } ?>

        <?php foreach ($menus as $indice => $menu) { ?>
            <?php if ($menu['items'] === []) { continue; } ?>
            <section class="tarjeta" aria-labelledby="ms-m-<?= (int) $indice ?>">
                <h2 id="ms-m-<?= (int) $indice ?>" class="text-lg"><?= $e((string) $menu['nombre']) ?></h2>
                <ul class="mt-3 flex flex-col gap-1.5">
                    <?php foreach ($menu['items'] as $item) { ?>
                        <li class="nivel-<?= (int) $item['nivel'] ?>">
                            <a href="<?= $e((string) $item['url']) ?>"><?= $e((string) $item['etiqueta']) ?></a>
                        </li>
                    <?php } ?>
                </ul>
            </section>
        <?php } ?>
    </div>
</div>

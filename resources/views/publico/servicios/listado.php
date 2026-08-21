<?php
/**
 * /servicios — Espera: $servicios (list<Servicio>), $sedes (list<Sede>).
 */
?>
<div class="mx-auto max-w-6xl px-4 py-10">
    <h1>Servicios</h1>

    <?php if ($sedes !== []) { ?>
        <div class="mt-6 max-w-xs">
            <label class="etiqueta" for="filtro-sede">Filtrar por sede</label>
            <select class="campo" id="filtro-sede" data-filtro="lista-servicios">
                <option value="">Todas las sedes</option>
                <?php foreach ($sedes as $sede) { ?>
                    <option value="<?= $sede->id ?>"><?= $e($sede->nombre) ?></option>
                <?php } ?>
            </select>
            <p class="mt-2 text-sm texto-suave" aria-live="polite" data-filtro-resultado="lista-servicios"></p>
        </div>
    <?php } ?>

    <?php if ($servicios === []) { ?>
        <p class="mt-6 texto-suave">Pronto publicaremos nuestros servicios.</p>
    <?php } else { ?>
        <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" id="lista-servicios">
            <?php foreach ($servicios as $servicio) { ?>
                <li data-valores="<?= implode(',', $servicio->sedeIds) ?>">
                    <article class="tarjeta h-full">
                        <h2 class="text-base">
                            <a class="texto-titular no-underline hover:underline" href="/servicios/<?= $e($servicio->slug) ?>">
                                <?= $e($servicio->nombre) ?>
                            </a>
                        </h2>
                        <?php if ($servicio->horario !== null) { ?>
                            <p class="mt-2 text-sm texto-suave"><?= $e($servicio->horario) ?></p>
                        <?php } ?>
                    </article>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>
</div>

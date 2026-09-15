<?php
/**
 * /especialidades — con filtro por sede sin recargar (criterio de la Fase 2).
 * Espera: $especialidades (list<Especialidad>), $sedes (list<Sede>).
 */
?>
<div class="mx-auto max-w-6xl px-4 py-10">
    <h1>Especialidades</h1>

    <?php /* El filtro solo aparece con MÁS de una sede. El hospital tiene
             sede única (docs/08), y un desplegable de «Todas las sedes» más
             una opción no filtra nada: ofrece una elección que no existe.
             Se decide por los datos, así que si abren una segunda sede el
             filtro vuelve solo. */ ?>
    <?php if (count($sedes) > 1) { ?>
        <div class="mt-6 max-w-xs">
            <label class="etiqueta" for="filtro-sede">Filtrar por sede</label>
            <select class="campo" id="filtro-sede" data-filtro="lista-especialidades">
                <option value="">Todas las sedes</option>
                <?php foreach ($sedes as $sede) { ?>
                    <option value="<?= $sede->id ?>"><?= $e($sede->nombre) ?></option>
                <?php } ?>
            </select>
            <p class="mt-2 text-sm texto-suave" aria-live="polite" data-filtro-resultado="lista-especialidades"></p>
        </div>
    <?php } ?>

    <?php if ($especialidades === []) { ?>
        <p class="mt-6 texto-suave">Pronto publicaremos nuestras especialidades.</p>
    <?php } else { ?>
        <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" id="lista-especialidades">
            <?php foreach ($especialidades as $especialidad) { ?>
                <li data-valores="<?= implode(',', $especialidad->sedeIds) ?>">
                    <article class="tarjeta h-full">
                        <h2 class="text-base">
                            <a class="texto-titular no-underline hover:underline" href="/especialidades/<?= $e($especialidad->slug) ?>">
                                <?= $e($especialidad->nombre) ?>
                            </a>
                        </h2>
                        <?php if ($especialidad->descripcion !== null) { ?>
                            <p class="mt-2 text-sm texto-suave"><?= $e(mb_substr($especialidad->descripcion, 0, 160)) ?></p>
                        <?php } ?>
                        <p class="mt-2 text-sm texto-suave"><?= count($especialidad->sedeIds) ?> sede(s)</p>
                    </article>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>
</div>

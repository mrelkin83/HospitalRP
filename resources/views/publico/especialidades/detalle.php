<?php
/**
 * /especialidades/{slug} — Espera: $especialidad, $sedes (list<Sede>),
 * $profesionales (list<Profesional> ya filtrados: autorizados y activos).
 */
?>
<article class="mx-auto max-w-6xl px-4 py-10">
    <nav aria-label="Miga de pan" class="text-sm texto-suave">
        <a href="/">Inicio</a> › <a href="/especialidades">Especialidades</a>
    </nav>

    <h1 class="mt-4"><?= $e($especialidad->nombre) ?></h1>

    <?php if ($especialidad->descripcion !== null) { ?>
        <p class="mt-4 max-w-prose"><?= $e($especialidad->descripcion) ?></p>
    <?php } ?>

    <?php if ($sedes !== []) { ?>
        <h2 class="mt-8 text-lg">Dónde se presta</h2>
        <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($sedes as $sede) { ?>
                <li><?= $vistas->render('publico/parciales/tarjeta-sede', ['sede' => $sede]) ?></li>
            <?php } ?>
        </ul>
    <?php } ?>

    <?php if ($profesionales !== []) { ?>
        <h2 class="mt-8 text-lg">Profesionales</h2>
        <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($profesionales as $profesional) { ?>
                <li class="tarjeta">
                    <a class="font-semibold texto-titular no-underline hover:underline" href="/directorio/<?= $e($profesional->slug) ?>">
                        <?= $e($profesional->nombre) ?>
                    </a>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>

    <p class="mt-8">
        <a class="boton boton-primario" href="/citas">Solicitar cita</a>
    </p>
</article>

<?php
/**
 * /servicios/{slug} — Espera: $servicio (Servicio), $sedes (list<Sede>).
 */
$soloTelefono = preg_replace('/\D+/', '', $servicio->telefono ?? '');
?>
<article class="mx-auto max-w-6xl px-4 py-10">
    <nav aria-label="Miga de pan" class="text-sm texto-suave">
        <a href="/">Inicio</a> › <a href="/servicios">Servicios</a>
    </nav>

    <h1 class="mt-4"><?= $e($servicio->nombre) ?></h1>

    <?php if ($servicio->descripcion !== null) { ?>
        <p class="mt-4 max-w-prose"><?= $e($servicio->descripcion) ?></p>
    <?php } ?>

    <div class="mt-8 grid gap-8 lg:grid-cols-2">
        <div>
            <?php if ($servicio->requisitos !== null) { ?>
                <h2 class="text-lg">Qué debes traer</h2>
                <p class="mt-2 max-w-prose"><?= nl2br($e($servicio->requisitos)) ?></p>
            <?php } ?>
        </div>
        <div class="tarjeta">
            <h2 class="text-lg">Atención</h2>
            <?php if ($servicio->horario !== null) { ?>
                <p class="mt-2"><?= $e($servicio->horario) ?></p>
            <?php } ?>
            <?php if ($servicio->telefono !== null) { ?>
                <p class="mt-3">
                    <a class="boton boton-primario" href="tel:<?= $e($soloTelefono) ?>">Llamar: <?= $e($servicio->telefono) ?></a>
                </p>
            <?php } ?>
        </div>
    </div>

    <?php if ($sedes !== []) { ?>
        <h2 class="mt-8 text-lg">Dónde se presta</h2>
        <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($sedes as $sede) { ?>
                <li><?= $vistas->render('publico/parciales/tarjeta-sede', ['sede' => $sede]) ?></li>
            <?php } ?>
        </ul>
    <?php } ?>
</article>

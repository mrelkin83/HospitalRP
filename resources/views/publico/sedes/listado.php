<?php
/**
 * /sedes — Espera: $sedes (list<Sede>), $imagenes (array<int, Archivo>).
 */
?>
<div class="mx-auto max-w-6xl px-4 py-10">
    <h1>Nuestras sedes</h1>
    <p class="mt-3 texto-suave max-w-prose">Direcciones, teléfonos y horarios de atención.</p>

    <?php if ($sedes === []) { ?>
        <p class="mt-6 texto-suave">Pronto publicaremos la información de nuestras sedes.</p>
    <?php } else { ?>
        <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($sedes as $sede) { ?>
                <li><?= $vistas->render('publico/parciales/tarjeta-sede', [
                    'sede' => $sede,
                    'imagen' => $sede->imagenId !== null ? ($imagenes[$sede->imagenId] ?? null) : null,
                ]) ?></li>
            <?php } ?>
        </ul>
    <?php } ?>
</div>

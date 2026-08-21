<?php
/**
 * /urgencias — la página que se abre con afán: teléfono primero.
 * Espera: $config (ConfigDinamica), $sedes (list<Sede> con urgencias).
 */
$telefono = $config->texto('contacto.emergencias');
$soloTelefono = preg_replace('/\D+/', '', $telefono);
?>
<div class="mx-auto max-w-6xl px-4 py-10">
    <h1>Urgencias</h1>

    <div class="mt-6 tarjeta bg-error-100 border-error-600 max-w-2xl">
        <h2 class="text-lg text-error-900">¿Es una emergencia?</h2>
        <p class="mt-2 text-error-900">
            Llama ya o acude a la sede con urgencias más cercana. No necesitas
            cita ni autorización para ser atendido en urgencias.
        </p>
        <p class="mt-4">
            <a class="boton boton-peligro text-lg" href="tel:<?= $e($soloTelefono) ?>">
                Llamar a emergencias: <?= $e($telefono) ?>
            </a>
        </p>
    </div>

    <h2 class="mt-10 text-lg">Sedes con urgencias 24 horas</h2>
    <?php if ($sedes === []) { ?>
        <p class="mt-4 texto-suave">Pronto publicaremos las sedes con urgencias.</p>
    <?php } else { ?>
        <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($sedes as $sede) { ?>
                <li><?= $vistas->render('publico/parciales/tarjeta-sede', ['sede' => $sede]) ?></li>
            <?php } ?>
        </ul>
    <?php } ?>

    <h2 class="mt-10 text-lg">¿Qué es una urgencia?</h2>
    <div class="mt-4 max-w-prose flex flex-col gap-3">
        <p>
            Una urgencia es una situación que pone en riesgo tu vida o tu salud y
            necesita atención inmediata: dolor en el pecho, dificultad para
            respirar, pérdida del conocimiento, sangrado abundante, traumas
            fuertes, signos de derrame o intoxicaciones.
        </p>
        <p>
            Si tu situación no es urgente, pide una <a href="/citas">cita
            médica</a>: así el equipo de urgencias puede dedicarse a quienes
            están en riesgo.
        </p>
    </div>
</div>

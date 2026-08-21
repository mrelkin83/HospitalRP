<?php
/**
 * /contacto — Espera: $config (ConfigDinamica), $sedes (list<Sede>).
 */
$callCenter = $config->texto('contacto.call_center');
$pbx = $config->texto('contacto.pbx');
?>
<div class="mx-auto max-w-6xl px-4 py-10">
    <h1>Contacto y canales de atención</h1>

    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="tarjeta">
            <h2 class="text-base">Citas (call center)</h2>
            <p class="mt-2"><a class="font-semibold" href="tel:<?= $e(preg_replace('/\D+/', '', $callCenter)) ?>"><?= $e($callCenter) ?></a></p>
            <p class="mt-1 text-sm texto-suave break-words"><a href="mailto:<?= $e($config->texto('contacto.correo_citas')) ?>"><?= $e($config->texto('contacto.correo_citas')) ?></a></p>
        </div>
        <div class="tarjeta">
            <h2 class="text-base">PBX</h2>
            <p class="mt-2"><a class="font-semibold" href="tel:<?= $e(preg_replace('/\D+/', '', $pbx)) ?>"><?= $e($pbx) ?></a></p>
        </div>
        <div class="tarjeta">
            <h2 class="text-base">Correo de atención</h2>
            <p class="mt-2 break-words"><a href="mailto:<?= $e($config->texto('contacto.correo')) ?>"><?= $e($config->texto('contacto.correo')) ?></a></p>
        </div>
        <div class="tarjeta">
            <h2 class="text-base">PQRSDF</h2>
            <p class="mt-2 text-sm texto-suave">Peticiones, quejas, reclamos, sugerencias, denuncias y felicitaciones.</p>
            <p class="mt-3"><a class="boton boton-primario" href="/atencion-ciudadania/pqrsdf">Radicar en línea</a></p>
        </div>
    </div>

    <p class="mt-6 texto-suave">
        Horario de atención administrativa: <?= $e($config->texto('contacto.horario')) ?> ·
        Ventanilla única: <a href="mailto:<?= $e($config->texto('contacto.correo_ventanilla')) ?>"><?= $e($config->texto('contacto.correo_ventanilla')) ?></a> ·
        Notificaciones judiciales:
        <a href="mailto:<?= $e($config->texto('contacto.correo_notificaciones')) ?>"><?= $e($config->texto('contacto.correo_notificaciones')) ?></a>
    </p>
    <p class="mt-2 texto-suave">
        Línea ética anticorrupción: <?= $e($config->texto('contacto.linea_etica_telefono')) ?> ·
        <a href="mailto:<?= $e($config->texto('contacto.linea_etica_correo')) ?>"><?= $e($config->texto('contacto.linea_etica_correo')) ?></a>
    </p>

    <h2 class="mt-10 text-lg"><?= count($sedes) === 1 ? 'Nuestra sede' : 'Nuestras sedes' ?></h2>
    <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($sedes as $sede) { ?>
            <li><?= $vistas->render('publico/parciales/tarjeta-sede', ['sede' => $sede]) ?></li>
        <?php } ?>
    </ul>
</div>

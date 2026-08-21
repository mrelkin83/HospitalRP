<?php
/**
 * /sedes/{slug} — Espera: $sede (Sede), $imagen (?Archivo).
 */
$soloTelefono = preg_replace('/\D+/', '', $sede->telefono ?? '');
?>
<article class="mx-auto max-w-6xl px-4 py-10">
    <nav aria-label="Miga de pan" class="text-sm texto-suave">
        <a href="/">Inicio</a> › <a href="/sedes">Sedes</a>
    </nav>

    <div class="mt-4 flex flex-wrap items-center gap-3">
        <h1><?= $e($sede->nombre) ?></h1>
        <?php if ($sede->tieneUrgencias) { ?>
            <span class="insignia insignia-exito">Urgencias 24/7</span>
        <?php } ?>
    </div>

    <?php if ($imagen !== null) { ?>
        <figure class="mt-6 max-w-3xl">
            <img class="w-full rounded-xl" src="/archivos/<?= $imagen->id ?>" alt="<?= $e($imagen->textoAlt ?? '') ?>"
                 width="<?= $imagen->ancho ?? 1200 ?>" height="<?= $imagen->alto ?? 600 ?>">
        </figure>
    <?php } ?>

    <div class="mt-8 grid gap-8 lg:grid-cols-2">
        <div>
            <?php if ($sede->descripcion !== null) { ?>
                <p class="max-w-prose"><?= $e($sede->descripcion) ?></p>
            <?php } ?>

            <h2 class="mt-6 text-lg">Cómo llegar</h2>
            <p class="mt-2"><?= $e($sede->direccion) ?>, <?= $e($sede->municipio) ?>, <?= $e($sede->departamento) ?></p>
            <?php if ($sede->enlaceMapa() !== null) { ?>
                <p class="mt-3">
                    <a class="boton boton-secundario" href="<?= $e($sede->enlaceMapa()) ?>" target="_blank" rel="noopener">
                        Ver en el mapa (se abre en otra pestaña)
                    </a>
                </p>
            <?php } ?>
        </div>

        <div class="tarjeta">
            <h2 class="text-lg">Atención</h2>
            <?php if ($sede->telefono !== null) { ?>
                <p class="mt-3">
                    <a class="boton boton-primario" href="tel:<?= $e($soloTelefono) ?>">Llamar: <?= $e($sede->telefono) ?></a>
                </p>
            <?php } ?>
            <?php if ($sede->correo !== null) { ?>
                <p class="mt-3"><a href="mailto:<?= $e($sede->correo) ?>"><?= $e($sede->correo) ?></a></p>
            <?php } ?>
            <?php if ($sede->horario !== []) { ?>
                <h3 class="mt-4 text-base">Horarios</h3>
                <ul class="mt-2 flex flex-col gap-1 text-sm">
                    <?php foreach ($sede->horario as $linea) { ?>
                        <li><?= $e($linea) ?></li>
                    <?php } ?>
                </ul>
            <?php } ?>
        </div>
    </div>
</article>

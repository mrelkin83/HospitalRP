<?php
/**
 * Tarjeta de sede, compartida por /sedes, /urgencias, /contacto y la home.
 * Espera: $sede (Sede), $imagen (?Archivo, opcional).
 */
$imagen ??= null;
$soloTelefono = preg_replace('/\D+/', '', $sede->telefono ?? '');
?>
<article class="tarjeta h-full p-0 overflow-hidden flex flex-col">
    <?php if ($imagen !== null) { ?>
        <img class="h-36 w-full object-cover" src="/archivos/<?= $imagen->id ?>"
             alt="<?= $e($imagen->textoAlt ?? '') ?>" loading="lazy" width="600" height="300">
    <?php } ?>
    <div class="p-4 flex-1 flex flex-col gap-2">
        <h3 class="text-base">
            <a class="texto-titular no-underline hover:underline" href="/sedes/<?= $e($sede->slug) ?>">
                <?= $e($sede->nombre) ?>
            </a>
        </h3>
        <p class="text-sm texto-suave"><?= $e($sede->direccion) ?>, <?= $e($sede->municipio) ?></p>
        <?php if ($sede->horario !== []) { ?>
            <p class="text-sm texto-suave"><?= $e($sede->horario[0]) ?></p>
        <?php } ?>
        <p class="mt-auto flex flex-wrap items-center gap-3 pt-2">
            <?php if ($sede->telefono !== null) { ?>
                <a class="font-semibold whitespace-nowrap" href="tel:<?= $e($soloTelefono) ?>"><?= $e($sede->telefono) ?></a>
            <?php } ?>
            <?php if ($sede->tieneUrgencias) { ?>
                <span class="insignia insignia-exito">Urgencias 24/7</span>
            <?php } ?>
        </p>
    </div>
</article>

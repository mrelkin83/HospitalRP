<?php
/**
 * /directorio/{slug} — Espera: $profesional (Profesional), $foto (?Archivo).
 * Nunca muestra teléfonos ni correos personales.
 */
?>
<article class="mx-auto max-w-6xl px-4 py-10">
    <nav aria-label="Miga de pan" class="text-sm texto-suave">
        <a href="/">Inicio</a> › <a href="/directorio">Directorio médico</a>
    </nav>

    <div class="mt-6 flex flex-wrap items-start gap-6">
        <?php if ($foto !== null) { ?>
            <img class="h-32 w-32 rounded-full object-cover" src="/archivos/<?= $foto->id ?>"
                 alt="<?= $e($foto->textoAlt ?? '') ?>" width="128" height="128">
        <?php } ?>
        <div class="min-w-0 flex-1">
            <h1><?= $e($profesional->nombre) ?></h1>
            <?php if ($profesional->especialidadNombres !== []) { ?>
                <p class="mt-2 flex flex-wrap gap-2">
                    <?php foreach ($profesional->especialidadNombres as $nombre) { ?>
                        <span class="insignia insignia-exito"><?= $e($nombre) ?></span>
                    <?php } ?>
                </p>
            <?php } ?>
            <?php if ($profesional->registroRethus !== null) { ?>
                <p class="mt-2 text-sm texto-suave">Registro ReTHUS: <?= $e($profesional->registroRethus) ?></p>
            <?php } ?>
        </div>
    </div>

    <?php if ($profesional->perfil !== null) { ?>
        <div class="prosa mt-8">
            <p><?= nl2br($e($profesional->perfil)) ?></p>
        </div>
    <?php } ?>

    <p class="mt-8">
        <a class="boton boton-primario" href="/citas">Solicitar cita</a>
    </p>
</article>

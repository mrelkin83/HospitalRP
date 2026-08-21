<?php
/**
 * Biblioteca de medios.
 * Espera: $archivos (list<Archivo>), $mensaje (?string),
 *         $errores (array<string,string>), $tokenCsrf (string).
 */
?>
<h1 class="text-2xl">Biblioteca de medios</h1>
<p class="mt-2 texto-suave">
    Sube aquí las imágenes del sitio. Para usarlas en un contenido, copia su
    dirección (<code>/archivos/número</code>).
</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>

<form class="tarjeta mt-6 grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end"
      method="post" action="/admin/medios" enctype="multipart/form-data">
    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
    <div>
        <label class="etiqueta" for="imagen">Imagen</label>
        <input class="campo" type="file" id="imagen" name="imagen"
               accept="image/jpeg,image/png,image/webp,image/avif"
               <?= isset($errores['imagen']) ? 'aria-invalid="true" aria-describedby="error-imagen"' : '' ?>>
        <?php if (isset($errores['imagen'])) { ?>
            <p class="error-campo" id="error-imagen"><?= $e($errores['imagen']) ?></p>
        <?php } ?>
    </div>
    <div>
        <label class="etiqueta" for="texto_alt">Descripción (texto alternativo)</label>
        <input class="campo" type="text" id="texto_alt" name="texto_alt" maxlength="255"
               <?= isset($errores['texto_alt']) ? 'aria-invalid="true" aria-describedby="error-texto_alt"' : '' ?>>
        <?php if (isset($errores['texto_alt'])) { ?>
            <p class="error-campo" id="error-texto_alt"><?= $e($errores['texto_alt']) ?></p>
        <?php } ?>
    </div>
    <div>
        <button type="submit" class="boton boton-primario">Subir</button>
    </div>
</form>

<?php if ($archivos === []) { ?>
    <div class="tarjeta mt-6 text-center">
        <p class="texto-suave">La biblioteca está vacía. Sube la primera imagen con el formulario de arriba.</p>
    </div>
<?php } else { ?>
    <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <?php foreach ($archivos as $archivo) { ?>
            <li class="tarjeta p-0 overflow-hidden">
                <img class="h-36 w-full object-cover" src="/archivos/<?= $archivo->id ?>"
                     alt="<?= $e($archivo->nombreOriginal) ?>" loading="lazy">
                <div class="p-3">
                    <p class="text-sm font-semibold texto-titular break-words"><?= $e($archivo->nombreOriginal) ?></p>
                    <p class="mt-1 text-sm texto-suave">
                        <code>/archivos/<?= $archivo->id ?></code>
                        · <?= $archivo->ancho ?>×<?= $archivo->alto ?>
                    </p>
                    <p class="mt-1 text-sm texto-suave break-words">
                        Alt: <?= $archivo->textoAlt !== null ? $e($archivo->textoAlt) : '—' ?>
                    </p>
                </div>
            </li>
        <?php } ?>
    </ul>
<?php } ?>

<?php
/**
 * Biblioteca de medios.
 * Espera: $usos (list<array<string,int>> en el mismo orden que $archivos), $archivos (list<Archivo>), $mensaje (?string),
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
        <?php if ($iaActiva) { ?>
            <p class="mt-2">
                <button type="button" class="boton boton-secundario" data-ia="texto_alternativo"
                        data-ia-imagen="#imagen" data-ia-destino="#texto_alt">
                    Describir con IA
                </button>
            </p>
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
        <?php foreach ($archivos as $indice => $archivo) {
            $usosDelArchivo = $usos[$indice] ?? [];
            ?>
            <li class="tarjeta p-0 overflow-hidden flex flex-col">
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

                <?php /* El borrado se apaga cuando la imagen sostiene contenido
                         publicado, y se dice dónde. Borrarla igual dejaría la
                         campaña o la noticia sin foto y sin aviso: su clave
                         foránea es ON DELETE SET NULL. */ ?>
                <div class="mt-auto border-t borde-suave p-3">
                    <?php if ($usosDelArchivo !== []) { ?>
                        <p class="text-xs texto-suave">
                            En uso:
                            <?php $partes = [];
                            foreach ($usosDelArchivo as $seccion => $cuantos) {
                                $partes[] = $cuantos . ' en ' . $seccion;
                            } ?>
                            <?= $e(implode(', ', $partes)) ?>.
                        </p>
                    <?php } else { ?>
                        <form method="post" action="/admin/medios/<?= $archivo->id ?>/eliminar">
                            <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                            <button type="submit" class="boton boton-peligro text-sm py-1 px-3 w-full">Borrar de la biblioteca</button>
                        </form>
                    <?php } ?>
                </div>
            </li>
        <?php } ?>
    </ul>
<?php } ?>

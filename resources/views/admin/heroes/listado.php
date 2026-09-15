<?php
/**
 * Panel · Héroe de la portada.
 * Espera: $heroes (list<fila>), $siguienteOrden (int), $mensaje (?string),
 *         $error (?string), $tokenCsrf (string).
 *
 * Una pantalla y ya: crear arriba, editar en su fila. El héroe se veía en la
 * portada antes de esto, pero se editaba en ocho campos sueltos de Ajustes que
 * ni siquiera dejaban poner una imagen.
 */
$campos = static function (array $f, string $tokenCsrf, int $siguienteOrden) use ($e): string {
    $id = (string) ($f['id'] ?? 'nuevo');
    $tieneImagen = ($f['imagen_id'] ?? null) !== null;
    ob_start(); ?>
    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="etiqueta" for="titulo-<?= $e($id) ?>">Título</label>
            <input class="campo" id="titulo-<?= $e($id) ?>" type="text" name="titulo" required maxlength="160"
                   value="<?= $e((string) ($f['titulo'] ?? '')) ?>">
        </div>
        <div>
            <label class="etiqueta" for="degradado-<?= $e($id) ?>">Continuación del título
                <span class="texto-suave font-normal">— se pinta con el degradado de marca</span>
            </label>
            <input class="campo" id="degradado-<?= $e($id) ?>" type="text" name="titulo_degradado" maxlength="160"
                   value="<?= $e((string) ($f['titulo_degradado'] ?? '')) ?>">
        </div>
    </div>

    <div class="mt-3">
        <label class="etiqueta" for="pildora-<?= $e($id) ?>">Frase corta de arriba
            <span class="texto-suave font-normal">— la del punto que parpadea. Vacía, no se dibuja</span>
        </label>
        <input class="campo" id="pildora-<?= $e($id) ?>" type="text" name="pildora" maxlength="120"
               value="<?= $e((string) ($f['pildora'] ?? '')) ?>">
    </div>

    <div class="mt-3">
        <label class="etiqueta" for="eslogan-<?= $e($id) ?>">Párrafo</label>
        <textarea class="campo" id="eslogan-<?= $e($id) ?>" name="eslogan" rows="3" maxlength="500"><?= $e((string) ($f['eslogan'] ?? '')) ?></textarea>
    </div>

    <fieldset class="mt-4 rounded-lg border border-primario-200 p-4 dark:border-primario-700">
        <legend class="px-2 text-sm font-semibold texto-titular">Botones</legend>
        <p class="text-sm texto-suave">
            Texto y dirección van juntos: con uno solo el botón no se guarda. La dirección
            puede ser una ruta del sitio («/citas»), una dirección completa («https://…»),
            un correo («mailto:…») o un teléfono («tel:…»).
        </p>
        <?php foreach ([1, 2] as $n) { ?>
            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="etiqueta" for="b<?= $n ?>t-<?= $e($id) ?>">Botón <?= $n ?> · texto</label>
                    <input class="campo" id="b<?= $n ?>t-<?= $e($id) ?>" type="text" name="boton<?= $n ?>_texto" maxlength="60"
                           value="<?= $e((string) ($f['boton' . $n . '_texto'] ?? '')) ?>">
                </div>
                <div>
                    <label class="etiqueta" for="b<?= $n ?>u-<?= $e($id) ?>">Botón <?= $n ?> · dirección</label>
                    <input class="campo" id="b<?= $n ?>u-<?= $e($id) ?>" type="text" name="boton<?= $n ?>_url" maxlength="255"
                           value="<?= $e((string) ($f['boton' . $n . '_url'] ?? '')) ?>">
                </div>
            </div>
        <?php } ?>
    </fieldset>

    <fieldset class="mt-4 rounded-lg border border-primario-200 p-4 dark:border-primario-700">
        <legend class="px-2 text-sm font-semibold texto-titular">Imagen de fondo</legend>
        <p class="text-sm texto-suave">
            Ocupa el héroe entero, con un velo oscuro encima para que el texto se siga
            leyendo. Sin imagen, el héroe usa el degradado de marca.
        </p>

        <?php if ($tieneImagen) { ?>
            <p class="mt-3"><img class="max-h-32 rounded-md" src="/archivos/<?= (int) $f['imagen_id'] ?>"
                                 alt="<?= $e((string) ($f['imagen_alt'] ?? 'Imagen actual del héroe')) ?>"></p>
            <label class="mt-2 flex items-center gap-2">
                <input type="checkbox" name="quitar_imagen" value="1">
                <span class="text-sm font-semibold">Quitar esta imagen y volver al degradado</span>
            </label>
        <?php } ?>

        <div class="mt-3 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="etiqueta" for="img-<?= $e($id) ?>"><?= $tieneImagen ? 'Reemplazar imagen' : 'Subir imagen' ?></label>
                <input class="campo" id="img-<?= $e($id) ?>" type="file" name="imagen"
                       accept="image/jpeg,image/png,image/webp,image/avif">
            </div>
            <div>
                <label class="etiqueta" for="alt-<?= $e($id) ?>">Descripción de la imagen
                    <span class="texto-suave font-normal">— obligatoria si subes una</span>
                </label>
                <input class="campo" id="alt-<?= $e($id) ?>" type="text" name="imagen_alt" maxlength="255">
            </div>
        </div>
    </fieldset>

    <div class="mt-4 grid gap-4 sm:grid-cols-3">
        <div>
            <label class="etiqueta" for="orden-<?= $e($id) ?>">Orden</label>
            <input class="campo" id="orden-<?= $e($id) ?>" type="number" name="orden" min="0" max="999"
                   value="<?= (int) ($f['orden'] ?? $siguienteOrden) ?>">
        </div>
        <div>
            <label class="etiqueta" for="desde-<?= $e($id) ?>">Se muestra desde
                <span class="texto-suave font-normal">— opcional</span>
            </label>
            <input class="campo" id="desde-<?= $e($id) ?>" type="date" name="inicio"
                   value="<?= $e((string) ($f['inicio'] ?? '')) ?>">
        </div>
        <div>
            <label class="etiqueta" for="hasta-<?= $e($id) ?>">Hasta
                <span class="texto-suave font-normal">— opcional</span>
            </label>
            <input class="campo" id="hasta-<?= $e($id) ?>" type="date" name="fin"
                   value="<?= $e((string) ($f['fin'] ?? '')) ?>">
        </div>
    </div>

    <div class="mt-4 flex flex-wrap gap-6">
        <label class="flex items-center gap-2">
            <input type="checkbox" name="visible" value="1" <?= ($f['visible'] ?? true) ? 'checked' : '' ?>>
            <span class="font-semibold">Se ve en la portada</span>
        </label>
        <label class="flex items-center gap-2">
            <input type="checkbox" name="mostrar_tarjeta" value="1" <?= ($f['mostrar_tarjeta'] ?? false) ? 'checked' : '' ?>>
            <span class="font-semibold">Mostrar aquí la tarjeta «Rosario Digital»</span>
        </label>
    </div>
    <p class="mt-1 text-sm texto-suave">
        La tarjeta es una sola: al marcarla aquí se quita del héroe donde estuviera.
        En los héroes con fotografía no cabe, así que ahí conviene dejarla sin marcar.
    </p>
    <?php return (string) ob_get_clean();
};
?>
<h1 class="text-2xl">Héroe de la portada</h1>
<p class="mt-2 text-sm texto-suave">
    Lo primero que ve quien entra al sitio. Cada héroe es una diapositiva: si hay
    más de una, se turnan solas. El buscador y los accesos rápidos no se tocan
    aquí — los accesos están en <a class="underline" href="/admin/menus">Menús del sitio</a>
    y los textos de la tarjeta «Rosario Digital» en <a class="underline" href="/admin/configuracion">Ajustes</a>.
</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>
<?php if ($heroes === []) { ?>
    <div class="alerta alerta-aviso mt-4" role="alert">
        No hay ningún héroe. Mientras no crees uno, la portada empieza directamente
        por los accesos rápidos.
    </div>
<?php } ?>

<details class="tarjeta mt-6">
    <summary class="cursor-pointer font-semibold texto-titular">Crear héroe</summary>
    <form class="mt-4" method="post" action="/admin/heroes" enctype="multipart/form-data">
        <?= $campos(['id' => 'nuevo'], $tokenCsrf, $siguienteOrden) ?>
        <p class="mt-4"><button type="submit" class="boton boton-primario">Crear</button></p>
    </form>
</details>

<ul class="mt-6 flex flex-col gap-4">
    <?php foreach ($heroes as $fila) { ?>
        <li class="tarjeta">
            <div class="flex flex-wrap items-center gap-3">
                <span class="insignia"><?= (int) $fila['orden'] ?></span>
                <strong class="texto-titular min-w-0 flex-1">
                    <?= $e((string) $fila['titulo']) ?>
                    <?php if ($fila['titulo_degradado'] !== '') { ?>
                        <span class="font-normal texto-suave"><?= $e((string) $fila['titulo_degradado']) ?></span>
                    <?php } ?>
                </strong>
                <?php if ($fila['mostrar_tarjeta']) { ?>
                    <span class="insignia">Con tarjeta</span>
                <?php } ?>
                <span class="insignia <?= $fila['imagen_id'] !== null ? 'insignia-exito' : 'insignia-aviso' ?>">
                    <?= $fila['imagen_id'] !== null ? 'Con imagen' : 'Degradado' ?>
                </span>
                <span class="insignia <?= $fila['visible'] ? 'insignia-exito' : 'insignia-aviso' ?>">
                    <?= $fila['visible'] ? 'Se ve' : 'Oculto' ?>
                </span>
            </div>

            <details class="mt-3">
                <summary class="cursor-pointer text-sm font-semibold texto-titular">Editar</summary>
                <form class="mt-4" method="post" action="/admin/heroes/<?= (int) $fila['id'] ?>" enctype="multipart/form-data">
                    <?= $campos($fila, $tokenCsrf, $siguienteOrden) ?>
                    <p class="mt-4"><button type="submit" class="boton boton-primario">Guardar cambios</button></p>
                </form>
                <form class="mt-2" method="post" action="/admin/heroes/<?= (int) $fila['id'] ?>/eliminar">
                    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                    <button type="submit" class="text-sm text-error-600 underline">Eliminar este héroe</button>
                </form>
            </details>
        </li>
    <?php } ?>
</ul>

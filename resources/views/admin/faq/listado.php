<?php
/**
 * Preguntas frecuentes.
 * Espera: $preguntas (list<fila>), $categorias (list<{id,nombre}>),
 *         $mensaje (?string), $error (?string), $tokenCsrf.
 */

$campos = static function (array $f, array $categorias, string $tokenCsrf) use ($e): string {
    ob_start(); ?>
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
        <div>
            <label class="etiqueta" for="pregunta-<?= $f['id'] ?>">Pregunta</label>
            <input class="campo" id="pregunta-<?= $f['id'] ?>" type="text" name="pregunta" required maxlength="500"
                   value="<?= $e((string) $f['pregunta']) ?>">
        </div>
        <div class="mt-3">
            <label class="etiqueta" for="respuesta-<?= $f['id'] ?>">Respuesta</label>
            <textarea class="campo" id="respuesta-<?= $f['id'] ?>" name="respuesta" rows="3" required><?= $e((string) $f['respuesta']) ?></textarea>
        </div>
        <div class="mt-3 grid gap-4 sm:grid-cols-3 sm:items-end">
            <div>
                <label class="etiqueta" for="categoria-<?= $f['id'] ?>">Categoría</label>
                <select class="campo" id="categoria-<?= $f['id'] ?>" name="categoria_id">
                    <option value="">Sin categoría</option>
                    <?php foreach ($categorias as $categoria) { ?>
                        <option value="<?= $categoria['id'] ?>" <?= ((int) ($f['categoria_id'] ?? 0)) === $categoria['id'] ? 'selected' : '' ?>>
                            <?= $e($categoria['nombre']) ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div>
                <label class="etiqueta" for="orden-<?= $f['id'] ?>">Orden</label>
                <input class="campo" id="orden-<?= $f['id'] ?>" type="number" name="orden" value="<?= (int) ($f['orden'] ?? 0) ?>">
            </div>
            <label class="flex items-center gap-2 pb-2">
                <input type="checkbox" name="publicada" value="1" <?= ((int) ($f['publicada'] ?? 1)) === 1 ? 'checked' : '' ?>>
                <span class="font-semibold">Publicada</span>
            </label>
        </div>
    <?php return (string) ob_get_clean();
};
?>
<h1 class="text-2xl">Preguntas frecuentes</h1>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>

<details class="tarjeta mt-6">
    <summary class="cursor-pointer font-semibold texto-titular">Crear pregunta</summary>
    <form class="mt-4" method="post" action="/admin/faq">
        <?= $campos(['id' => 'nueva', 'pregunta' => '', 'respuesta' => '', 'categoria_id' => null, 'orden' => 0, 'publicada' => 1], $categorias, $tokenCsrf) ?>
        <p class="mt-4"><button type="submit" class="boton boton-primario">Crear</button></p>
    </form>
</details>

<ul class="mt-6 flex flex-col gap-4">
    <?php foreach ($preguntas as $fila) { ?>
        <li class="tarjeta">
            <div class="flex flex-wrap items-center gap-3">
                <strong class="texto-titular min-w-0 flex-1"><?= $e((string) $fila['pregunta']) ?></strong>
                <?php if (((int) $fila['publicada']) === 1) { ?>
                    <span class="insignia insignia-exito">Publicada</span>
                <?php } else { ?>
                    <span class="insignia insignia-aviso">Oculta</span>
                <?php } ?>
                <?php if (($fila['categoria_nombre'] ?? null) !== null) { ?>
                    <span class="text-sm texto-suave"><?= $e((string) $fila['categoria_nombre']) ?></span>
                <?php } ?>
            </div>
            <details class="mt-3">
                <summary class="cursor-pointer text-sm font-semibold texto-titular">Editar</summary>
                <form class="mt-4" method="post" action="/admin/faq/<?= (int) $fila['id'] ?>">
                    <?= $campos($fila, $categorias, $tokenCsrf) ?>
                    <p class="mt-4"><button type="submit" class="boton boton-primario">Guardar cambios</button></p>
                </form>
                <form class="mt-2" method="post" action="/admin/faq/<?= (int) $fila['id'] ?>/eliminar">
                    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                    <button type="submit" class="text-sm text-error-600 underline">Eliminar pregunta</button>
                </form>
            </details>
        </li>
    <?php } ?>
</ul>

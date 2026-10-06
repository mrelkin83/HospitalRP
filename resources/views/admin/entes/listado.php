<?php
/**
 * Entes que nos vigilan (panel).
 * Espera: $entes, $tipos (list<TipoControl>), $mensaje, $error, $tokenCsrf.
 *
 * Los siete literales del criterio 3.13 del ITA, uno por campo.
 */
$campos = static function (array $f, array $tipos, string $tokenCsrf) use ($e): string {
    $id = (int) ($f['id'] ?? 0);
    ob_start(); ?>
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="etiqueta" for="nombre-<?= $id ?>">Nombre de la autoridad</label>
                <input class="campo" id="nombre-<?= $id ?>" type="text" name="nombre" maxlength="250" required
                       value="<?= $e((string) ($f['nombre'] ?? '')) ?>">
            </div>
            <div>
                <label class="etiqueta" for="tipo-<?= $id ?>">Tipo de control</label>
                <select class="campo" id="tipo-<?= $id ?>" name="tipo_control" required>
                    <?php foreach ($tipos as $tipo) { ?>
                        <option value="<?= $e($tipo->value) ?>" <?= ($f['tipo_control'] ?? '') === $tipo->value ? 'selected' : '' ?>>
                            <?= $e($tipo->etiqueta()) ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="etiqueta" for="dir-<?= $id ?>">Dirección</label>
                <input class="campo" id="dir-<?= $id ?>" type="text" name="direccion" maxlength="300"
                       value="<?= $e((string) ($f['direccion'] ?? '')) ?>">
            </div>
            <div>
                <label class="etiqueta" for="tel-<?= $id ?>">Teléfono</label>
                <input class="campo" id="tel-<?= $id ?>" type="tel" name="telefono" maxlength="120"
                       value="<?= $e((string) ($f['telefono'] ?? '')) ?>">
            </div>
            <div>
                <label class="etiqueta" for="correo-<?= $id ?>">Correo</label>
                <input class="campo" id="correo-<?= $id ?>" type="email" name="correo" maxlength="160"
                       value="<?= $e((string) ($f['correo'] ?? '')) ?>">
            </div>
            <div class="sm:col-span-2">
                <label class="etiqueta" for="url-<?= $id ?>">Sitio web</label>
                <input class="campo" id="url-<?= $id ?>" type="url" name="url" maxlength="500"
                       value="<?= $e((string) ($f['url'] ?? '')) ?>" placeholder="https://...">
            </div>
            <div class="sm:col-span-2">
                <label class="etiqueta" for="mec-<?= $id ?>">Mecanismo de supervisión y notificación</label>
                <textarea class="campo" id="mec-<?= $id ?>" name="mecanismos" rows="2"><?= $e((string) ($f['mecanismos'] ?? '')) ?></textarea>
                <p class="ayuda-campo">Cómo vigila esta autoridad a la entidad. Lo exige el literal g del criterio 3.13.</p>
            </div>
        </div>
        <div class="mt-3 grid gap-4 sm:grid-cols-3 sm:items-end">
            <div>
                <label class="etiqueta" for="orden-<?= $id ?>">Orden</label>
                <input class="campo" id="orden-<?= $id ?>" type="number" name="orden" value="<?= (int) ($f['orden'] ?? 0) ?>">
            </div>
            <div>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="publicado" value="1" <?= ((int) ($f['publicado'] ?? 1)) === 1 ? 'checked' : '' ?>>
                    <span>Publicado</span>
                </label>
            </div>
        </div>
    <?php return (string) ob_get_clean();
};
?>
<h1>Entes que nos vigilan</h1>
<p class="mt-2 texto-suave max-w-prose">
    Autoridades de inspección, vigilancia y control de la entidad. Se publican
    en <a href="/transparencia/entes-de-control">/transparencia/entes-de-control</a>
    y las verifica el criterio 3.13 del ITA, literal por literal. Los campos que
    dejes vacíos simplemente no se muestran.
</p>

<?php if ($mensaje !== null) { ?>
    <p class="aviso aviso-exito mt-4" role="status"><?= $e($mensaje) ?></p>
<?php } ?>
<?php if ($error !== null) { ?>
    <p class="aviso aviso-error mt-4" role="alert"><?= $e($error) ?></p>
<?php } ?>

<section class="tarjeta mt-6">
    <h2 class="text-base">Agregar una autoridad</h2>
    <form method="post" action="/admin/transparencia/entes" class="mt-4">
        <?= $campos([], $tipos, $tokenCsrf) ?>
        <p class="mt-4"><button class="boton boton-primario" type="submit">Agregar</button></p>
    </form>
</section>

<h2 class="mt-10 text-base">Autoridades registradas (<?= count($entes) ?>)</h2>

<?php if ($entes === []) { ?>
    <p class="mt-3 texto-suave">Todavía no hay autoridades registradas.</p>
<?php } else { ?>
    <ul class="mt-4 flex flex-col gap-4">
        <?php foreach ($entes as $ente) { ?>
            <li>
                <article class="tarjeta">
                    <form method="post" action="/admin/transparencia/entes/<?= (int) $ente['id'] ?>">
                        <?= $campos($ente, $tipos, $tokenCsrf) ?>
                        <p class="mt-4"><button class="boton boton-primario" type="submit">Guardar</button></p>
                    </form>
                    <form method="post" action="/admin/transparencia/entes/<?= (int) $ente['id'] ?>/eliminar" class="mt-2">
                        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                        <button class="boton boton-secundario" type="submit">Eliminar</button>
                    </form>
                </article>
            </li>
        <?php } ?>
    </ul>
<?php } ?>

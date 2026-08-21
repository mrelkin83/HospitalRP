<?php
/**
 * Dependencias (áreas administrativas).
 * Espera: $dependencias (list<fila>), $mensaje (?string), $error (?string), $tokenCsrf.
 */

$campos = static function (array $d, string $tokenCsrf) use ($e): string {
    ob_start(); ?>
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="etiqueta" for="nombre-<?= $d['id'] ?>">Nombre</label>
                <input class="campo" id="nombre-<?= $d['id'] ?>" type="text" name="nombre" required maxlength="160" value="<?= $e((string) $d['nombre']) ?>">
            </div>
            <div>
                <label class="etiqueta" for="sigla-<?= $d['id'] ?>">Sigla</label>
                <input class="campo" id="sigla-<?= $d['id'] ?>" type="text" name="sigla" maxlength="20" value="<?= $e((string) ($d['sigla'] ?? '')) ?>">
            </div>
            <div>
                <label class="etiqueta" for="responsable-<?= $d['id'] ?>">Responsable</label>
                <input class="campo" id="responsable-<?= $d['id'] ?>" type="text" name="responsable" maxlength="160" value="<?= $e((string) ($d['responsable'] ?? '')) ?>">
            </div>
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-[1fr_1fr_auto_auto] sm:items-end">
            <div>
                <label class="etiqueta" for="correo-<?= $d['id'] ?>">Correo</label>
                <input class="campo" id="correo-<?= $d['id'] ?>" type="email" name="correo" maxlength="160" value="<?= $e((string) ($d['correo'] ?? '')) ?>">
            </div>
            <div>
                <label class="etiqueta" for="telefono-<?= $d['id'] ?>">Teléfono</label>
                <input class="campo" id="telefono-<?= $d['id'] ?>" type="text" name="telefono" maxlength="40" value="<?= $e((string) ($d['telefono'] ?? '')) ?>">
            </div>
            <div>
                <label class="etiqueta" for="orden-<?= $d['id'] ?>">Orden</label>
                <input class="campo w-24" id="orden-<?= $d['id'] ?>" type="number" name="orden" value="<?= (int) ($d['orden'] ?? 0) ?>">
            </div>
            <label class="flex items-center gap-2 pb-2">
                <input type="checkbox" name="activa" value="1" <?= ((int) ($d['activa'] ?? 1)) === 1 ? 'checked' : '' ?>>
                <span class="font-semibold">Activa</span>
            </label>
        </div>
    <?php return (string) ob_get_clean();
};
?>
<h1 class="text-2xl">Dependencias</h1>
<p class="mt-2 texto-suave">Áreas responsables de documentos y trámites.</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>

<details class="tarjeta mt-6">
    <summary class="cursor-pointer font-semibold texto-titular">Crear dependencia</summary>
    <form class="mt-4" method="post" action="/admin/dependencias">
        <?= $campos(['id' => 'nueva', 'nombre' => ''], $tokenCsrf) ?>
        <p class="mt-4"><button type="submit" class="boton boton-primario">Crear</button></p>
    </form>
</details>

<ul class="mt-6 flex flex-col gap-4">
    <?php foreach ($dependencias as $dependencia) { ?>
        <li class="tarjeta">
            <div class="flex flex-wrap items-center gap-3">
                <strong class="texto-titular"><?= $e((string) $dependencia['nombre']) ?></strong>
                <?php if (($dependencia['sigla'] ?? null) !== null) { ?>
                    <span class="text-sm texto-suave"><?= $e((string) $dependencia['sigla']) ?></span>
                <?php } ?>
                <?php if (((int) $dependencia['activa']) !== 1) { ?>
                    <span class="insignia insignia-aviso">Inactiva</span>
                <?php } ?>
            </div>
            <details class="mt-3">
                <summary class="cursor-pointer text-sm font-semibold texto-titular">Editar</summary>
                <form class="mt-4" method="post" action="/admin/dependencias/<?= (int) $dependencia['id'] ?>">
                    <?= $campos($dependencia, $tokenCsrf) ?>
                    <p class="mt-4"><button type="submit" class="boton boton-primario">Guardar cambios</button></p>
                </form>
            </details>
        </li>
    <?php } ?>
</ul>

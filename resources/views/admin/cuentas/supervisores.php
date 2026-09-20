<?php
/**
 * A5 — Supervisores.
 * Espera: $supervisores, $usuariosElegibles, $mensaje, $error, $tokenCsrf.
 */
?>
<h1 class="text-2xl">Supervisores</h1>
<p class="mt-2 texto-suave">
    El nombre y el cargo se escriben aquí exactamente como deben aparecer en
    el informe de supervisión: ese PDF es un documento oficial.
</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>

<details class="tarjeta mt-4">
    <summary class="cursor-pointer font-semibold texto-titular">Vincular supervisor</summary>
    <form class="mt-3 grid gap-3 sm:grid-cols-2" method="post" action="/admin/cuentas-cobro/supervisores">
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
        <input type="hidden" name="activo" value="1">
        <div class="sm:col-span-2">
            <label class="etiqueta" for="sup-usuario">Usuario del panel</label>
            <select class="campo" id="sup-usuario" name="usuario_id" required>
                <option value="">Seleccione…</option>
                <?php foreach ($usuariosElegibles as $usuario) { ?>
                    <option value="<?= (int) $usuario['id'] ?>">
                        <?= $e($usuario['nombre'] . ' — ' . $usuario['correo']) ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div>
            <label class="etiqueta" for="sup-nombre">Nombre como va en el PDF</label>
            <input class="campo" id="sup-nombre" type="text" name="nombre_completo" required maxlength="240"
                   placeholder="KENNYS MELGAREJO ZULETA">
        </div>
        <div>
            <label class="etiqueta" for="sup-cargo">Cargo como va en el PDF</label>
            <input class="campo" id="sup-cargo" type="text" name="cargo" required maxlength="160"
                   placeholder="PROFESIONAL UNIVERSITARIO (FACTURACIÓN)">
        </div>
        <div>
            <label class="etiqueta" for="sup-dependencia">Dependencia</label>
            <input class="campo" id="sup-dependencia" type="text" name="dependencia" maxlength="160">
        </div>
        <div class="flex items-end">
            <button class="boton-principal" type="submit">Vincular</button>
        </div>
    </form>
</details>

<div class="tarjeta mt-4 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left texto-suave">
                <th class="p-2">Nombre en el PDF</th><th class="p-2">Cargo</th><th class="p-2">Usuario</th>
                <th class="p-2">Contratos</th><th class="p-2">Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($supervisores === []) { ?>
                <tr><td class="p-4 texto-suave" colspan="5">Aún no hay supervisores vinculados.</td></tr>
            <?php } ?>
            <?php foreach ($supervisores as $supervisor) { $sid = (int) $supervisor['id']; ?>
                <tr class="border-t borde-suave">
                    <td class="p-2 font-semibold"><?= $e((string) $supervisor['nombre_completo']) ?></td>
                    <td class="p-2"><?= $e((string) $supervisor['cargo']) ?></td>
                    <td class="p-2"><?= $e((string) $supervisor['usuario_correo']) ?></td>
                    <td class="p-2"><?= (int) $supervisor['contratos'] ?></td>
                    <td class="p-2"><?= ((int) $supervisor['activo'] === 1) ? 'Activo' : 'Inactivo' ?></td>
                </tr>
                <tr class="border-t borde-suave">
                    <td class="p-2 pb-4" colspan="5">
                        <details>
                            <summary class="cursor-pointer text-sm texto-suave">Editar</summary>
                            <form class="mt-3 grid gap-3 sm:grid-cols-4" method="post"
                                  action="/admin/cuentas-cobro/supervisores/<?= $sid ?>">
                                <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                                <div>
                                    <label class="etiqueta" for="sup-nombre-<?= $sid ?>">Nombre</label>
                                    <input class="campo" id="sup-nombre-<?= $sid ?>" type="text" name="nombre_completo" required
                                           maxlength="240" value="<?= $e((string) $supervisor['nombre_completo']) ?>">
                                </div>
                                <div>
                                    <label class="etiqueta" for="sup-cargo-<?= $sid ?>">Cargo</label>
                                    <input class="campo" id="sup-cargo-<?= $sid ?>" type="text" name="cargo" required
                                           maxlength="160" value="<?= $e((string) $supervisor['cargo']) ?>">
                                </div>
                                <div>
                                    <label class="etiqueta" for="sup-dep-<?= $sid ?>">Dependencia</label>
                                    <input class="campo" id="sup-dep-<?= $sid ?>" type="text" name="dependencia"
                                           maxlength="160" value="<?= $e((string) ($supervisor['dependencia'] ?? '')) ?>">
                                </div>
                                <label class="flex items-end gap-2 pb-2 text-sm">
                                    <input type="checkbox" name="activo" value="1" <?= (int) $supervisor['activo'] === 1 ? 'checked' : '' ?>>
                                    Activo
                                </label>
                                <div class="sm:col-span-4">
                                    <button class="boton-principal" type="submit">Guardar</button>
                                </div>
                            </form>
                        </details>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

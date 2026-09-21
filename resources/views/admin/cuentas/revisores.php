<?php
/**
 * Revisores de cuentas de cobro (pantalla del administrador).
 * Espera: $revisores, $usuariosElegibles, $mensaje, $error, $tokenCsrf.
 */
?>
<h1 class="text-2xl">Revisores</h1>
<p class="mt-2 texto-suave">
    El equipo que verifica cada cuenta enviada y la firma antes de que
    llegue al supervisor. Cada revisor sube su propia firma desde su
    «Bandeja de revisión».
</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>

<details class="tarjeta mt-4">
    <summary class="cursor-pointer font-semibold texto-titular">Vincular revisor</summary>
    <form class="mt-3 grid gap-3 sm:grid-cols-2" method="post" action="/admin/cuentas-cobro/revisores">
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
        <input type="hidden" name="activo" value="1">
        <div class="sm:col-span-2">
            <label class="etiqueta" for="rev-usuario">Usuario del panel</label>
            <select class="campo" id="rev-usuario" name="usuario_id" required>
                <option value="">Seleccione…</option>
                <?php foreach ($usuariosElegibles as $usuario) { ?>
                    <option value="<?= (int) $usuario['id'] ?>">
                        <?= $e($usuario['nombre'] . ' — ' . $usuario['correo']) ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div>
            <label class="etiqueta" for="rev-nombre">Nombre completo</label>
            <input class="campo" id="rev-nombre" type="text" name="nombre_completo" required maxlength="240">
        </div>
        <div>
            <label class="etiqueta" for="rev-cargo">Cargo</label>
            <input class="campo" id="rev-cargo" type="text" name="cargo" required maxlength="160">
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
                <th class="p-2">Nombre</th><th class="p-2">Cargo</th><th class="p-2">Usuario</th>
                <th class="p-2">Firma</th><th class="p-2">Revisadas</th><th class="p-2">Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($revisores === []) { ?>
                <tr><td class="p-4 texto-suave" colspan="6">Aún no hay revisores vinculados.</td></tr>
            <?php } ?>
            <?php foreach ($revisores as $revisor) { $rid = (int) $revisor['id']; ?>
                <tr class="border-t borde-suave">
                    <td class="p-2 font-semibold"><?= $e((string) $revisor['nombre_completo']) ?></td>
                    <td class="p-2"><?= $e((string) $revisor['cargo']) ?></td>
                    <td class="p-2"><?= $e((string) $revisor['usuario_correo']) ?></td>
                    <td class="p-2"><?= is_string($revisor['firma_archivo'] ?? null) && $revisor['firma_archivo'] !== '' ? 'Registrada' : 'Pendiente' ?></td>
                    <td class="p-2"><?= (int) $revisor['revisadas'] ?></td>
                    <td class="p-2"><?= ((int) $revisor['activo'] === 1) ? 'Activo' : 'Inactivo' ?></td>
                </tr>
                <tr class="border-t borde-suave">
                    <td class="p-2 pb-4" colspan="6">
                        <details>
                            <summary class="cursor-pointer text-sm texto-suave">Editar</summary>
                            <form class="mt-3 grid gap-3 sm:grid-cols-3" method="post"
                                  action="/admin/cuentas-cobro/revisores/<?= $rid ?>">
                                <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                                <div>
                                    <label class="etiqueta" for="rev-nombre-<?= $rid ?>">Nombre</label>
                                    <input class="campo" id="rev-nombre-<?= $rid ?>" type="text" name="nombre_completo" required
                                           maxlength="240" value="<?= $e((string) $revisor['nombre_completo']) ?>">
                                </div>
                                <div>
                                    <label class="etiqueta" for="rev-cargo-<?= $rid ?>">Cargo</label>
                                    <input class="campo" id="rev-cargo-<?= $rid ?>" type="text" name="cargo" required
                                           maxlength="160" value="<?= $e((string) $revisor['cargo']) ?>">
                                </div>
                                <label class="flex items-end gap-2 pb-2 text-sm">
                                    <input type="checkbox" name="activo" value="1" <?= (int) $revisor['activo'] === 1 ? 'checked' : '' ?>>
                                    Activo
                                </label>
                                <div class="sm:col-span-3">
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

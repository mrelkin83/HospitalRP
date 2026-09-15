<?php
/**
 * Cuentas del panel.
 * Espera: $usuarios (list<fila>), $roles, $dependencias, $credencial (?array),
 * $usuarioActual (?int), $mensaje (?string), $error (?string), $tokenCsrf.
 */

use App\Core\Support\Fechas;
?>
<h1 class="text-2xl">Usuarios</h1>
<p class="mt-2 texto-suave">
    Quién entra al panel y con qué permisos. Las contraseñas las genera el
    sistema y solo se muestran una vez: nadie, ni el administrador, puede
    consultar la contraseña de otro.
</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>

<?php if ($credencial !== null) { ?>
    <div class="alerta alerta-aviso mt-4" role="status">
        <p class="font-semibold">
            Contraseña <?= $e((string) $credencial['motivo']) ?> para <?= $e((string) $credencial['correo']) ?>
        </p>
        <p class="mt-2"><code class="text-lg font-bold"><?= $e((string) $credencial['password']) ?></code></p>
        <p class="mt-2 text-sm">
            Anótala y entrégasela en persona. No vuelve a mostrarse, y al entrar
            el sistema le exigirá cambiarla.
        </p>
    </div>
<?php } ?>

<details class="tarjeta mt-6">
    <summary class="cursor-pointer font-semibold texto-titular">Crear usuario</summary>
    <form class="mt-4" method="post" action="/admin/usuarios">
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="etiqueta" for="nuevo-nombre">Nombre completo</label>
                <input class="campo" id="nuevo-nombre" type="text" name="nombre" required maxlength="160">
            </div>
            <div>
                <label class="etiqueta" for="nuevo-correo">Correo institucional</label>
                <input class="campo" id="nuevo-correo" type="email" name="correo" required maxlength="160">
                <p class="mt-1 text-sm texto-suave">Es el usuario de ingreso.</p>
            </div>
            <div>
                <label class="etiqueta" for="nuevo-rol">Rol</label>
                <select class="campo" id="nuevo-rol" name="rol_id" required>
                    <?php foreach ($roles as $rol) { ?>
                        <option value="<?= (int) $rol['id'] ?>"><?= $e((string) $rol['nombre']) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div>
                <label class="etiqueta" for="nuevo-cargo">Cargo</label>
                <input class="campo" id="nuevo-cargo" type="text" name="cargo" maxlength="120">
            </div>
            <div>
                <label class="etiqueta" for="nuevo-dependencia">Dependencia</label>
                <select class="campo" id="nuevo-dependencia" name="dependencia_id">
                    <option value="0">Sin dependencia</option>
                    <?php foreach ($dependencias as $dependencia) { ?>
                        <option value="<?= (int) $dependencia['id'] ?>"><?= $e((string) $dependencia['nombre']) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <p class="mt-4"><button type="submit" class="boton boton-primario">Crear y generar contraseña</button></p>
    </form>
</details>

<ul class="mt-6 flex flex-col gap-4">
    <?php foreach ($usuarios as $usuario) {
        $id = (int) $usuario['id'];
        $esUnoMismo = $id === $usuarioActual;
        $dadoDeBaja = $usuario['eliminado_en'] !== null;
        $bloqueado = $usuario['bloqueado_hasta'] !== null;
        ?>
        <li class="tarjeta">
            <div class="flex flex-wrap items-center gap-3">
                <strong class="texto-titular"><?= $e((string) $usuario['nombre']) ?></strong>
                <span class="text-sm texto-suave"><?= $e((string) $usuario['correo']) ?></span>
                <span class="insignia"><?= $e((string) $usuario['rol_nombre']) ?></span>
                <?php if ($esUnoMismo) { ?>
                    <span class="insignia insignia-exito">Tu cuenta</span>
                <?php } ?>
                <?php if ($dadoDeBaja) { ?>
                    <span class="insignia insignia-error">Dada de baja</span>
                <?php } elseif ((int) $usuario['activo'] !== 1) { ?>
                    <span class="insignia insignia-aviso">Inactiva</span>
                <?php } ?>
                <?php if ((int) $usuario['debe_cambiar_pass'] === 1) { ?>
                    <span class="insignia insignia-aviso">Contraseña provisional</span>
                <?php } ?>
                <?php if ($bloqueado) { ?>
                    <span class="insignia insignia-error">Bloqueada</span>
                <?php } ?>
            </div>

            <p class="mt-2 text-sm texto-suave">
                Último acceso:
                <?= $e(Fechas::conHora($usuario['ultimo_acceso'] === null ? null : (string) $usuario['ultimo_acceso']) ?: 'nunca') ?>
                <?php if ((int) $usuario['intentos_fallidos'] > 0) { ?>
                    · Intentos fallidos: <?= (int) $usuario['intentos_fallidos'] ?>
                <?php } ?>
                <?php if (($usuario['dependencia_nombre'] ?? null) !== null) { ?>
                    · <?= $e((string) $usuario['dependencia_nombre']) ?>
                <?php } ?>
                <?php if (($usuario['cargo'] ?? null) !== null) { ?>
                    · <?= $e((string) $usuario['cargo']) ?>
                <?php } ?>
            </p>

            <?php if (!$dadoDeBaja) { ?>
                <details class="mt-3">
                    <summary class="cursor-pointer text-sm font-semibold texto-titular">Editar</summary>
                    <form class="mt-4" method="post" action="/admin/usuarios/<?= $id ?>">
                        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="etiqueta" for="nombre-<?= $id ?>">Nombre</label>
                                <input class="campo" id="nombre-<?= $id ?>" type="text" name="nombre" required maxlength="160" value="<?= $e((string) $usuario['nombre']) ?>">
                            </div>
                            <div>
                                <label class="etiqueta" for="rol-<?= $id ?>">Rol</label>
                                <?php if ($esUnoMismo) { ?>
                                    <p class="campo texto-suave"><?= $e((string) $usuario['rol_nombre']) ?></p>
                                    <input type="hidden" name="rol_id" value="<?= (int) $usuario['rol_id'] ?>">
                                    <p class="mt-1 text-sm texto-suave">Nadie cambia su propio rol.</p>
                                <?php } else { ?>
                                    <select class="campo" id="rol-<?= $id ?>" name="rol_id">
                                        <?php foreach ($roles as $rol) { ?>
                                            <option value="<?= (int) $rol['id'] ?>" <?= (int) $rol['id'] === (int) $usuario['rol_id'] ? 'selected' : '' ?>><?= $e((string) $rol['nombre']) ?></option>
                                        <?php } ?>
                                    </select>
                                <?php } ?>
                            </div>
                            <div>
                                <label class="etiqueta" for="cargo-<?= $id ?>">Cargo</label>
                                <input class="campo" id="cargo-<?= $id ?>" type="text" name="cargo" maxlength="120" value="<?= $e((string) ($usuario['cargo'] ?? '')) ?>">
                            </div>
                            <div>
                                <label class="etiqueta" for="dependencia-<?= $id ?>">Dependencia</label>
                                <select class="campo" id="dependencia-<?= $id ?>" name="dependencia_id">
                                    <option value="0">Sin dependencia</option>
                                    <?php foreach ($dependencias as $dependencia) { ?>
                                        <option value="<?= (int) $dependencia['id'] ?>" <?= (int) $dependencia['id'] === (int) ($usuario['dependencia_id'] ?? 0) ? 'selected' : '' ?>><?= $e((string) $dependencia['nombre']) ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <?php if ($esUnoMismo) { ?>
                            <input type="hidden" name="activo" value="1">
                        <?php } else { ?>
                            <label class="mt-4 flex items-center gap-2">
                                <input type="checkbox" name="activo" value="1" <?= (int) $usuario['activo'] === 1 ? 'checked' : '' ?>>
                                <span class="font-semibold">Cuenta activa</span>
                            </label>
                        <?php } ?>
                        <p class="mt-4"><button type="submit" class="boton boton-primario">Guardar cambios</button></p>
                    </form>
                </details>
            <?php } ?>

            <div class="mt-3 flex flex-wrap gap-2">
                <?php if ($dadoDeBaja) { ?>
                    <form method="post" action="/admin/usuarios/<?= $id ?>/restaurar">
                        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                        <button type="submit" class="boton boton-secundario">Reactivar cuenta</button>
                    </form>
                <?php } else { ?>
                    <form method="post" action="/admin/usuarios/<?= $id ?>/restablecer">
                        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                        <button type="submit" class="boton boton-secundario">Restablecer contraseña</button>
                    </form>
                    <?php if ($bloqueado || (int) $usuario['intentos_fallidos'] > 0) { ?>
                        <form method="post" action="/admin/usuarios/<?= $id ?>/desbloquear">
                            <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                            <button type="submit" class="boton boton-secundario">Desbloquear</button>
                        </form>
                    <?php } ?>
                    <?php if (!$esUnoMismo) { ?>
                        <form method="post" action="/admin/usuarios/<?= $id ?>/eliminar">
                            <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                            <button type="submit" class="boton boton-peligro">Dar de baja</button>
                        </form>
                    <?php } ?>
                <?php } ?>
            </div>
        </li>
    <?php } ?>
</ul>

<?php
/**
 * Roles y reparto de permisos.
 * Espera: $roles (list<fila con permisos decodificados>), $grupos (catálogo),
 * $mensaje (?string), $error (?string), $tokenCsrf.
 */

/** Casillas de permisos, reutilizadas por el formulario de alta y los de edición. */
$casillas = static function (array $seleccionados, string $sufijo) use ($grupos, $e): string {
    ob_start(); ?>
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <?php foreach ($grupos as $clave => $grupo) { ?>
            <fieldset class="rounded-lg border borde-suave p-3">
                <legend class="px-1 text-sm font-semibold texto-titular"><?= $e($grupo['titulo']) ?></legend>
                <?php foreach ($grupo['permisos'] as $permiso => $descripcion) { ?>
                    <label class="mt-2 flex items-start gap-2 text-sm">
                        <input type="checkbox" name="permisos[]" value="<?= $e($permiso) ?>"
                               id="<?= $e($permiso . '-' . $sufijo) ?>"
                               <?= in_array($permiso, $seleccionados, true) ? 'checked' : '' ?>>
                        <span>
                            <span class="font-semibold"><?= $e($permiso) ?></span><br>
                            <span class="texto-suave"><?= $e($descripcion) ?></span>
                        </span>
                    </label>
                <?php } ?>
            </fieldset>
        <?php } ?>
    </div>
    <?php return (string) ob_get_clean();
};
?>
<h1 class="text-2xl">Roles y permisos</h1>
<p class="mt-2 texto-suave">
    Un rol es un paquete de permisos. Así el hospital deja de trabajar con una
    sola cuenta compartida: prensa publica noticias, ventanilla atiende PQRSDF
    y solo el administrador toca usuarios.
</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>

<details class="tarjeta mt-6">
    <summary class="cursor-pointer font-semibold texto-titular">Crear rol</summary>
    <form class="mt-4" method="post" action="/admin/roles">
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="etiqueta" for="rol-nuevo-nombre">Nombre del rol</label>
                <input class="campo" id="rol-nuevo-nombre" type="text" name="nombre" required maxlength="80"
                       placeholder="Prensa y comunicaciones">
            </div>
            <div>
                <label class="etiqueta" for="rol-nuevo-descripcion">Descripción</label>
                <input class="campo" id="rol-nuevo-descripcion" type="text" name="descripcion" maxlength="255">
            </div>
        </div>
        <?= $casillas([], 'nuevo') ?>
        <p class="mt-4"><button type="submit" class="boton boton-primario">Crear rol</button></p>
    </form>
</details>

<ul class="mt-6 flex flex-col gap-4">
    <?php foreach ($roles as $rol) {
        $id = (int) $rol['id'];
        $protegido = (bool) $rol['protegido'];
        ?>
        <li class="tarjeta">
            <div class="flex flex-wrap items-center gap-3">
                <strong class="texto-titular"><?= $e((string) $rol['nombre']) ?></strong>
                <span class="text-sm texto-suave"><?= $e((string) $rol['slug']) ?></span>
                <span class="insignia"><?= (int) $rol['usuarios'] ?> usuario(s)</span>
                <?php if ($protegido) { ?>
                    <span class="insignia insignia-aviso">Rol protegido</span>
                <?php } ?>
            </div>

            <?php if (($rol['descripcion'] ?? null) !== null) { ?>
                <p class="mt-2 text-sm texto-suave"><?= $e((string) $rol['descripcion']) ?></p>
            <?php } ?>

            <?php if (in_array('*', $rol['permisos'], true)) { ?>
                <p class="mt-3 text-sm">
                    <span class="insignia insignia-exito">Acceso total</span>
                    Este rol lo puede todo, incluidos los módulos que se agreguen después.
                </p>
            <?php } else { ?>
                <p class="mt-3 text-sm texto-suave">
                    <?= $e(implode(', ', $rol['permisos'])) ?: 'Sin permisos: quien lo tenga entra al panel pero no ve ningún módulo.' ?>
                </p>
            <?php } ?>

            <?php if ($protegido) { ?>
                <p class="mt-3 text-sm texto-suave">
                    No se edita ni se elimina: es la llave maestra del panel. Si se le
                    pudiera quitar un permiso por error, recuperarlo exigiría entrar a
                    la base de datos.
                </p>
            <?php } else { ?>
                <details class="mt-3">
                    <summary class="cursor-pointer text-sm font-semibold texto-titular">Editar permisos</summary>
                    <form class="mt-4" method="post" action="/admin/roles/<?= $id ?>">
                        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="etiqueta" for="rol-nombre-<?= $id ?>">Nombre</label>
                                <input class="campo" id="rol-nombre-<?= $id ?>" type="text" name="nombre" required maxlength="80" value="<?= $e((string) $rol['nombre']) ?>">
                            </div>
                            <div>
                                <label class="etiqueta" for="rol-descripcion-<?= $id ?>">Descripción</label>
                                <input class="campo" id="rol-descripcion-<?= $id ?>" type="text" name="descripcion" maxlength="255" value="<?= $e((string) ($rol['descripcion'] ?? '')) ?>">
                            </div>
                        </div>
                        <?= $casillas($rol['permisos'], (string) $id) ?>
                        <p class="mt-4"><button type="submit" class="boton boton-primario">Guardar permisos</button></p>
                    </form>
                </details>

                <?php if ((int) $rol['usuarios'] === 0) { ?>
                    <form class="mt-3" method="post" action="/admin/roles/<?= $id ?>/eliminar">
                        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                        <button type="submit" class="boton boton-peligro">Eliminar rol</button>
                    </form>
                <?php } ?>
            <?php } ?>
        </li>
    <?php } ?>
</ul>

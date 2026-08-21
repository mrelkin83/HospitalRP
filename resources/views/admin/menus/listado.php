<?php
/**
 * Editor de menús.
 * Espera: $menus (list<menu + items>), $mensaje (?string), $tokenCsrf (string).
 */
?>
<h1 class="text-2xl">Menús del sitio</h1>
<p class="mt-2 texto-suave">
    Lo que cambies aquí cambia la navegación del sitio público al instante.
    Los enlaces marcados «obligatorio» los exige la normativa: se pueden
    renombrar u ocultar, no borrar.
</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>

<div class="mt-6 flex flex-col gap-8">
    <?php foreach ($menus as $menu) { ?>
        <section class="tarjeta" aria-labelledby="menu-<?= $menu['id'] ?>">
            <h2 id="menu-<?= $menu['id'] ?>" class="text-base"><?= $e($menu['nombre']) ?></h2>

            <ul class="mt-4 flex flex-col gap-3">
                <?php foreach ($menu['items'] as $item) { ?>
                    <li class="border-b borde-suave pb-3">
                        <form class="grid gap-2 sm:grid-cols-[2fr_2fr_auto_auto_auto_auto] sm:items-center"
                              method="post" action="/admin/menus/items/<?= $item['id'] ?>">
                            <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                            <div>
                                <label class="sr-only" for="et-<?= $item['id'] ?>">Etiqueta</label>
                                <input class="campo" id="et-<?= $item['id'] ?>" type="text" name="etiqueta"
                                       value="<?= $e($item['etiqueta']) ?>" required maxlength="120">
                            </div>
                            <div>
                                <label class="sr-only" for="url-<?= $item['id'] ?>">Dirección</label>
                                <input class="campo" id="url-<?= $item['id'] ?>" type="text" name="url"
                                       value="<?= $e($item['url']) ?>" required maxlength="500">
                            </div>
                            <div>
                                <label class="sr-only" for="or-<?= $item['id'] ?>">Orden</label>
                                <input class="campo w-20" id="or-<?= $item['id'] ?>" type="number" name="orden"
                                       value="<?= $item['orden'] ?>">
                            </div>
                            <label class="flex items-center gap-1 text-sm">
                                <input type="checkbox" name="visible" value="1" <?= $item['visible'] ? 'checked' : '' ?>>
                                Visible
                            </label>
                            <button type="submit" class="boton boton-secundario">Guardar</button>
                            <?php if ($item['protegido']) { ?>
                                <span class="insignia insignia-aviso">Obligatorio</span>
                            <?php } ?>
                        </form>
                        <?php if (!$item['protegido']) { ?>
                            <form class="mt-1" method="post" action="/admin/menus/items/<?= $item['id'] ?>/eliminar">
                                <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                                <button type="submit" class="text-sm text-error-600 underline">Eliminar enlace</button>
                            </form>
                        <?php } ?>
                    </li>
                <?php } ?>
            </ul>

            <details class="mt-4">
                <summary class="cursor-pointer font-semibold texto-titular">Añadir enlace</summary>
                <form class="mt-3 grid gap-2 sm:grid-cols-[2fr_2fr_auto_auto]"
                      method="post" action="/admin/menus/<?= $menu['id'] ?>/items">
                    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                    <div>
                        <label class="sr-only" for="net-<?= $menu['id'] ?>">Etiqueta</label>
                        <input class="campo" id="net-<?= $menu['id'] ?>" type="text" name="etiqueta"
                               placeholder="Etiqueta" required maxlength="120">
                    </div>
                    <div>
                        <label class="sr-only" for="nurl-<?= $menu['id'] ?>">Dirección</label>
                        <input class="campo" id="nurl-<?= $menu['id'] ?>" type="text" name="url"
                               placeholder="/direccion" required maxlength="500">
                    </div>
                    <div>
                        <label class="sr-only" for="nor-<?= $menu['id'] ?>">Orden</label>
                        <input class="campo w-20" id="nor-<?= $menu['id'] ?>" type="number" name="orden" value="99">
                    </div>
                    <button type="submit" class="boton boton-primario">Añadir</button>
                </form>
            </details>
        </section>
    <?php } ?>
</div>

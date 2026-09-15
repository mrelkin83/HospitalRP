<?php
/**
 * Ventanas emergentes del sitio público.
 * Espera: $popups (list<fila>), $imagenes (list<Archivo>), $mensaje (?string),
 *         $error (?string), $tokenCsrf (string).
 */

$campos = static function (array $p, array $imagenes, string $tokenCsrf) use ($e): string {
    $fechaLocal = static fn (?string $f): string => $f === null ? '' : str_replace(' ', 'T', substr($f, 0, 16));
    $sel = static fn (string $actual, string $valor): string => $actual === $valor ? 'selected' : '';
    $id = $p['id'];

    ob_start(); ?>
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">

        <div>
            <label class="etiqueta" for="titulo-<?= $id ?>">Título</label>
            <input class="campo" id="titulo-<?= $id ?>" type="text" name="titulo" required maxlength="200"
                   value="<?= $e((string) $p['titulo']) ?>">
        </div>

        <div class="mt-4">
            <label class="etiqueta" for="mensaje-<?= $id ?>">Mensaje</label>
            <textarea class="campo" id="mensaje-<?= $id ?>" name="mensaje" rows="3" required maxlength="1000"><?= $e((string) $p['mensaje']) ?></textarea>
            <p class="ayuda-campo">Texto plano: los saltos de línea se respetan, el HTML no se admite.</p>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="etiqueta" for="ubicacion-<?= $id ?>">Dónde se muestra</label>
                <select class="campo" id="ubicacion-<?= $id ?>" name="ubicacion">
                    <option value="portada" <?= $sel((string) $p['ubicacion'], 'portada') ?>>Solo en la portada</option>
                    <option value="todas" <?= $sel((string) $p['ubicacion'], 'todas') ?>>En todas las páginas</option>
                </select>
            </div>
            <div>
                <label class="etiqueta" for="frecuencia-<?= $id ?>">Cada cuánto reaparece</label>
                <select class="campo" id="frecuencia-<?= $id ?>" name="frecuencia">
                    <option value="sesion" <?= $sel((string) $p['frecuencia'], 'sesion') ?>>Una vez por visita</option>
                    <option value="diaria" <?= $sel((string) $p['frecuencia'], 'diaria') ?>>Una vez al día</option>
                    <option value="siempre" <?= $sel((string) $p['frecuencia'], 'siempre') ?>>En cada página (insistente)</option>
                </select>
            </div>
        </div>

        <div class="mt-4">
            <label class="etiqueta" for="imagen_id-<?= $id ?>">Imagen (opcional)</label>
            <select class="campo" id="imagen_id-<?= $id ?>" name="imagen_id">
                <option value="0">Sin imagen</option>
                <?php foreach ($imagenes as $imagen) { ?>
                    <option value="<?= $imagen->id ?>" <?= ((int) ($p['imagen_id'] ?? 0)) === $imagen->id ? 'selected' : '' ?>>
                        <?= $e($imagen->nombreOriginal) ?>
                    </option>
                <?php } ?>
            </select>
            <p class="ayuda-campo">Se suben en Medios. Se ve mejor apaisada.</p>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="etiqueta" for="url-<?= $id ?>">Enlace del botón (opcional)</label>
                <input class="campo" id="url-<?= $id ?>" type="text" name="url" maxlength="500"
                       value="<?= $e((string) ($p['url'] ?? '')) ?>">
            </div>
            <div>
                <label class="etiqueta" for="texto_boton-<?= $id ?>">Texto del botón</label>
                <input class="campo" id="texto_boton-<?= $id ?>" type="text" name="texto_boton" maxlength="80"
                       value="<?= $e((string) ($p['texto_boton'] ?? '')) ?>">
            </div>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-4 sm:items-end">
            <div>
                <label class="etiqueta" for="desde-<?= $id ?>">Mostrar desde</label>
                <input class="campo" id="desde-<?= $id ?>" type="datetime-local" name="desde"
                       value="<?= $e($fechaLocal($p['desde'] ?? null)) ?>">
            </div>
            <div>
                <label class="etiqueta" for="hasta-<?= $id ?>">Hasta</label>
                <input class="campo" id="hasta-<?= $id ?>" type="datetime-local" name="hasta"
                       value="<?= $e($fechaLocal($p['hasta'] ?? null)) ?>">
            </div>
            <div>
                <label class="etiqueta" for="prioridad-<?= $id ?>">Prioridad</label>
                <input class="campo" id="prioridad-<?= $id ?>" type="number" name="prioridad"
                       value="<?= (int) $p['prioridad'] ?>">
            </div>
            <label class="flex items-center gap-2 pb-2">
                <input type="checkbox" name="activo" value="1" class="size-4 accent-primario-600"
                       <?= ((int) $p['activo']) === 1 ? 'checked' : '' ?>>
                <span class="font-semibold">Activa</span>
            </label>
        </div>
    <?php return (string) ob_get_clean();
};
?>
<h1 class="text-2xl">Ventanas emergentes</h1>
<p class="mt-2 texto-suave">
    Se muestran encima de la página, así que aparece <strong>una sola</strong>:
    la activa de mayor prioridad. Quien la cierra no vuelve a verla hasta que
    pase el plazo de «cada cuánto reaparece», o hasta que edites su texto. Sin
    fechas, se muestra mientras esté activa.
</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>

<details class="tarjeta mt-6">
    <summary class="cursor-pointer font-semibold texto-titular">Crear ventana emergente</summary>
    <form class="mt-4" method="post" action="/admin/popups">
        <?= $campos([
            'id' => 'nueva', 'titulo' => '', 'mensaje' => '', 'imagen_id' => null,
            'url' => null, 'texto_boton' => null, 'ubicacion' => 'portada',
            'frecuencia' => 'sesion', 'desde' => null, 'hasta' => null,
            'activo' => 0, 'prioridad' => 0,
        ], $imagenes, $tokenCsrf) ?>
        <p class="mt-4"><button type="submit" class="boton boton-primario">Crear ventana emergente</button></p>
    </form>
</details>

<?php if ($popups === []) { ?>
    <p class="texto-suave mt-6">Todavía no hay ninguna ventana emergente.</p>
<?php } else { ?>
    <ul class="mt-6 flex flex-col gap-4">
        <?php foreach ($popups as $popup) { ?>
            <li class="tarjeta">
                <div class="flex flex-wrap items-center gap-3">
                    <strong class="texto-titular"><?= $e((string) $popup['titulo']) ?></strong>
                    <?php if (((int) $popup['activo']) === 1) { ?>
                        <span class="insignia insignia-exito">Activa</span>
                    <?php } else { ?>
                        <span class="insignia insignia-aviso">Inactiva</span>
                    <?php } ?>
                    <span class="text-sm texto-suave">
                        <?= $popup['ubicacion'] === 'todas' ? 'todas las páginas' : 'solo la portada' ?>
                        · <?= $e(match ((string) $popup['frecuencia']) {
                            'siempre' => 'en cada página',
                            'diaria' => 'una vez al día',
                            default => 'una vez por visita',
                        }) ?>
                        · prioridad <?= (int) $popup['prioridad'] ?>
                    </span>
                </div>

                <details class="mt-3">
                    <summary class="cursor-pointer text-sm font-semibold texto-titular">Editar</summary>
                    <form class="mt-4" method="post" action="/admin/popups/<?= (int) $popup['id'] ?>">
                        <?= $campos($popup, $imagenes, $tokenCsrf) ?>
                        <p class="mt-4"><button type="submit" class="boton boton-primario">Guardar cambios</button></p>
                    </form>

                    <?php /* Formulario aparte y no un botón más dentro del de
                             arriba: anidar formularios no es HTML válido y el
                             navegador desarma el interior por su cuenta. */ ?>
                    <form class="mt-4 border-t borde-suave pt-4" method="post"
                          action="/admin/popups/<?= (int) $popup['id'] ?>/eliminar">
                        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                        <button type="submit" class="boton boton-peligro">Eliminar</button>
                        <span class="ms-2 text-sm texto-suave">No se puede deshacer.</span>
                    </form>
                </details>
            </li>
        <?php } ?>
    </ul>
<?php } ?>

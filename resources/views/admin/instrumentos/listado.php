<?php
/**
 * Instrumentos de gestión de la información (panel).
 * Espera: $tipo (TipoInstrumento), $tipos (list<TipoInstrumento>), $filas,
 *         $dependencias, $mensaje, $error, $tokenCsrf.
 *
 * El formulario se genera a partir de `TipoInstrumento::columnas()`. No hay
 * una lista de campos escrita a mano aquí: si cambia el criterio del ITA, se
 * toca el enum y esta pantalla, la vista pública y el CSV cambian a la vez.
 */

/** Campos de un registro, en el orden del criterio ITA. */
$campos = static function (array $f, $tipo, array $dependencias, string $tokenCsrf) use ($e): string {
    $id = (int) ($f['id'] ?? 0);
    $columnas = $tipo->columnas();
    ob_start(); ?>
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
        <div class="grid gap-4 sm:grid-cols-2">
            <?php foreach ($columnas as $campo => $etiqueta) {
                $valor = (string) ($f[$campo] ?? '');
                $campoId = $campo . '-' . $id;
                $largo = in_array($campo, ['descripcion', 'objetivo_excepcion', 'fundamento_juridico'], true); ?>
                <div class="<?= $largo ? 'sm:col-span-2' : '' ?>">
                    <label class="etiqueta" for="<?= $e($campoId) ?>"><?= $e($etiqueta) ?></label>
                    <?php if ($largo) { ?>
                        <textarea class="campo" id="<?= $e($campoId) ?>" name="<?= $e($campo) ?>" rows="2"><?= $e($valor) ?></textarea>
                    <?php } elseif ($campo === 'fecha_generacion') { ?>
                        <input class="campo" id="<?= $e($campoId) ?>" type="date" name="<?= $e($campo) ?>" value="<?= $e($valor) ?>">
                    <?php } elseif ($campo === 'alcance_excepcion') { ?>
                        <select class="campo" id="<?= $e($campoId) ?>" name="<?= $e($campo) ?>">
                            <option value="">Sin definir</option>
                            <option value="total" <?= $valor === 'total' ? 'selected' : '' ?>>Total</option>
                            <option value="parcial" <?= $valor === 'parcial' ? 'selected' : '' ?>>Parcial</option>
                        </select>
                    <?php } elseif ($campo === 'url_datos_abiertos') { ?>
                        <input class="campo" id="<?= $e($campoId) ?>" type="url" name="<?= $e($campo) ?>"
                               maxlength="500" placeholder="https://www.datos.gov.co/..." value="<?= $e($valor) ?>">
                    <?php } else { ?>
                        <input class="campo" id="<?= $e($campoId) ?>" type="text" name="<?= $e($campo) ?>"
                               maxlength="300" value="<?= $e($valor) ?>">
                    <?php } ?>
                </div>
            <?php } ?>
        </div>

        <?php /* `idioma` y `medio_conservacion` son comunes a los tres
                 instrumentos pero el ITA no los lista en todos. Cuando el tipo
                 no los pide, igual se capturan aquí: la tabla los exige. */ ?>
        <div class="mt-3 grid gap-4 sm:grid-cols-4 sm:items-end">
            <?php if (!array_key_exists('idioma', $columnas)) { ?>
                <div>
                    <label class="etiqueta" for="idioma-<?= $id ?>">Idioma</label>
                    <input class="campo" id="idioma-<?= $id ?>" type="text" name="idioma"
                           maxlength="60" value="<?= $e((string) ($f['idioma'] ?? 'Español')) ?>">
                </div>
            <?php } ?>
            <?php if (!array_key_exists('medio_conservacion', $columnas)) { ?>
                <div>
                    <label class="etiqueta" for="medio-<?= $id ?>">Medio de conservación</label>
                    <input class="campo" id="medio-<?= $id ?>" type="text" name="medio_conservacion"
                           maxlength="160" required value="<?= $e((string) ($f['medio_conservacion'] ?? '')) ?>">
                </div>
            <?php } ?>
            <div>
                <label class="etiqueta" for="dep-<?= $id ?>">Dependencia</label>
                <select class="campo" id="dep-<?= $id ?>" name="dependencia_id">
                    <option value="">Sin asignar</option>
                    <?php foreach ($dependencias as $dependencia) { ?>
                        <option value="<?= $dependencia['id'] ?>" <?= ((int) ($f['dependencia_id'] ?? 0)) === $dependencia['id'] ? 'selected' : '' ?>>
                            <?= $e($dependencia['nombre']) ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div>
                <label class="etiqueta" for="orden-<?= $id ?>">Orden</label>
                <input class="campo" id="orden-<?= $id ?>" type="number" name="orden" value="<?= (int) ($f['orden'] ?? 0) ?>">
            </div>
            <div>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="publicado" value="1" <?= ((int) ($f['publicado'] ?? 0)) === 1 ? 'checked' : '' ?>>
                    <span>Publicado</span>
                </label>
            </div>
        </div>
    <?php return (string) ob_get_clean();
};
?>
<h1>Instrumentos de gestión de la información</h1>
<p class="mt-2 texto-suave max-w-prose">
    Los tres instrumentos que exige la Ley 1712 de 2014 (artículo 6, literal d)
    y verifica el criterio 9.1 del ITA. Cada uno se publica como tabla y se
    descarga en CSV. Las columnas son las que pide la norma: no se pueden
    cambiar desde aquí.
</p>

<?php if ($mensaje !== null) { ?>
    <p class="aviso aviso-exito mt-4" role="status"><?= $e($mensaje) ?></p>
<?php } ?>
<?php if ($error !== null) { ?>
    <p class="aviso aviso-error mt-4" role="alert"><?= $e($error) ?></p>
<?php } ?>

<nav class="mt-6 flex flex-wrap gap-2" aria-label="Instrumento">
    <?php foreach ($tipos as $otro) { ?>
        <a class="boton <?= $otro === $tipo ? 'boton-primario' : 'boton-secundario' ?>"
           href="/admin/transparencia/instrumentos/<?= $e($otro->slug()) ?>"
           <?= $otro === $tipo ? 'aria-current="page"' : '' ?>>
            <?= $e($otro->nombre()) ?>
        </a>
    <?php } ?>
</nav>

<p class="mt-4 text-sm texto-suave">
    <?= $e($tipo->descripcion()) ?>
    Se publica en <a href="/transparencia/instrumentos-de-gestion/<?= $e($tipo->slug()) ?>">/transparencia/instrumentos-de-gestion/<?= $e($tipo->slug()) ?></a>.
</p>

<section class="tarjeta mt-6">
    <h2 class="text-base">Agregar un registro</h2>
    <form method="post" action="/admin/transparencia/instrumentos/<?= $e($tipo->slug()) ?>" class="mt-4">
        <?= $campos([], $tipo, $dependencias, $tokenCsrf) ?>
        <p class="mt-4">
            <button class="boton boton-primario" type="submit">Agregar</button>
        </p>
    </form>
</section>

<h2 class="mt-10 text-base">
    Registros de <?= $e($tipo->nombre()) ?> (<?= count($filas) ?>)
</h2>

<?php if ($filas === []) { ?>
    <p class="mt-3 texto-suave">Todavía no hay registros en este instrumento.</p>
<?php } else { ?>
    <ul class="mt-4 flex flex-col gap-4">
        <?php foreach ($filas as $fila) { ?>
            <li>
                <article class="tarjeta">
                    <form method="post" action="/admin/transparencia/instrumentos/<?= $e($tipo->slug()) ?>/<?= (int) $fila['id'] ?>">
                        <?= $campos($fila, $tipo, $dependencias, $tokenCsrf) ?>
                        <p class="mt-4 flex flex-wrap gap-2">
                            <button class="boton boton-primario" type="submit">Guardar</button>
                        </p>
                    </form>
                    <form method="post"
                          action="/admin/transparencia/instrumentos/<?= $e($tipo->slug()) ?>/<?= (int) $fila['id'] ?>/eliminar"
                          class="mt-2">
                        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                        <button class="boton boton-secundario" type="submit">Eliminar</button>
                    </form>
                </article>
            </li>
        <?php } ?>
    </ul>
<?php } ?>

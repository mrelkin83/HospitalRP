<?php
/**
 * Panel · Indicadores estadísticos.
 * Espera: $indicadores, $grupos, $mensaje, $error, $tokenCsrf.
 */
$campos = static function (array $f, array $grupos, string $tokenCsrf) use ($e): string {
    $id = (string) ($f['id'] ?? 'nuevo');
    $periodo = (string) ($f['periodo'] ?? '');
    ob_start(); ?>
    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="etiqueta" for="nombre-<?= $e($id) ?>">Nombre del indicador</label>
            <input class="campo" id="nombre-<?= $e($id) ?>" type="text" name="nombre" required maxlength="200"
                   value="<?= $e((string) ($f['nombre'] ?? '')) ?>">
        </div>
        <div>
            <label class="etiqueta" for="grupo-<?= $e($id) ?>">Grupo</label>
            <select class="campo" id="grupo-<?= $e($id) ?>" name="grupo">
                <?php foreach ($grupos as $codigo => $titulo) { ?>
                    <option value="<?= $e($codigo) ?>" <?= ($f['grupo'] ?? '') === $codigo ? 'selected' : '' ?>><?= $e($titulo) ?></option>
                <?php } ?>
            </select>
        </div>
    </div>

    <div class="mt-3 grid gap-4 sm:grid-cols-4">
        <div>
            <label class="etiqueta" for="periodo-<?= $e($id) ?>">Periodo (mes)</label>
            <?php /* No se pide el día: el periodo es el mes completo. */ ?>
            <input class="campo" id="periodo-<?= $e($id) ?>" type="month" name="periodo" required
                   value="<?= $e($periodo !== '' ? substr($periodo, 0, 7) : '') ?>">
        </div>
        <div>
            <label class="etiqueta" for="valor-<?= $e($id) ?>">Valor</label>
            <input class="campo" id="valor-<?= $e($id) ?>" type="number" step="0.01" name="valor" required
                   value="<?= $e((string) ($f['valor'] ?? '')) ?>">
        </div>
        <div>
            <label class="etiqueta" for="unidad-<?= $e($id) ?>">Unidad</label>
            <input class="campo" id="unidad-<?= $e($id) ?>" type="text" name="unidad" maxlength="40"
                   placeholder="camas, %, millones"
                   value="<?= $e((string) ($f['unidad'] ?? '')) ?>">
        </div>
        <div>
            <label class="etiqueta" for="meta-<?= $e($id) ?>">Meta (opcional)</label>
            <input class="campo" id="meta-<?= $e($id) ?>" type="number" step="0.01" name="meta"
                   value="<?= $e((string) ($f['meta'] ?? '')) ?>">
        </div>
    </div>

    <div class="mt-3">
        <label class="etiqueta" for="fuente-<?= $e($id) ?>">Fuente</label>
        <input class="campo" id="fuente-<?= $e($id) ?>" type="text" name="fuente" maxlength="200"
               placeholder="Oficina de Planeación · corte a 31 de marzo"
               value="<?= $e((string) ($f['fuente'] ?? '')) ?>">
        <p class="mt-1 text-xs texto-suave">
            De dónde sale la cifra y a qué corte. Se muestra al ciudadano junto al dato.
        </p>
    </div>

    <div class="mt-3">
        <label class="etiqueta" for="nota-<?= $e($id) ?>">Nota aclaratoria (opcional)</label>
        <textarea class="campo" id="nota-<?= $e($id) ?>" name="nota" rows="2" maxlength="500"><?= $e((string) ($f['nota'] ?? '')) ?></textarea>
    </div>

    <div class="mt-3 grid gap-4 sm:grid-cols-2 sm:items-end">
        <div>
            <label class="etiqueta" for="orden-<?= $e($id) ?>">Orden dentro del grupo</label>
            <input class="campo" id="orden-<?= $e($id) ?>" type="number" name="orden"
                   value="<?= (int) ($f['orden'] ?? 0) ?>">
        </div>
        <div class="flex flex-col gap-2 pb-2">
            <label class="flex items-center gap-2">
                <input type="checkbox" name="publicado" value="1" <?= ((int) ($f['publicado'] ?? 0)) === 1 ? 'checked' : '' ?>>
                <span class="font-semibold">Publicado</span>
            </label>
            <label class="flex items-center gap-2">
                <input type="checkbox" name="destacado" value="1" <?= ((int) ($f['destacado'] ?? 0)) === 1 ? 'checked' : '' ?>>
                <span class="font-semibold">Destacar en la portada</span>
            </label>
            <p class="text-xs texto-suave">
                La portada muestra como máximo dos destacados, por orden. Solo
                salen los que además estén publicados.
            </p>
        </div>
    </div>
    <?php return (string) ob_get_clean();
};
?>
<h1 class="text-2xl">Indicadores estadísticos</h1>
<p class="mt-2 text-sm texto-suave">
    Lo que se publica en <a href="/estadisticas">/estadisticas</a>: contratación,
    ocupación hospitalaria y estadísticas vitales. Cada carga es un mes: para
    publicar marzo se añade una fila nueva, no se edita la de febrero. Así queda
    la serie histórica y el ciudadano puede comparar.
</p>
<p class="mt-2 text-sm texto-suave">
    Solo cifras agregadas. Ningún dato que permita identificar a un paciente
    entra aquí.
</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>

<details class="tarjeta mt-6">
    <summary class="cursor-pointer font-semibold texto-titular">Cargar indicador</summary>
    <form class="mt-4" method="post" action="/admin/indicadores">
        <?= $campos(['id' => 'nuevo', 'grupo' => 'contratacion'], $grupos, $tokenCsrf) ?>
        <p class="mt-4"><button type="submit" class="boton boton-primario">Cargar</button></p>
    </form>
</details>

<?php if ($indicadores === []) { ?>
    <p class="tarjeta mt-6 texto-suave">
        Todavía no hay indicadores cargados. Mientras no haya ninguno publicado,
        la página pública muestra un aviso en lugar de cifras vacías.
    </p>
<?php } else { ?>
    <ul class="mt-6 flex flex-col gap-4">
        <?php foreach ($indicadores as $fila) { ?>
            <li class="tarjeta">
                <div class="flex flex-wrap items-center gap-3">
                    <strong class="texto-titular min-w-0 flex-1"><?= $e((string) $fila['nombre']) ?></strong>
                    <span class="text-sm texto-suave tabular-nums">
                        <?= $e((string) $fila['valor']) ?><?= ($fila['unidad'] ?? '') !== '' ? ' ' . $e((string) $fila['unidad']) : '' ?>
                    </span>
                    <span class="text-sm texto-suave"><?= $e(substr((string) $fila['periodo'], 0, 7)) ?></span>
                    <span class="text-sm texto-suave"><?= $e($grupos[$fila['grupo']] ?? (string) $fila['grupo']) ?></span>
                    <span class="insignia <?= ((int) $fila['publicado']) === 1 ? 'insignia-exito' : 'insignia-aviso' ?>">
                        <?= ((int) $fila['publicado']) === 1 ? 'Publicado' : 'Borrador' ?>
                    </span>
                    <?php if (((int) $fila['destacado']) === 1) { ?>
                        <span class="insignia">En portada</span>
                    <?php } ?>
                </div>
                <details class="mt-3">
                    <summary class="cursor-pointer text-sm font-semibold texto-titular">Editar</summary>
                    <form class="mt-4" method="post" action="/admin/indicadores/<?= (int) $fila['id'] ?>">
                        <?= $campos($fila, $grupos, $tokenCsrf) ?>
                        <p class="mt-4"><button type="submit" class="boton boton-primario">Guardar cambios</button></p>
                    </form>
                    <form class="mt-2" method="post" action="/admin/indicadores/<?= (int) $fila['id'] ?>/eliminar">
                        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                        <button type="submit" class="text-sm text-error-600 underline">Eliminar este dato</button>
                    </form>
                </details>
            </li>
        <?php } ?>
    </ul>
<?php } ?>

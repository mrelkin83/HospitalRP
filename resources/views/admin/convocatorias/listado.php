<?php
/**
 * Panel · Convocatorias.
 * Espera: $convocatorias, $dependencias, $estados, $tipos, $mensaje, $error, $tokenCsrf.
 */
$etiquetaTipo = [
    'empleo' => 'Empleo', 'contratacion' => 'Contratación',
    'proveedores' => 'Proveedores', 'otra' => 'Otra',
];
$etiquetaEstado = [
    'proxima' => 'Próxima', 'abierta' => 'Abierta', 'en_evaluacion' => 'En evaluación',
    'cerrada' => 'Cerrada', 'desierta' => 'Desierta', 'adjudicada' => 'Adjudicada',
];
$paraInput = static fn (?string $f): string => $f === null ? '' : date('Y-m-d\TH:i', (int) strtotime($f));

$campos = static function (array $f, array $dependencias, array $tipos, array $estados, string $tokenCsrf) use ($e, $etiquetaTipo, $etiquetaEstado, $paraInput): string {
    $id = (string) ($f['id'] ?? 'nueva');
    ob_start(); ?>
    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
    <div>
        <label class="etiqueta" for="titulo-<?= $e($id) ?>">Título</label>
        <input class="campo" id="titulo-<?= $e($id) ?>" type="text" name="titulo" required maxlength="300"
               value="<?= $e((string) ($f['titulo'] ?? '')) ?>">
    </div>
    <div class="mt-3">
        <label class="etiqueta" for="descripcion-<?= $e($id) ?>">Descripción</label>
        <textarea class="campo" id="descripcion-<?= $e($id) ?>" name="descripcion" rows="4" required><?= $e((string) ($f['descripcion'] ?? '')) ?></textarea>
    </div>
    <div class="mt-3 grid gap-4 sm:grid-cols-2">
        <div>
            <label class="etiqueta" for="tipo-<?= $e($id) ?>">Tipo</label>
            <select class="campo" id="tipo-<?= $e($id) ?>" name="tipo">
                <?php foreach ($tipos as $t) { ?>
                    <option value="<?= $e($t) ?>" <?= ($f['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= $e($etiquetaTipo[$t] ?? $t) ?></option>
                <?php } ?>
            </select>
        </div>
        <div>
            <label class="etiqueta" for="estado-<?= $e($id) ?>">Estado</label>
            <select class="campo" id="estado-<?= $e($id) ?>" name="estado">
                <?php foreach ($estados as $s) { ?>
                    <option value="<?= $e($s) ?>" <?= ($f['estado'] ?? '') === $s ? 'selected' : '' ?>><?= $e($etiquetaEstado[$s] ?? $s) ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
    <div class="mt-3 grid gap-4 sm:grid-cols-3">
        <div>
            <label class="etiqueta" for="apertura-<?= $e($id) ?>">Apertura</label>
            <input class="campo" id="apertura-<?= $e($id) ?>" type="datetime-local" name="apertura"
                   value="<?= $e($paraInput($f['apertura'] ?? null)) ?>">
        </div>
        <div>
            <label class="etiqueta" for="cierre-<?= $e($id) ?>">Cierre</label>
            <input class="campo" id="cierre-<?= $e($id) ?>" type="datetime-local" name="cierre"
                   value="<?= $e($paraInput($f['cierre'] ?? null)) ?>">
        </div>
        <div>
            <label class="etiqueta" for="dependencia-<?= $e($id) ?>">Dependencia</label>
            <select class="campo" id="dependencia-<?= $e($id) ?>" name="dependencia_id">
                <option value="">Sin asignar</option>
                <?php foreach ($dependencias as $d) { ?>
                    <option value="<?= (int) $d['id'] ?>" <?= ((int) ($f['dependencia_id'] ?? 0)) === $d['id'] ? 'selected' : '' ?>><?= $e($d['nombre']) ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
    <div class="mt-3 grid gap-4 sm:grid-cols-2 sm:items-end">
        <div>
            <label class="etiqueta" for="url-<?= $e($id) ?>">Enlace externo (SECOP, por ejemplo)</label>
            <input class="campo" id="url-<?= $e($id) ?>" type="url" name="url_externa" maxlength="500"
                   value="<?= $e((string) ($f['url_externa'] ?? '')) ?>">
        </div>
        <label class="flex items-center gap-2 pb-2">
            <input type="checkbox" name="publicada" value="1" <?= ((int) ($f['publicada'] ?? 0)) === 1 ? 'checked' : '' ?>>
            <span class="font-semibold">Publicada</span>
        </label>
    </div>
    <?php return (string) ob_get_clean();
};
?>
<h1 class="text-2xl">Convocatorias</h1>
<p class="mt-2 text-sm texto-suave">
    El estado lo cambias tú: una convocatoria sigue abierta hasta que la cierras,
    aunque la fecha de cierre ya haya pasado.
</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>

<details class="tarjeta mt-6">
    <summary class="cursor-pointer font-semibold texto-titular">Crear convocatoria</summary>
    <form class="mt-4" method="post" action="/admin/convocatorias">
        <?= $campos(['id' => 'nueva', 'tipo' => 'empleo', 'estado' => 'proxima'], $dependencias, $tipos, $estados, $tokenCsrf) ?>
        <p class="mt-4"><button type="submit" class="boton boton-primario">Crear</button></p>
    </form>
</details>

<ul class="mt-6 flex flex-col gap-4">
    <?php foreach ($convocatorias as $fila) { ?>
        <li class="tarjeta">
            <div class="flex flex-wrap items-center gap-3">
                <strong class="texto-titular min-w-0 flex-1"><?= $e((string) $fila['titulo']) ?></strong>
                <span class="insignia <?= ((int) $fila['publicada']) === 1 ? 'insignia-exito' : 'insignia-aviso' ?>">
                    <?= ((int) $fila['publicada']) === 1 ? 'Publicada' : 'Oculta' ?>
                </span>
                <span class="text-sm texto-suave"><?= $e($etiquetaEstado[$fila['estado']] ?? (string) $fila['estado']) ?></span>
            </div>
            <details class="mt-3">
                <summary class="cursor-pointer text-sm font-semibold texto-titular">Editar</summary>
                <form class="mt-4" method="post" action="/admin/convocatorias/<?= (int) $fila['id'] ?>">
                    <?= $campos($fila, $dependencias, $tipos, $estados, $tokenCsrf) ?>
                    <p class="mt-4"><button type="submit" class="boton boton-primario">Guardar cambios</button></p>
                </form>
                <form class="mt-2" method="post" action="/admin/convocatorias/<?= (int) $fila['id'] ?>/eliminar">
                    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                    <button type="submit" class="text-sm text-error-600 underline">Eliminar convocatoria</button>
                </form>
            </details>
        </li>
    <?php } ?>
</ul>

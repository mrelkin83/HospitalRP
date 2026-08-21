<?php
/**
 * Datos abiertos (panel).
 * Espera: $datasets (list<dataset con recursos>), $dependencias, $mensaje,
 *         $error, $tokenCsrf.
 */

$campos = static function (array $d, array $dependencias, string $tokenCsrf) use ($e): string {
    ob_start(); ?>
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="etiqueta" for="titulo-<?= $d['id'] ?>">Título</label>
                <input class="campo" id="titulo-<?= $d['id'] ?>" type="text" name="titulo" required maxlength="300"
                       value="<?= $e((string) ($d['titulo'] ?? '')) ?>">
            </div>
            <div>
                <label class="etiqueta" for="dep-<?= $d['id'] ?>">Dependencia</label>
                <select class="campo" id="dep-<?= $d['id'] ?>" name="dependencia_id">
                    <option value="">Sin asignar</option>
                    <?php foreach ($dependencias as $dependencia) { ?>
                        <option value="<?= $dependencia['id'] ?>" <?= ((int) ($d['dependencia_id'] ?? 0)) === $dependencia['id'] ? 'selected' : '' ?>>
                            <?= $e($dependencia['nombre']) ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="mt-3">
            <label class="etiqueta" for="descripcion-<?= $d['id'] ?>">Descripción</label>
            <textarea class="campo" id="descripcion-<?= $d['id'] ?>" name="descripcion" rows="2" required><?= $e((string) ($d['descripcion'] ?? '')) ?></textarea>
        </div>
        <div class="mt-3 grid gap-4 sm:grid-cols-4 sm:items-end">
            <div>
                <label class="etiqueta" for="per-<?= $d['id'] ?>">Periodicidad</label>
                <select class="campo" id="per-<?= $d['id'] ?>" name="periodicidad">
                    <?php foreach (['anual', 'semestral', 'trimestral', 'mensual', 'ninguna'] as $p) { ?>
                        <option value="<?= $p ?>" <?= ($d['periodicidad'] ?? 'anual') === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div>
                <label class="etiqueta" for="cd-<?= $d['id'] ?>">Cobertura desde</label>
                <input class="campo" id="cd-<?= $d['id'] ?>" type="date" name="cobertura_desde" value="<?= $e((string) ($d['cobertura_desde'] ?? '')) ?>">
            </div>
            <div>
                <label class="etiqueta" for="ch-<?= $d['id'] ?>">Hasta</label>
                <input class="campo" id="ch-<?= $d['id'] ?>" type="date" name="cobertura_hasta" value="<?= $e((string) ($d['cobertura_hasta'] ?? '')) ?>">
            </div>
            <label class="flex items-center gap-2 pb-2">
                <input type="checkbox" name="publicado" value="1" <?= ((int) ($d['publicado'] ?? 0)) === 1 ? 'checked' : '' ?>>
                <span class="font-semibold">Publicado</span>
            </label>
        </div>
    <?php return (string) ob_get_clean();
};
?>
<h1 class="text-2xl">Datos abiertos</h1>
<p class="mt-2 texto-suave max-w-prose">
    Conjuntos de datos en formato abierto (CSV, JSON, XML, XLSX). Un PDF no es
    un dato abierto: para eso está la gestión documental.
</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>

<details class="tarjeta mt-6">
    <summary class="cursor-pointer font-semibold texto-titular">Crear conjunto de datos</summary>
    <form class="mt-4" method="post" action="/admin/datasets">
        <?= $campos(['id' => 'nuevo'], $dependencias, $tokenCsrf) ?>
        <p class="mt-4"><button type="submit" class="boton boton-primario">Crear</button></p>
    </form>
</details>

<ul class="mt-6 flex flex-col gap-4">
    <?php foreach ($datasets as $dataset) { ?>
        <li class="tarjeta">
            <div class="flex flex-wrap items-center gap-3">
                <strong class="texto-titular"><?= $e((string) $dataset['titulo']) ?></strong>
                <?php if (((int) $dataset['publicado']) === 1) { ?>
                    <span class="insignia insignia-exito">Publicado</span>
                <?php } else { ?>
                    <span class="insignia insignia-aviso">Borrador</span>
                <?php } ?>
                <?php foreach ($dataset['recursos'] as $recurso) { ?>
                    <span class="insignia insignia-exito"><?= strtoupper((string) $recurso['formato']) ?></span>
                <?php } ?>
            </div>

            <details class="mt-3">
                <summary class="cursor-pointer text-sm font-semibold texto-titular">Editar</summary>
                <form class="mt-4" method="post" action="/admin/datasets/<?= (int) $dataset['id'] ?>">
                    <?= $campos($dataset, $dependencias, $tokenCsrf) ?>
                    <p class="mt-4"><button type="submit" class="boton boton-primario">Guardar cambios</button></p>
                </form>
            </details>

            <details class="mt-2">
                <summary class="cursor-pointer text-sm font-semibold texto-titular">Recursos (<?= count($dataset['recursos']) ?>)</summary>
                <?php if ($dataset['recursos'] !== []) { ?>
                    <ul class="mt-3 flex flex-col gap-1 text-sm">
                        <?php foreach ($dataset['recursos'] as $recurso) { ?>
                            <li class="flex items-center gap-2">
                                <span class="min-w-0 flex-1">
                                    <strong><?= strtoupper((string) $recurso['formato']) ?></strong>
                                    <?php if ($recurso['archivo_id'] !== null) { ?>
                                        · <a href="/archivos/<?= (int) $recurso['archivo_id'] ?>">archivo</a>
                                    <?php } else { ?>
                                        · <span class="texto-suave break-words"><?= $e((string) $recurso['url_externa']) ?></span>
                                    <?php } ?>
                                    · <?= (int) $recurso['descargas'] ?> descargas
                                </span>
                                <form method="post" action="/admin/datasets/recursos/<?= (int) $recurso['id'] ?>/eliminar">
                                    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                                    <button type="submit" class="text-sm text-error-600 underline">Quitar</button>
                                </form>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
                <form class="mt-3 grid gap-3 sm:grid-cols-[auto_1fr_1fr_auto] sm:items-end"
                      method="post" action="/admin/datasets/<?= (int) $dataset['id'] ?>/recursos" enctype="multipart/form-data">
                    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                    <div>
                        <label class="etiqueta" for="fmt-<?= $dataset['id'] ?>">Formato</label>
                        <select class="campo" id="fmt-<?= $dataset['id'] ?>" name="formato">
                            <option value="csv">CSV</option>
                            <option value="json">JSON</option>
                            <option value="xml">XML</option>
                            <option value="xlsx">XLSX</option>
                        </select>
                    </div>
                    <div>
                        <label class="etiqueta" for="arc-<?= $dataset['id'] ?>">Archivo</label>
                        <input class="campo" id="arc-<?= $dataset['id'] ?>" type="file" name="archivo" accept=".csv,.json,.xml,.xlsx">
                    </div>
                    <div>
                        <label class="etiqueta" for="ur-<?= $dataset['id'] ?>">… o URL externa</label>
                        <input class="campo" id="ur-<?= $dataset['id'] ?>" type="url" name="url_externa" maxlength="500">
                    </div>
                    <button type="submit" class="boton boton-secundario">Añadir</button>
                </form>
            </details>
        </li>
    <?php } ?>
</ul>

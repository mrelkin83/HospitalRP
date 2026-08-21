<?php
/**
 * Panel de transparencia.
 * Espera: $arbol (list<seccion con hijas e items>), $vacias (list<{codigo,nombre}>),
 *         $dependencias, $mensaje, $error, $tokenCsrf.
 */

$formSeccion = static function (array $s, array $dependencias, string $tokenCsrf) use ($e): string {
    ob_start(); ?>
        <form class="mt-3 grid gap-3 sm:grid-cols-[auto_1fr_1fr_auto] sm:items-end"
              method="post" action="/admin/transparencia/secciones/<?= (int) $s['id'] ?>">
            <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
            <label class="flex items-center gap-2 pb-2">
                <input type="checkbox" name="aplica" value="1" <?= ((int) $s['aplica']) === 1 ? 'checked' : '' ?>>
                Aplica
            </label>
            <div>
                <label class="etiqueta" for="dep-<?= $s['id'] ?>">Dependencia responsable</label>
                <select class="campo" id="dep-<?= $s['id'] ?>" name="dependencia_id">
                    <option value="">Sin asignar</option>
                    <?php foreach ($dependencias as $dependencia) { ?>
                        <option value="<?= $dependencia['id'] ?>" <?= ((int) ($s['dependencia_id'] ?? 0)) === $dependencia['id'] ? 'selected' : '' ?>>
                            <?= $e($dependencia['nombre']) ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div>
                <label class="etiqueta" for="per-<?= $s['id'] ?>">Periodicidad de actualización</label>
                <select class="campo" id="per-<?= $s['id'] ?>" name="periodicidad">
                    <?php foreach (['ninguna', 'mensual', 'trimestral', 'semestral', 'anual', 'permanente'] as $p) { ?>
                        <option value="<?= $p ?>" <?= ($s['periodicidad'] ?? 'ninguna') === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                    <?php } ?>
                </select>
            </div>
            <button type="submit" class="boton boton-secundario">Guardar</button>
        </form>
    <?php return (string) ob_get_clean();
};

$formItemNuevo = static function (int $seccionId, string $tokenCsrf) use ($e): string {
    ob_start(); ?>
        <details class="mt-2">
            <summary class="cursor-pointer text-sm font-semibold texto-titular">Añadir ítem</summary>
            <form class="mt-3 grid gap-3 sm:grid-cols-2" method="post" action="/admin/transparencia/secciones/<?= $seccionId ?>/items">
                <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                <div class="sm:col-span-2">
                    <label class="etiqueta" for="ti-<?= $seccionId ?>">Título</label>
                    <input class="campo" id="ti-<?= $seccionId ?>" type="text" name="titulo" required maxlength="300">
                </div>
                <div>
                    <label class="etiqueta" for="doc-<?= $seccionId ?>">Documento (número del listado de documentos)</label>
                    <input class="campo" id="doc-<?= $seccionId ?>" type="number" name="documento_id" min="1">
                </div>
                <div>
                    <label class="etiqueta" for="url-<?= $seccionId ?>">… o enlace externo</label>
                    <input class="campo" id="url-<?= $seccionId ?>" type="url" name="url_externa" maxlength="500" placeholder="https://…">
                </div>
                <div class="sm:col-span-2">
                    <label class="etiqueta" for="de-<?= $seccionId ?>">Descripción (opcional)</label>
                    <input class="campo" id="de-<?= $seccionId ?>" type="text" name="descripcion" maxlength="500">
                </div>
                <p><button type="submit" class="boton boton-primario">Añadir</button></p>
            </form>
        </details>
    <?php return (string) ob_get_clean();
};
?>
<h1 class="text-2xl">Transparencia y acceso a la información</h1>
<p class="mt-2 texto-suave max-w-prose">
    Estructura del Anexo 2 de la Res. 1519 de 2020 (versión estándar —
    <strong>cotejar con la versión vigente del anexo</strong>). Las secciones
    obligatorias no se borran: se marcan «no aplica» si no corresponden.
</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>

<?php if ($vacias !== []) { ?>
    <div class="alerta alerta-aviso mt-4" role="status">
        <span>
            <strong><?= count($vacias) ?> sección(es) obligatoria(s) sin contenido:</strong>
            <?= $e(implode(' · ', array_map(
                static fn (array $v): string => $v['codigo'] . ' ' . $v['nombre'],
                array_slice($vacias, 0, 8),
            ))) ?><?= count($vacias) > 8 ? '…' : '' ?>
        </span>
    </div>
<?php } else { ?>
    <div class="alerta alerta-exito mt-4" role="status">Todas las secciones obligatorias tienen contenido.</div>
<?php } ?>

<div class="mt-6 flex flex-col gap-6">
    <?php foreach ($arbol as $seccion) { ?>
        <section class="tarjeta" aria-labelledby="ts-<?= $seccion['id'] ?>">
            <h2 id="ts-<?= $seccion['id'] ?>" class="text-base">
                <?= $e((string) $seccion['codigo']) ?>. <?= $e((string) $seccion['nombre']) ?>
            </h2>

            <?php foreach ($seccion['hijas'] as $hija) { ?>
                <div class="mt-4 border-t borde-suave pt-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <h3 class="text-sm font-bold texto-titular">
                            <?= $e((string) $hija['codigo']) ?> <?= $e((string) $hija['nombre']) ?>
                        </h3>
                        <?php if (((int) $hija['aplica']) !== 1) { ?>
                            <span class="insignia insignia-aviso">No aplica</span>
                        <?php } elseif (((int) $hija['items_publicados']) === 0) { ?>
                            <span class="insignia insignia-error">Vacía</span>
                        <?php } else { ?>
                            <span class="insignia insignia-exito"><?= (int) $hija['items_publicados'] ?> ítem(s)</span>
                        <?php } ?>
                    </div>

                    <?php if ($hija['items'] !== []) { ?>
                        <ul class="mt-2 flex flex-col gap-1 text-sm">
                            <?php foreach ($hija['items'] as $item) { ?>
                                <li class="flex items-center gap-2">
                                    <span class="min-w-0 flex-1">
                                        <?= $e((string) $item['titulo']) ?>
                                        <span class="texto-suave">
                                            <?= $item['documento_id'] !== null ? '(documento ' . (int) $item['documento_id'] . ')' : '(enlace externo)' ?>
                                        </span>
                                    </span>
                                    <form method="post" action="/admin/transparencia/items/<?= (int) $item['id'] ?>/eliminar">
                                        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                                        <button type="submit" class="text-sm text-error-600 underline">Quitar</button>
                                    </form>
                                </li>
                            <?php } ?>
                        </ul>
                    <?php } ?>

                    <?= $formItemNuevo((int) $hija['id'], $tokenCsrf) ?>

                    <details class="mt-2">
                        <summary class="cursor-pointer text-sm texto-suave">Configuración de la subsección</summary>
                        <?= $formSeccion($hija, $dependencias, $tokenCsrf) ?>
                    </details>
                </div>
            <?php } ?>
        </section>
    <?php } ?>
</div>

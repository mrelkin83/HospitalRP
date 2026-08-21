<?php
/**
 * Formulario de documento (crear y editar).
 * Espera: $documento (?Documento), $enviado (?DocumentoDatos), $errores,
 *         $mensaje, $dependencias, $categorias, $versiones, $tokenCsrf.
 */

use App\Modules\Documentos\Entity\EstadoDocumento;

$v = static fn (mixed $deEnviado, mixed $deDocumento): string => (string) ($deEnviado ?? $deDocumento ?? '');

$valorTitulo = $v($enviado?->titulo, $documento?->titulo);
$valorResumen = $v($enviado?->resumen, $documento?->resumen);
$valorNumeroActo = $v($enviado?->numeroActo, $documento?->numeroActo);
$valorFecha = $v($enviado?->fechaDocumento, $documento?->fechaDocumento);
$valorVigDesde = $v($enviado?->vigenciaDesde, $documento?->vigenciaDesde);
$valorVigHasta = $v($enviado?->vigenciaHasta, $documento?->vigenciaHasta);
$valorReemplaza = $enviado?->reemplazaAId ?? $documento?->reemplazaAId;
$valorSerie = $v($enviado?->serieDocumental, $documento?->serieDocumental);
$valorSubserie = $v($enviado?->subserieDocumental, $documento?->subserieDocumental);
$valorRetencion = $enviado?->tiempoRetencion ?? $documento?->tiempoRetencion;
$valorCategoria = $enviado?->categoriaId ?? $documento?->categoriaId;
$valorDependencia = $enviado?->dependenciaId ?? $documento?->dependenciaId;
$valorEtiquetas = $enviado?->etiquetas ?? implode(', ', $documento?->etiquetas ?? []);

$accion = $documento === null ? '/admin/documentos' : '/admin/documentos/' . $documento->id;

$error = static function (string $campo) use ($errores, $e): string {
    if (!isset($errores[$campo])) {
        return '';
    }

    return '<p class="error-campo" id="error-' . $campo . '">' . $e($errores[$campo]) . '</p>';
};
?>
<div class="flex flex-wrap items-center gap-3">
    <h1 class="text-2xl"><?= $documento === null ? 'Nuevo documento' : 'Editar documento' ?></h1>
    <?php if ($documento !== null) { ?>
        <span class="insignia <?= $documento->estado === EstadoDocumento::Vigente ? 'insignia-exito' : 'insignia-aviso' ?>">
            <?= $e($documento->estado->etiqueta()) ?>
        </span>
        <span class="insignia <?= $documento->publicado ? 'insignia-exito' : 'insignia-aviso' ?>">
            <?= $documento->publicado ? 'En el sitio' : 'No publicado' ?>
        </span>
    <?php } ?>
    <a class="ms-auto text-sm" href="/admin/documentos">Volver al listado</a>
</div>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>

<?php if (isset($errores['publicacion'])) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($errores['publicacion']) ?></div>
<?php } elseif ($errores !== []) { ?>
    <div class="alerta alerta-error mt-4" role="alert">Revisa los campos marcados.</div>
<?php } ?>

<div class="mt-6 grid gap-8 lg:grid-cols-3">
    <form class="lg:col-span-2 flex flex-col gap-5" method="post"
          action="<?= $e($accion) ?>" enctype="multipart/form-data">
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">

        <div>
            <label class="etiqueta" for="titulo">Título</label>
            <input class="campo" type="text" id="titulo" name="titulo" required maxlength="300"
                   value="<?= $e($valorTitulo) ?>"
                   <?= isset($errores['titulo']) ? 'aria-invalid="true" aria-describedby="error-titulo"' : '' ?>>
            <?= $error('titulo') ?>
        </div>

        <div>
            <label class="etiqueta" for="resumen">Resumen</label>
            <textarea class="campo" id="resumen" name="resumen" rows="3" required maxlength="1000"
                      <?= isset($errores['resumen']) ? 'aria-invalid="true" aria-describedby="error-resumen"' : '' ?>><?= $e($valorResumen) ?></textarea>
            <p class="ayuda-campo">De qué trata, para quién aplica y qué cambia. Es lo que el ciudadano lee sin abrir el PDF.</p>
            <?= $error('resumen') ?>
            <?php if ($iaActiva) { ?>
                <p class="mt-2">
                    <button type="button" class="boton boton-secundario" data-ia="resumen_documento"
                            data-ia-titulo="#titulo" data-ia-texto="#resumen" data-ia-destino="#resumen">
                        Resumir con IA
                    </button>
                </p>
                <p class="ayuda-campo">
                    Pega en el campo texto del documento (aunque sea largo o
                    desordenado) y pídele el resumen: lo reemplaza por tres
                    frases que puedes editar.
                </p>
            <?php } ?>
        </div>

        <?php if ($documento === null) { ?>
            <div>
                <label class="etiqueta" for="archivo">Archivo PDF</label>
                <input class="campo" type="file" id="archivo" name="archivo" accept="application/pdf" required
                       <?= isset($errores['archivo']) ? 'aria-invalid="true" aria-describedby="error-archivo"' : '' ?>>
                <?= $error('archivo') ?>
            </div>
        <?php } ?>

        <div class="grid gap-5 sm:grid-cols-3">
            <div>
                <label class="etiqueta" for="fecha_documento">Fecha del documento</label>
                <input class="campo" type="date" id="fecha_documento" name="fecha_documento" required
                       value="<?= $e($valorFecha) ?>"
                       <?= isset($errores['fecha_documento']) ? 'aria-invalid="true" aria-describedby="error-fecha_documento"' : '' ?>>
                <?= $error('fecha_documento') ?>
            </div>
            <div>
                <label class="etiqueta" for="numero_acto">Número de acto (opcional)</label>
                <input class="campo" type="text" id="numero_acto" name="numero_acto" maxlength="80"
                       value="<?= $e($valorNumeroActo) ?>" placeholder="Resolución 123 de 2026">
            </div>
            <div>
                <label class="etiqueta" for="dependencia_id">Dependencia responsable</label>
                <select class="campo" id="dependencia_id" name="dependencia_id">
                    <option value="">— Obligatoria para publicar —</option>
                    <?php foreach ($dependencias as $dependencia) { ?>
                        <option value="<?= $dependencia['id'] ?>" <?= $valorDependencia === $dependencia['id'] ? 'selected' : '' ?>>
                            <?= $e($dependencia['nombre']) ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-3">
            <div>
                <label class="etiqueta" for="categoria_id">Categoría</label>
                <select class="campo" id="categoria_id" name="categoria_id">
                    <option value="">Sin categoría</option>
                    <?php foreach ($categorias as $categoria) { ?>
                        <option value="<?= $categoria['id'] ?>" <?= $valorCategoria === $categoria['id'] ? 'selected' : '' ?>>
                            <?= $e($categoria['nombre']) ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div>
                <label class="etiqueta" for="vigencia_desde">Vigente desde (opcional)</label>
                <input class="campo" type="date" id="vigencia_desde" name="vigencia_desde" value="<?= $e($valorVigDesde) ?>">
            </div>
            <div>
                <label class="etiqueta" for="vigencia_hasta">Vigente hasta (opcional)</label>
                <input class="campo" type="date" id="vigencia_hasta" name="vigencia_hasta" value="<?= $e($valorVigHasta) ?>">
                <?= $error('vigencia_hasta') ?>
            </div>
        </div>

        <div>
            <label class="etiqueta" for="etiquetas">Etiquetas (separadas por coma)</label>
            <input class="campo" type="text" id="etiquetas" name="etiquetas"
                   value="<?= $e($valorEtiquetas) ?>" placeholder="contratación, talento humano">
            <?php if ($iaActiva) { ?>
                <p class="mt-2">
                    <button type="button" class="boton boton-secundario" data-ia="sugerencia_etiquetas"
                            data-ia-titulo="#titulo" data-ia-texto="#resumen" data-ia-destino="#etiquetas">
                        Sugerir etiquetas con IA
                    </button>
                </p>
            <?php } ?>
        </div>

        <details class="tarjeta" <?= $valorSerie !== '' ? 'open' : '' ?>>
            <summary class="cursor-pointer font-semibold texto-titular">Archivo y retención (TRD)</summary>
            <div class="mt-4 grid gap-5 sm:grid-cols-3">
                <div>
                    <label class="etiqueta" for="serie_documental">Serie documental</label>
                    <input class="campo" type="text" id="serie_documental" name="serie_documental" maxlength="160" value="<?= $e($valorSerie) ?>">
                </div>
                <div>
                    <label class="etiqueta" for="subserie_documental">Subserie</label>
                    <input class="campo" type="text" id="subserie_documental" name="subserie_documental" maxlength="160" value="<?= $e($valorSubserie) ?>">
                </div>
                <div>
                    <label class="etiqueta" for="tiempo_retencion">Retención (años)</label>
                    <input class="campo" type="number" id="tiempo_retencion" name="tiempo_retencion" min="0" value="<?= $e((string) ($valorRetencion ?? '')) ?>">
                </div>
            </div>
        </details>

        <details class="tarjeta" <?= $valorReemplaza !== null ? 'open' : '' ?>>
            <summary class="cursor-pointer font-semibold texto-titular">Deroga o reemplaza a otro documento</summary>
            <div class="mt-4">
                <label class="etiqueta" for="reemplaza_a_id">Número (id) del documento reemplazado</label>
                <input class="campo w-40" type="number" id="reemplaza_a_id" name="reemplaza_a_id" min="1"
                       value="<?= $e((string) ($valorReemplaza ?? '')) ?>">
                <p class="ayuda-campo">Está en el listado de documentos. La ficha del viejo enlazará a este.</p>
            </div>
        </details>

        <div>
            <button type="submit" class="boton boton-primario">
                <?= $documento === null ? 'Guardar documento' : 'Guardar cambios' ?>
            </button>
        </div>
    </form>

    <?php if ($documento !== null) { ?>
        <aside class="flex flex-col gap-6">
            <section class="tarjeta" aria-labelledby="t-publicacion">
                <h2 id="t-publicacion" class="text-base">Publicación</h2>
                <p class="mt-2 text-sm texto-suave">
                    Ficha pública: <a href="<?= $e($documento->rutaPublica()) ?>"><?= $e($documento->rutaPublica()) ?></a>
                    · <?= $documento->descargas ?> descargas
                </p>
                <form class="mt-3" method="post" action="/admin/documentos/<?= $documento->id ?>/publicar">
                    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                    <input type="hidden" name="publicado" value="<?= $documento->publicado ? '0' : '1' ?>">
                    <button type="submit" class="boton w-full <?= $documento->publicado ? 'boton-secundario' : 'boton-primario' ?>">
                        <?= $documento->publicado ? 'Retirar del sitio' : 'Publicar en el sitio' ?>
                    </button>
                </form>

                <form class="mt-4" method="post" action="/admin/documentos/<?= $documento->id ?>/estado">
                    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                    <label class="etiqueta" for="estado">Estado normativo</label>
                    <div class="flex gap-2">
                        <select class="campo" id="estado" name="estado">
                            <?php foreach (EstadoDocumento::cases() as $estado) { ?>
                                <option value="<?= $estado->value ?>" <?= $documento->estado === $estado ? 'selected' : '' ?>>
                                    <?= $e($estado->etiqueta()) ?>
                                </option>
                            <?php } ?>
                        </select>
                        <button type="submit" class="boton boton-secundario">Cambiar</button>
                    </div>
                </form>
            </section>

            <section class="tarjeta" aria-labelledby="t-versiones">
                <h2 id="t-versiones" class="text-base">Versiones del archivo</h2>
                <ol class="mt-3 flex flex-col gap-2 text-sm">
                    <?php foreach ($versiones as $version) { ?>
                        <li>
                            <strong>v<?= $version['version'] ?></strong>
                            · <a href="/archivos/<?= $version['archivo_id'] ?>">PDF</a>
                            · <?= $e($version['creado_en']) ?>
                            <?php if ($version['notas'] !== null) { ?>
                                <span class="texto-suave">— <?= $e($version['notas']) ?></span>
                            <?php } ?>
                        </li>
                    <?php } ?>
                </ol>
                <form class="mt-4" method="post" action="/admin/documentos/<?= $documento->id ?>/version" enctype="multipart/form-data">
                    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                    <label class="etiqueta" for="archivo-version">Subir nueva versión (PDF)</label>
                    <input class="campo" type="file" id="archivo-version" name="archivo" accept="application/pdf" required>
                    <label class="etiqueta mt-2" for="notas-version">Qué cambió</label>
                    <input class="campo" type="text" id="notas-version" name="notas" maxlength="500">
                    <button type="submit" class="boton boton-secundario mt-3 w-full">Subir versión</button>
                </form>
            </section>

            <section class="tarjeta" aria-labelledby="t-papelera">
                <h2 id="t-papelera" class="text-base">Papelera</h2>
                <form class="mt-3" method="post" action="/admin/documentos/<?= $documento->id ?>/eliminar">
                    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                    <button type="submit" class="boton boton-peligro w-full">Enviar a la papelera</button>
                </form>
            </section>
        </aside>
    <?php } ?>
</div>

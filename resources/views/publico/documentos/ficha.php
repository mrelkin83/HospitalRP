<?php
/**
 * /documentos/{id}-{slug} — ficha HTML del documento, legible en móvil sin
 * abrir el PDF.
 * Espera: $documento (Documento), $archivo (?Archivo), $reemplazadoPor (?Documento).
 */

use App\Core\Support\Fechas;
use App\Modules\Documentos\Entity\EstadoDocumento;

$peso = null;

if ($archivo !== null) {
    $peso = $archivo->tamano >= 1_048_576
        ? sprintf('%.1f MB', $archivo->tamano / 1_048_576)
        : sprintf('%d KB', max(1, (int) round($archivo->tamano / 1024)));
}
?>
<article class="mx-auto max-w-6xl px-4 py-10">
    <nav aria-label="Miga de pan" class="text-sm texto-suave">
        <a href="/">Inicio</a> › <a href="/documentos">Documentos</a>
    </nav>

    <?php if ($documento->estado === EstadoDocumento::Derogado) { ?>
        <div class="alerta alerta-error mt-4 max-w-3xl" role="alert">
            <span>
                <strong>Este documento está derogado</strong> y se conserva solo como referencia.
                <?php if ($reemplazadoPor !== null) { ?>
                    Lo reemplaza <a href="<?= $e($reemplazadoPor->rutaPublica()) ?>"><?= $e($reemplazadoPor->titulo) ?></a>.
                <?php } ?>
            </span>
        </div>
    <?php } elseif ($documento->estado === EstadoDocumento::Historico) { ?>
        <div class="alerta alerta-aviso mt-4 max-w-3xl" role="status">
            Documento histórico: se conserva como memoria institucional.
        </div>
    <?php } ?>

    <h1 class="mt-4 max-w-3xl"><?= $e($documento->titulo) ?></h1>

    <p class="mt-4 max-w-3xl"><?= $e($documento->resumen) ?></p>

    <p class="mt-6 flex flex-wrap gap-3">
        <a class="boton boton-primario" href="/documentos/<?= $documento->id ?>/descargar">
            Descargar PDF<?= $peso !== null ? ' (' . $e($peso) . ')' : '' ?>
        </a>
    </p>

    <h2 class="mt-10 text-lg">Datos del documento</h2>
    <dl class="mt-4 grid gap-x-8 gap-y-3 sm:grid-cols-2 max-w-3xl">
        <div>
            <dt class="text-sm font-semibold texto-suave">Fecha</dt>
            <dd><?= $e(Fechas::larga($documento->fechaDocumento)) ?></dd>
        </div>
        <?php if ($documento->numeroActo !== null) { ?>
            <div>
                <dt class="text-sm font-semibold texto-suave">Número de acto</dt>
                <dd><?= $e($documento->numeroActo) ?></dd>
            </div>
        <?php } ?>
        <?php if ($documento->dependenciaNombre !== null) { ?>
            <div>
                <dt class="text-sm font-semibold texto-suave">Dependencia responsable</dt>
                <dd><?= $e($documento->dependenciaNombre) ?></dd>
            </div>
        <?php } ?>
        <?php if ($documento->categoriaNombre !== null) { ?>
            <div>
                <dt class="text-sm font-semibold texto-suave">Categoría</dt>
                <dd><?= $e($documento->categoriaNombre) ?></dd>
            </div>
        <?php } ?>
        <div>
            <dt class="text-sm font-semibold texto-suave">Estado</dt>
            <dd><?= $e($documento->estado->etiqueta()) ?></dd>
        </div>
        <div>
            <dt class="text-sm font-semibold texto-suave">Versión</dt>
            <dd>v<?= $documento->version ?></dd>
        </div>
        <?php if ($documento->vigenciaDesde !== null || $documento->vigenciaHasta !== null) { ?>
            <div>
                <dt class="text-sm font-semibold texto-suave">Vigencia</dt>
                <dd>
                    <?= $documento->vigenciaDesde !== null ? 'desde ' . $e(Fechas::larga($documento->vigenciaDesde)) : '' ?>
                    <?= $documento->vigenciaHasta !== null ? ' hasta ' . $e(Fechas::larga($documento->vigenciaHasta)) : '' ?>
                </dd>
            </div>
        <?php } ?>
        <?php if ($documento->serieDocumental !== null) { ?>
            <div>
                <dt class="text-sm font-semibold texto-suave">Serie documental</dt>
                <dd><?= $e($documento->serieDocumental) ?><?= $documento->subserieDocumental !== null ? ' / ' . $e($documento->subserieDocumental) : '' ?></dd>
            </div>
        <?php } ?>
    </dl>

    <?php if ($documento->etiquetas !== []) { ?>
        <p class="mt-6 flex flex-wrap gap-2">
            <?php foreach ($documento->etiquetas as $etiqueta) { ?>
                <span class="insignia insignia-exito"><?= $e($etiqueta) ?></span>
            <?php } ?>
        </p>
    <?php } ?>
</article>

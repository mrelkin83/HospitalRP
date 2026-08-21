<?php
/**
 * /documentos — buscador documental con filtros.
 * Espera: $documentos (list<Documento>), $total (int), $filtros (FiltrosDocumento),
 *         $dependencias, $categorias, $anios (list<int>).
 */

use App\Core\Support\Fechas;
use App\Modules\Documentos\Entity\EstadoDocumento;

$porPagina = 20;
$totalPaginas = max(1, (int) ceil($total / $porPagina));
$parametros = static function (int $pagina) use ($filtros): string {
    return http_build_query(array_filter([
        'q' => $filtros->q !== '' ? $filtros->q : null,
        'dependencia' => $filtros->dependenciaId,
        'categoria' => $filtros->categoriaId,
        'anio' => $filtros->anio,
        'estado' => $filtros->estado !== EstadoDocumento::Vigente ? $filtros->estado->value : null,
        'pagina' => $pagina > 1 ? $pagina : null,
    ]));
};
?>
<div class="mx-auto max-w-6xl px-4 py-10">
    <h1>Documentos</h1>
    <p class="mt-3 texto-suave max-w-prose">
        Normatividad, actos administrativos, informes y demás documentos públicos del hospital.
    </p>

    <form class="tarjeta mt-6" method="get" action="/documentos" role="search">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
            <div class="lg:col-span-2">
                <label class="etiqueta" for="q">Buscar</label>
                <input class="campo" type="search" id="q" name="q" value="<?= $e($filtros->q) ?>"
                       placeholder="Palabras del título o del resumen">
            </div>
            <div>
                <label class="etiqueta" for="dependencia">Dependencia</label>
                <select class="campo" id="dependencia" name="dependencia">
                    <option value="">Todas</option>
                    <?php foreach ($dependencias as $dependencia) { ?>
                        <option value="<?= $dependencia['id'] ?>" <?= $filtros->dependenciaId === $dependencia['id'] ? 'selected' : '' ?>>
                            <?= $e($dependencia['nombre']) ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div>
                <label class="etiqueta" for="anio">Año</label>
                <select class="campo" id="anio" name="anio">
                    <option value="">Todos</option>
                    <?php foreach ($anios as $anio) { ?>
                        <option value="<?= $anio ?>" <?= $filtros->anio === $anio ? 'selected' : '' ?>><?= $anio ?></option>
                    <?php } ?>
                </select>
            </div>
            <div>
                <button type="submit" class="boton boton-primario w-full">Buscar</button>
            </div>
        </div>
        <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label class="etiqueta" for="categoria">Categoría</label>
                <select class="campo" id="categoria" name="categoria">
                    <option value="">Todas</option>
                    <?php foreach ($categorias as $categoria) { ?>
                        <option value="<?= $categoria['id'] ?>" <?= $filtros->categoriaId === $categoria['id'] ? 'selected' : '' ?>>
                            <?= $e($categoria['nombre']) ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div>
                <label class="etiqueta" for="estado">Estado</label>
                <select class="campo" id="estado" name="estado">
                    <option value="vigente">Vigentes (por defecto)</option>
                    <option value="derogado" <?= $filtros->estado === EstadoDocumento::Derogado ? 'selected' : '' ?>>Derogados</option>
                    <option value="historico" <?= $filtros->estado === EstadoDocumento::Historico ? 'selected' : '' ?>>Históricos</option>
                </select>
            </div>
        </div>
    </form>

    <p class="mt-6 texto-suave" aria-live="polite">
        <?= $total ?> documento(s)<?= $filtros->estado !== EstadoDocumento::Vigente ? ' (' . $e($filtros->estado->etiqueta()) . 's)' : '' ?>.
    </p>

    <ul class="mt-4 flex flex-col gap-3">
        <?php foreach ($documentos as $doc) { ?>
            <li>
                <article class="tarjeta">
                    <h2 class="text-base leading-snug">
                        <a class="texto-titular no-underline hover:underline" href="<?= $e($doc->rutaPublica()) ?>">
                            <?= $e($doc->titulo) ?>
                        </a>
                    </h2>
                    <p class="mt-1 text-sm texto-suave">
                        <?= $e(Fechas::larga($doc->fechaDocumento)) ?>
                        <?php if ($doc->numeroActo !== null) { ?> · <?= $e($doc->numeroActo) ?><?php } ?>
                        <?php if ($doc->dependenciaNombre !== null) { ?> · <?= $e($doc->dependenciaNombre) ?><?php } ?>
                        <?php if ($doc->estado !== EstadoDocumento::Vigente) { ?>
                            · <span class="insignia insignia-error"><?= $e($doc->estado->etiqueta()) ?></span>
                        <?php } ?>
                    </p>
                    <p class="mt-2 text-sm"><?= $e(mb_substr($doc->resumen, 0, 240)) ?><?= mb_strlen($doc->resumen) > 240 ? '…' : '' ?></p>
                </article>
            </li>
        <?php } ?>
    </ul>

    <?php if ($totalPaginas > 1) { ?>
        <nav class="mt-8 flex items-center gap-3" aria-label="Paginación de documentos">
            <?php if ($filtros->pagina > 1) { ?>
                <a class="boton boton-secundario" href="/documentos?<?= $e($parametros($filtros->pagina - 1)) ?>">← Anterior</a>
            <?php } ?>
            <span class="text-sm texto-suave">Página <?= $filtros->pagina ?> de <?= $totalPaginas ?></span>
            <?php if ($filtros->pagina < $totalPaginas) { ?>
                <a class="boton boton-secundario" href="/documentos?<?= $e($parametros($filtros->pagina + 1)) ?>">Siguiente →</a>
            <?php } ?>
        </nav>
    <?php } ?>
</div>

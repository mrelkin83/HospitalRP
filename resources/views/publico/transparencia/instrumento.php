<?php
/**
 * /transparencia/instrumentos-de-gestion/{slug}
 * Espera: $tipo (TipoInstrumento), $filas (list<array<string,mixed>>).
 *
 * El ITA 9.1 verifica columna por columna, así que la tabla se pinta COMPLETA
 * aunque haya celdas vacías: esconder una columna sin datos haría imposible
 * ver qué falta por diligenciar, tanto al evaluador como al hospital.
 *
 * Doble presentación por CLAUDE.md §6 («sin tablas anchas en móvil»): el
 * Índice de Información Clasificada tiene trece columnas, imposibles de leer
 * en 360 px. En escritorio va como tabla; en celular, cada registro se
 * reconvierte en una ficha con pares etiqueta/valor.
 */
$columnas = $tipo->columnas();

/** Celda vacía con marca legible y anunciable por lector de pantalla. */
$valor = static function (array $fila, string $campo) use ($e): string {
    $bruto = trim((string) ($fila[$campo] ?? ''));

    if ($bruto === '') {
        return '<span class="texto-suave">Sin diligenciar</span>';
    }

    if ($campo === 'url_datos_abiertos') {
        return '<a href="' . $e($bruto) . '" target="_blank" rel="noopener">Ver el conjunto</a>';
    }

    return $e($bruto);
};
?>
<div class="mx-auto max-w-6xl px-4 py-10">
    <nav aria-label="Miga de pan" class="text-sm texto-suave">
        <a href="/">Inicio</a> › <a href="/transparencia">Transparencia</a>
        › <a href="/transparencia/instrumentos-de-gestion">Instrumentos de gestión</a>
    </nav>

    <h1 class="mt-4"><?= $e($tipo->nombre()) ?></h1>
    <p class="mt-3 texto-suave max-w-prose"><?= $e($tipo->descripcion()) ?></p>

    <?php if ($filas === []) { ?>
        <p class="mt-8 texto-suave">
            Este instrumento está en actualización. Mientras tanto puedes
            solicitarlo por <a href="/atencion-ciudadania/pqrsdf">PQRSDF</a>.
        </p>
    <?php } else { ?>
        <p class="mt-6 flex flex-wrap items-center gap-3 text-sm">
            <a class="boton boton-secundario"
               href="/transparencia/instrumentos-de-gestion/<?= $e($tipo->slug()) ?>.csv">
                Descargar en CSV
            </a>
            <span class="texto-suave">
                <?= count($filas) ?> <?= count($filas) === 1 ? 'registro publicado' : 'registros publicados' ?>
            </span>
        </p>

        <?php /* Escritorio: tabla. `overflow-x-auto` encierra el desborde en
                 su propio contenedor para que el cuerpo de la página nunca
                 ruede en horizontal. */ ?>
        <div class="mt-6 hidden overflow-x-auto md:block">
            <table class="w-full border-collapse text-sm">
                <caption class="sr-only"><?= $e($tipo->nombre()) ?></caption>
                <thead>
                    <tr>
                        <?php foreach ($columnas as $encabezado) { ?>
                            <th scope="col" class="border-b-2 px-3 py-2 text-left align-bottom font-semibold">
                                <?= $e($encabezado) ?>
                            </th>
                        <?php } ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filas as $fila) { ?>
                        <tr>
                            <?php foreach (array_keys($columnas) as $campo) { ?>
                                <td class="border-b px-3 py-2 align-top"><?= $valor($fila, $campo) ?></td>
                            <?php } ?>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <?php /* Móvil: una ficha por registro. */ ?>
        <ul class="mt-6 flex flex-col gap-4 md:hidden">
            <?php foreach ($filas as $fila) { ?>
                <li>
                    <article class="tarjeta">
                        <dl class="flex flex-col gap-2 text-sm">
                            <?php foreach ($columnas as $campo => $encabezado) { ?>
                                <div>
                                    <dt class="font-semibold"><?= $e($encabezado) ?></dt>
                                    <dd class="mt-0.5"><?= $valor($fila, $campo) ?></dd>
                                </div>
                            <?php } ?>
                        </dl>
                    </article>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>

    <p class="mt-8 text-sm texto-suave">
        Publicado conforme al artículo 6, literal d, de la Ley 1712 de 2014 y
        al Anexo 2 de la Resolución 1519 de 2020.
    </p>
</div>

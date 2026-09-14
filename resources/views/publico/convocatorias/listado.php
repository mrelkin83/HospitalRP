<?php
/**
 * /convocatorias — listado filtrable.
 * Espera: $convocatorias, $tipos (codigo => etiqueta), $tipoActivo (?string).
 */
$estados = [
    'abierta' => 'Abierta',
    'proxima' => 'Próxima',
    'en_evaluacion' => 'En evaluación',
    'cerrada' => 'Cerrada',
    'adjudicada' => 'Adjudicada',
    'desierta' => 'Desierta',
];
$fecha = static fn (?string $f): string => $f === null ? '—' : date('d/m/Y', (int) strtotime($f));
?>
<div class="mx-auto max-w-4xl px-4 py-10">
    <h1>Convocatorias</h1>
    <p class="mt-3 texto-suave max-w-prose">
        Convocatorias de empleo, contratación y proveedores. Los procesos de
        contratación también se publican en
        <a href="https://www.colombiacompra.gov.co/secop/secop-ii" rel="noopener">SECOP II</a>.
    </p>

    <nav class="mt-6 flex flex-wrap gap-2" aria-label="Filtrar por tipo">
        <a href="/convocatorias" class="etiqueta<?= $tipoActivo === null ? ' etiqueta-activa' : '' ?>"
           <?= $tipoActivo === null ? 'aria-current="page"' : '' ?>>Todas</a>
        <?php foreach ($tipos as $codigo => $etiqueta) { ?>
            <a href="/convocatorias?tipo=<?= $e((string) $codigo) ?>"
               class="etiqueta<?= $tipoActivo === $codigo ? ' etiqueta-activa' : '' ?>"
               <?= $tipoActivo === $codigo ? 'aria-current="page"' : '' ?>><?= $e((string) $etiqueta) ?></a>
        <?php } ?>
    </nav>

    <?php if ($convocatorias === []) { ?>
        <p class="mt-8 tarjeta texto-suave">
            No hay convocatorias publicadas en esta categoría por ahora.
        </p>
    <?php } else { ?>
        <ul class="mt-8 flex flex-col gap-4">
            <?php foreach ($convocatorias as $c) { ?>
                <li class="tarjeta">
                    <p class="text-sm texto-suave">
                        <?= $e($tipos[$c['tipo']] ?? (string) $c['tipo']) ?> ·
                        <strong><?= $e($estados[$c['estado']] ?? (string) $c['estado']) ?></strong>
                    </p>
                    <h2 class="mt-1 text-lg">
                        <a href="/convocatorias/<?= $e((string) $c['slug']) ?>"><?= $e((string) $c['titulo']) ?></a>
                    </h2>
                    <p class="mt-2 text-sm texto-suave">
                        Apertura: <?= $e($fecha($c['apertura'])) ?> ·
                        Cierre: <?= $e($fecha($c['cierre'])) ?>
                    </p>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>
</div>

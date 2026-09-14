<?php
/**
 * /convocatorias/{slug} — ficha de una convocatoria.
 * Espera: $convocatoria, $documentos, $tipos.
 */
$estados = [
    'abierta' => 'Abierta',
    'proxima' => 'Próxima',
    'en_evaluacion' => 'En evaluación',
    'cerrada' => 'Cerrada',
    'adjudicada' => 'Adjudicada',
    'desierta' => 'Desierta',
];
$fecha = static fn (?string $f): string => $f === null ? 'Sin definir' : date('d/m/Y \a \l\a\s H:i', (int) strtotime($f));
?>
<div class="mx-auto max-w-3xl px-4 py-10">
    <nav aria-label="Ruta" class="text-sm texto-suave">
        <a href="/convocatorias">Convocatorias</a> ·
        <?= $e($tipos[$convocatoria['tipo']] ?? (string) $convocatoria['tipo']) ?>
    </nav>

    <h1 class="mt-2"><?= $e((string) $convocatoria['titulo']) ?></h1>

    <dl class="mt-6 tarjeta grid gap-3 sm:grid-cols-3">
        <div>
            <dt class="text-sm texto-suave">Estado</dt>
            <dd class="texto-titular"><?= $e($estados[$convocatoria['estado']] ?? (string) $convocatoria['estado']) ?></dd>
        </div>
        <div>
            <dt class="text-sm texto-suave">Apertura</dt>
            <dd><?= $e($fecha($convocatoria['apertura'])) ?></dd>
        </div>
        <div>
            <dt class="text-sm texto-suave">Cierre</dt>
            <dd><?= $e($fecha($convocatoria['cierre'])) ?></dd>
        </div>
    </dl>

    <div class="prosa mt-8"><?= $convocatoria['descripcion'] ?></div>

    <?php if ($documentos !== []) { ?>
        <section class="mt-8" aria-labelledby="cv-docs">
            <h2 id="cv-docs" class="text-lg">Documentos de la convocatoria</h2>
            <ul class="mt-3 flex flex-col gap-1.5">
                <?php foreach ($documentos as $d) { ?>
                    <li>
                        <a href="/documentos/<?= (int) $d['id'] ?>-<?= $e((string) $d['slug']) ?>">
                            <?= $e((string) $d['titulo']) ?>
                        </a>
                    </li>
                <?php } ?>
            </ul>
        </section>
    <?php } ?>

    <?php if (!empty($convocatoria['url_externa'])) { ?>
        <p class="mt-8">
            <a class="boton" href="<?= $e((string) $convocatoria['url_externa']) ?>" rel="noopener">
                Ver el proceso completo
            </a>
        </p>
    <?php } ?>
</div>

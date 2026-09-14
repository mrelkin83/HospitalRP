<?php
/**
 * /eventos/{slug} — ficha de un evento.
 * Espera: $evento.
 */
$tipos = [
    'institucional' => 'Institucional',
    'participacion_ciudadana' => 'Participación ciudadana',
    'rendicion_cuentas' => 'Rendición de cuentas',
    'capacitacion' => 'Capacitación',
    'jornada_salud' => 'Jornada de salud',
    'otro' => 'Otro',
];
$inicio = (int) strtotime((string) $evento['inicio']);
$fin = $evento['fin'] === null ? null : (int) strtotime((string) $evento['fin']);
?>
<div class="mx-auto max-w-3xl px-4 py-10">
    <nav aria-label="Ruta" class="text-sm texto-suave">
        <a href="/eventos">Calendario de eventos</a> ·
        <?= $e($tipos[$evento['tipo']] ?? (string) $evento['tipo']) ?>
    </nav>

    <h1 class="mt-2"><?= $e((string) $evento['titulo']) ?></h1>

    <dl class="mt-6 tarjeta grid gap-3 sm:grid-cols-3">
        <div>
            <dt class="text-sm texto-suave">Fecha</dt>
            <dd>
                <time datetime="<?= $e(date('Y-m-d', $inicio)) ?>"><?= $e(date('d/m/Y', $inicio)) ?></time>
            </dd>
        </div>
        <div>
            <dt class="text-sm texto-suave">Hora</dt>
            <dd>
                <?php if ((bool) $evento['todo_el_dia']) { ?>
                    Todo el día
                <?php } else { ?>
                    <?= $e(date('H:i', $inicio)) ?><?= $fin !== null ? ' a ' . $e(date('H:i', $fin)) : '' ?>
                <?php } ?>
            </dd>
        </div>
        <div>
            <dt class="text-sm texto-suave">Lugar</dt>
            <dd><?= $e((string) ($evento['lugar'] ?? 'Por confirmar')) ?></dd>
        </div>
    </dl>

    <?php if (!empty($evento['descripcion'])) { ?>
        <div class="prosa mt-8"><?= $evento['descripcion'] ?></div>
    <?php } ?>

    <?php if (!empty($evento['url_externa'])) { ?>
        <p class="mt-8">
            <a class="boton" href="<?= $e((string) $evento['url_externa']) ?>" rel="noopener">Más información</a>
        </p>
    <?php } ?>
</div>

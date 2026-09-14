<?php
/**
 * /eventos — agenda institucional por mes.
 * Espera: $eventos, $proximos, $mesActual, $mesAnterior, $mesSiguiente.
 *
 * Se navega con enlaces, no con JavaScript: en conexión mala un calendario
 * que solo existe si carga un script es un calendario que no existe.
 */
$meses = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
    'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$tipos = [
    'institucional' => 'Institucional',
    'participacion_ciudadana' => 'Participación ciudadana',
    'rendicion_cuentas' => 'Rendición de cuentas',
    'capacitacion' => 'Capacitación',
    'jornada_salud' => 'Jornada de salud',
    'otro' => 'Otro',
];
$nombreMes = static fn (DateTimeImmutable $d): string => $meses[(int) $d->format('n')] . ' de ' . $d->format('Y');
$enlaceMes = static fn (DateTimeImmutable $d): string => '/eventos?anio=' . $d->format('Y') . '&mes=' . $d->format('n');
$hora = static function (array $ev): string {
    if ((bool) $ev['todo_el_dia']) {
        return 'Todo el día';
    }
    $texto = date('H:i', (int) strtotime((string) $ev['inicio']));

    return $ev['fin'] === null ? $texto : $texto . ' a ' . date('H:i', (int) strtotime((string) $ev['fin']));
};
?>
<div class="mx-auto max-w-4xl px-4 py-10">
    <h1>Calendario de eventos</h1>
    <p class="mt-3 texto-suave max-w-prose">
        Agenda institucional: rendición de cuentas, jornadas de salud,
        capacitaciones y espacios de participación ciudadana.
    </p>

    <nav class="mt-6 flex items-center justify-between gap-4" aria-label="Navegación por mes">
        <a href="<?= $e($enlaceMes($mesAnterior)) ?>" rel="prev">&larr; <?= $e($nombreMes($mesAnterior)) ?></a>
        <h2 class="text-lg texto-titular"><?= $e(ucfirst($nombreMes($mesActual))) ?></h2>
        <a href="<?= $e($enlaceMes($mesSiguiente)) ?>" rel="next"><?= $e($nombreMes($mesSiguiente)) ?> &rarr;</a>
    </nav>

    <?php if ($eventos === []) { ?>
        <p class="mt-8 tarjeta texto-suave">
            No hay eventos publicados en <?= $e($nombreMes($mesActual)) ?>.
        </p>
    <?php } else { ?>
        <ul class="mt-8 flex flex-col gap-4">
            <?php foreach ($eventos as $ev) { ?>
                <li class="tarjeta">
                    <p class="text-sm texto-suave">
                        <time datetime="<?= $e(date('Y-m-d', (int) strtotime((string) $ev['inicio']))) ?>">
                            <?= $e(date('d/m/Y', (int) strtotime((string) $ev['inicio']))) ?>
                        </time>
                        · <?= $e($hora($ev)) ?>
                        · <?= $e($tipos[$ev['tipo']] ?? (string) $ev['tipo']) ?>
                    </p>
                    <h3 class="mt-1 text-base">
                        <a href="/eventos/<?= $e((string) $ev['slug']) ?>"><?= $e((string) $ev['titulo']) ?></a>
                    </h3>
                    <?php if (!empty($ev['lugar'])) { ?>
                        <p class="mt-1 text-sm texto-suave"><?= $e((string) $ev['lugar']) ?></p>
                    <?php } ?>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>

    <?php if ($proximos !== []) { ?>
        <section class="mt-10" aria-labelledby="ev-prox">
            <h2 id="ev-prox" class="text-lg">Próximos eventos</h2>
            <ul class="mt-3 flex flex-col gap-1.5">
                <?php foreach ($proximos as $ev) { ?>
                    <li>
                        <time datetime="<?= $e(date('Y-m-d', (int) strtotime((string) $ev['inicio']))) ?>" class="texto-suave">
                            <?= $e(date('d/m/Y', (int) strtotime((string) $ev['inicio']))) ?>
                        </time>
                        — <a href="/eventos/<?= $e((string) $ev['slug']) ?>"><?= $e((string) $ev['titulo']) ?></a>
                    </li>
                <?php } ?>
            </ul>
        </section>
    <?php } ?>
</div>

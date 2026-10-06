<?php
/**
 * /servidores-publicos — ITA 1.6 y 1.6.1.
 * Espera: $porDependencia (dependencia => list<servidor>), $total (int).
 */
$vinculacion = [
    'planta' => 'Planta',
    'libre_nombramiento' => 'Libre nombramiento y remoción',
    'provisional' => 'Provisional',
    'contrato' => 'Contrato de prestación de servicios',
    'otro' => 'Otra',
];
$soloTelefono = static fn (string $t): string => (string) preg_replace('/[^0-9+]/', '', $t);
?>
<div class="mx-auto max-w-5xl px-4 py-10">
    <h1>Directorio de servidores públicos</h1>
    <p class="mt-3 texto-suave max-w-prose">
        Servidores públicos y contratistas de la E.S.E. Hospital Rosario
        Pumarejo de López, con su cargo, dependencia y escala salarial, en
        cumplimiento de la Ley 1712 de 2014.
    </p>
    <p class="mt-2 text-sm texto-suave">
        Los datos de contacto son <strong>institucionales</strong>. Si buscas un
        especialista para una consulta, usa el
        <a href="/directorio">directorio médico</a>.
    </p>

    <?php if ($total === 0) { ?>
        <p class="mt-8 tarjeta texto-suave">
            El directorio está en actualización. Mientras tanto puedes consultar
            las hojas de vida en
            <a href="https://www.funcionpublica.gov.co/web/sigep/entidades" rel="noopener">SIGEP</a>
            o escribirnos desde <a href="/contacto">contacto</a>.
        </p>
    <?php } else { ?>
        <p class="mt-6 text-sm texto-suave"><?= (int) $total ?> registros publicados.</p>

        <?php $n = 0; foreach ($porDependencia as $dependencia => $servidores) { $n++; ?>
            <section class="mt-8" aria-labelledby="sp-<?= $n ?>">
                <h2 id="sp-<?= $n ?>" class="text-lg"><?= $e((string) $dependencia) ?></h2>

                <div class="mt-3 overflow-x-auto">
                    <table class="tabla">
                        <caption class="sr-only">
                            Servidores públicos de <?= $e((string) $dependencia) ?>
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">Nombre</th>
                                <th scope="col">Cargo</th>
                                <th scope="col">Vinculación</th>
                                <th scope="col">Contacto institucional</th>
                                <th scope="col">Escala salarial</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($servidores as $s) { ?>
                                <tr>
                                    <th scope="row">
                                        <?= $e((string) $s['apellidos']) ?> <?= $e((string) $s['nombres']) ?>
                                        <?php if (!empty($s['url_hoja_vida'])) { ?>
                                            <br><a class="text-sm" href="<?= $e((string) $s['url_hoja_vida']) ?>" rel="noopener">Hoja de vida (SIGEP)</a>
                                        <?php } ?>
                                    </th>
                                    <td>
                                        <?= $e((string) $s['cargo']) ?>
                                        <?php if (!empty($s['oficina'])) { ?>
                                            <br><span class="text-sm texto-suave"><?= $e((string) $s['oficina']) ?></span>
                                        <?php } ?>
                                    </td>
                                    <td><?= $e($vinculacion[$s['tipo_vinculacion']] ?? (string) $s['tipo_vinculacion']) ?></td>
                                    <td>
                                        <?php if (!empty($s['correo_institucional'])) { ?>
                                            <a href="mailto:<?= $e((string) $s['correo_institucional']) ?>"><?= $e((string) $s['correo_institucional']) ?></a><br>
                                        <?php } ?>
                                        <?php if (!empty($s['telefono_institucional'])) { ?>
                                            <a href="tel:<?= $e($soloTelefono((string) $s['telefono_institucional'])) ?>"><?= $e((string) $s['telefono_institucional']) ?></a>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($s['url_escala_salarial'])) { ?>
                                            <a href="<?= $e((string) $s['url_escala_salarial']) ?>" rel="noopener"><?= $e((string) ($s['escala_salarial'] ?: 'Consultar')) ?></a>
                                        <?php } else { ?>
                                            <?= $e((string) ($s['escala_salarial'] ?: '—')) ?>
                                        <?php } ?>
                                    </td>
                                </tr>
                                <?php
                                /* Resto de los literales del ITA 3.5: formación
                                   (c), experiencia (d), lugar de nacimiento (b)
                                   y datos del contrato de prestación de
                                   servicios (j).

                                   Va en una fila desplegable y no en columnas
                                   nuevas porque la tabla ya tiene cinco: nueve
                                   columnas serían ilegibles. El contenido está
                                   en el DOM aunque el detalle esté cerrado, así
                                   que se indexa y se puede verificar. */
                                $nacimiento = array_filter([
                                    (string) ($s['ciudad_nacimiento'] ?? ''),
                                    (string) ($s['departamento_nacimiento'] ?? ''),
                                    (string) ($s['pais_nacimiento'] ?? ''),
                                ], static fn (string $v): bool => trim($v) !== '');

                                $detalle = [
                                    'Lugar de nacimiento' => implode(', ', $nacimiento),
                                    'Formación académica' => (string) ($s['formacion_academica'] ?? ''),
                                    'Experiencia laboral y profesional' => (string) ($s['experiencia_laboral'] ?? ''),
                                ];

                                if (trim((string) ($s['contrato_objeto'] ?? '')) !== '') {
                                    $detalle['Objeto del contrato'] = (string) $s['contrato_objeto'];
                                }

                                if (($s['contrato_valor'] ?? null) !== null) {
                                    $detalle['Valor total de los honorarios'] =
                                        '$' . number_format((float) $s['contrato_valor'], 0, ',', '.');
                                }

                                if (trim((string) ($s['contrato_desde'] ?? '')) !== '') {
                                    $detalle['Vigencia del contrato'] = sprintf(
                                        'Del %s al %s',
                                        (string) $s['contrato_desde'],
                                        (string) ($s['contrato_hasta'] ?? 'por definir'),
                                    );
                                }

                                $detalle = array_filter($detalle, static fn (string $v): bool => trim($v) !== '');
                                ?>
                                <?php if ($detalle !== []) { ?>
                                    <tr>
                                        <td colspan="5" class="pt-0">
                                            <details class="text-sm">
                                                <summary>Más información de <?= $e((string) $s['nombres']) ?> <?= $e((string) $s['apellidos']) ?></summary>
                                                <dl class="mt-2 flex flex-col gap-1">
                                                    <?php foreach ($detalle as $etiqueta => $valor) { ?>
                                                        <div>
                                                            <dt class="inline font-semibold"><?= $e($etiqueta) ?>:</dt>
                                                            <dd class="inline"><?= $e($valor) ?></dd>
                                                        </div>
                                                    <?php } ?>
                                                </dl>
                                            </details>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php } ?>
    <?php } ?>
</div>

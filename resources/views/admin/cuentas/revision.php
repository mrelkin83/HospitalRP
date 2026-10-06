<?php
/**
 * S2/S3 — Revisión de una cuenta y cuenta aprobada.
 * Espera: $supervisor, $cuenta, $contrato, $actividades, $ss, $sugeridos,
 * $adjuntos, $periodoTexto, $tieneFirma, $mensaje, $error, $tokenCsrf.
 */
$moneda = static fn (string $v): string => '$ ' . number_format((int) explode('.', $v, 2)[0], 0, ',', '.');
$pesosEnteros = static fn (mixed $v): int => $v === null ? 0 : (int) explode('.', (string) $v, 2)[0];
$id = (int) $cuenta['id'];
$enviada = $cuenta['estado'] === 'ENVIADA';
$aprobada = in_array($cuenta['estado'], ['APROBADA', 'ENTREGADA_FINANCIERA'], true);
// Diferencias reportado vs sugerido mayores al 5 % se resaltan (§7.2).
$desviado = static function (mixed $reportado, string $campo) use ($sugeridos, $pesosEnteros): bool {
    $sugerido = (int) ($sugeridos[$campo] ?? 0);
    $valor = $pesosEnteros($reportado);
    return $sugerido > 0 && $valor > 0 && abs($valor - $sugerido) / $sugerido > 0.05;
};
?>
<h1 class="text-2xl">Cuenta <?= $e((string) ($cuenta['radicado'] ?? '')) ?> — N° <?= (int) $cuenta['consecutivo'] ?></h1>
<p class="mt-1 texto-suave">
    <?= $e((string) $contrato['contratista_nombre']) ?> · Contrato <?= $e((string) $contrato['numero']) ?> ·
    Periodo <?= $e($periodoTexto) ?> · Estado: <strong><?= $e((string) $cuenta['estado']) ?></strong>
</p>

<?php if ($mensaje !== null) { ?><div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div><?php } ?>
<?php if ($error !== null) { ?><div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div><?php } ?>

<?php /* Los documentos del contratista: se cargan UNA vez al inicio del
         contrato y sirven para toda su vigencia. Quien certifica la cuenta
         necesita poder abrirlos —la certificación bancaria contra el número
         de cuenta, el RUT contra el régimen— sin salir de esta pantalla. */ ?>
<section class="tarjeta mt-4">
    <h2 class="text-lg font-semibold texto-titular">Documentos del contratista</h2>
    <p class="mt-1 text-sm texto-suave">
        Se cargan una sola vez al inicio del contrato; no se piden cada mes.
    </p>
    <ul class="mt-2 flex flex-wrap gap-3 text-sm">
        <?php
        $documentosContratista = [
            'cedula' => ['Cédula', 'cedula_archivo'],
            'cedula-reverso' => ['Cédula (reverso)', 'cedula_reverso_archivo'],
            'rut' => ['RUT', 'rut_archivo'],
            'certificacion-bancaria' => ['Certificación bancaria', 'certificacion_bancaria_archivo'],
            'firma' => ['Firma registrada', 'firma_archivo'],
        ];

        foreach ($documentosContratista as $claveDoc => [$rotuloDoc, $columnaDoc]) {
            $hayDoc = ($contrato[$columnaDoc] ?? null) !== null;
            // El reverso solo se echa de menos si la cédula es una foto: en
            // PDF vienen las dos caras en el mismo archivo.
            $cedulaPdf = str_ends_with((string) ($contrato['cedula_archivo'] ?? ''), '.pdf');

            if ($claveDoc === 'cedula-reverso' && !$hayDoc && $cedulaPdf) {
                continue;
            }
            ?>
            <li>
                <?php if ($hayDoc) { ?>
                    <a class="enlace" target="_blank" rel="noopener"
                       href="/admin/cuentas-cobro/bandeja/<?= $id ?>/documento/<?= $e($claveDoc) ?>"><?= $e($rotuloDoc) ?></a>
                <?php } else { ?>
                    <span class="texto-suave"><?= $e($rotuloDoc) ?>: sin subir</span>
                <?php } ?>
            </li>
        <?php } ?>
    </ul>
</section>

<section class="tarjeta mt-4">
    <h2 class="text-lg font-semibold texto-titular">Avance financiero</h2>
    <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
        <div><dt class="texto-suave">Valor del contrato</dt><dd class="font-semibold"><?= $e($moneda((string) $contrato['valor_total'])) ?></dd></div>
        <div><dt class="texto-suave">Pago de esta cuenta</dt><dd class="font-semibold"><?= $e($moneda((string) $cuenta['valor'])) ?></dd></div>
        <?php if ($aprobada) { ?>
            <div><dt class="texto-suave">Pagos anteriores (congelado)</dt><dd class="font-semibold"><?= $e($moneda((string) $cuenta['pagos_anteriores'])) ?></dd></div>
            <div><dt class="texto-suave">Saldo por ejecutar (congelado)</dt><dd class="font-semibold"><?= $e($moneda((string) $cuenta['saldo_por_ejecutar'])) ?></dd></div>
        <?php } else { ?>
            <div><dt class="texto-suave">Avance completo</dt><dd><a class="enlace" href="/admin/cuentas-cobro/bandeja/<?= $id ?>/vista-previa" target="_blank" rel="noopener">Ver en la vista previa del informe</a></dd></div>
        <?php } ?>
    </dl>
</section>

<?php if ($aprobada) { ?>
    <section class="tarjeta mt-4">
        <h2 class="text-lg font-semibold texto-titular">Documentos del paquete</h2>
        <div class="mt-3 flex flex-wrap gap-3">
            <a class="boton-principal" href="/admin/cuentas-cobro/bandeja/<?= $id ?>/descargar/paquete">Paquete completo</a>
            <a class="boton-secundario" href="/admin/cuentas-cobro/bandeja/<?= $id ?>/descargar/cuenta">Cuenta de cobro</a>
            <a class="boton-secundario" href="/admin/cuentas-cobro/bandeja/<?= $id ?>/descargar/informe">Informe de actividades</a>
            <a class="boton-secundario" href="/admin/cuentas-cobro/bandeja/<?= $id ?>/descargar/supervision">Informe de supervisión</a>
        </div>
        <?php if ($cuenta['estado'] === 'APROBADA') { ?>
            <form class="mt-4" method="post" action="/admin/cuentas-cobro/bandeja/<?= $id ?>/entregada">
                <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                <button class="boton-secundario" type="submit">Marcar entregada a Financiera</button>
            </form>
        <?php } ?>
    </section>
<?php } ?>

<form method="post" action="/admin/cuentas-cobro/bandeja/<?= $id ?>/aprobar">
    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">

    <section class="tarjeta mt-4">
        <h2 class="text-lg font-semibold texto-titular">Cumplimiento de obligaciones</h2>
        <p class="mt-1 text-sm texto-suave">Las descripciones son las del contratista, tal como saldrán en ambos informes.</p>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left texto-suave">
                        <th class="p-2">N°</th><th class="p-2">Obligación</th><th class="p-2">Ejecución reportada</th>
                        <th class="p-2">Producto y evidencia</th><th class="p-2">Cumplimiento</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($actividades as $actividad) { $oid = (int) $actividad['obligacion_id']; ?>
                        <tr class="border-t borde-suave align-top">
                            <td class="p-2"><?= (int) $actividad['orden'] ?></td>
                            <td class="p-2"><?= $e((string) $actividad['texto']) ?></td>
                            <td class="p-2"><?= $e((string) ($actividad['descripcion_ejecucion'] ?? '— sin descripción —')) ?></td>
                            <td class="p-2">
                                <?php // Certificar a ciegas es lo que esta columna viene a impedir. ?>
                                <?= $e((string) ($actividad['producto_nombre'] ?? '— sin producto —')) ?>
                                <?php $suyas = $evidencias[(int) $actividad['id']] ?? []; ?>
                                <?php if ($suyas !== []) { ?>
                                    <ul class="mt-1">
                                        <?php foreach ($suyas as $evidencia) { ?>
                                            <li>
                                                <?php if ((string) $evidencia['tipo'] === 'ENLACE') { ?>
                                                    <a class="enlace" href="<?= $e((string) $evidencia['enlace']) ?>"
                                                       target="_blank" rel="noopener noreferrer">Abrir enlace</a>
                                                <?php } else { ?>
                                                    <a class="enlace" href="/admin/cuentas-cobro/bandeja/<?= $id ?>/evidencia/<?= (int) $evidencia['id'] ?>">
                                                        <?= $e((string) ($evidencia['nombre_original'] ?? 'archivo')) ?>
                                                    </a>
                                                <?php } ?>
                                            </li>
                                        <?php } ?>
                                    </ul>
                                <?php } ?>
                            </td>
                            <td class="p-2 whitespace-nowrap">
                                <?php foreach (['SI' => 'Sí', 'NO' => 'No', 'PARCIAL' => 'Parcial'] as $valor => $etiqueta) { ?>
                                    <label class="mr-2 inline-flex items-center gap-1">
                                        <input type="radio" name="cumplimiento[<?= $oid ?>]" value="<?= $valor ?>"
                                               <?= ($actividad['cumplimiento'] ?? 'SI') === $valor ? 'checked' : '' ?>
                                               <?= $enviada ? '' : 'disabled' ?>>
                                        <?= $etiqueta ?>
                                    </label>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="tarjeta mt-4">
        <h2 class="text-lg font-semibold texto-titular">Seguridad social del periodo</h2>
        <?php if ($ss === null) { ?>
            <p class="mt-2 texto-suave">El contratista aún no reporta su planilla.</p>
        <?php } else { ?>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left texto-suave">
                            <th class="p-2">Concepto</th><th class="p-2">Reportado</th><th class="p-2">Sugerido por el sistema</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-t borde-suave"><td class="p-2">Planilla N°</td><td class="p-2" colspan="2"><?= $e((string) $ss['numero_planilla']) ?> · salud <?= $e((string) $ss['mes_salud']) ?> · pensión <?= $e((string) $ss['mes_pension']) ?></td></tr>
                        <?php foreach ([
                            'ibc' => 'IBC',
                            'aporte_salud' => 'Salud',
                            'aporte_pension' => 'Pensión',
                            'aporte_arl' => 'ARL',
                        ] as $campo => $etiqueta) { $alerta = $desviado($ss[$campo], $campo); ?>
                            <tr class="border-t borde-suave <?= $alerta ? 'font-semibold' : '' ?>">
                                <td class="p-2"><?= $etiqueta ?><?= $alerta ? ' ⚠' : '' ?></td>
                                <td class="p-2"><?= $e($moneda((string) $ss[$campo])) ?><?= $alerta ? ' — difiere más del 5 %' : '' ?></td>
                                <td class="p-2"><?= isset($sugeridos[$campo]) ? $e('$ ' . number_format((int) $sugeridos[$campo], 0, ',', '.')) : '—' ?></td>
                            </tr>
                        <?php } ?>
                        <tr class="border-t borde-suave"><td class="p-2">Total aportes</td><td class="p-2" colspan="2"><?= $e($moneda((string) $ss['total_aportes'])) ?></td></tr>
                    </tbody>
                </table>
            </div>
        <?php } ?>

        <h2 class="mt-5 text-lg font-semibold texto-titular">Anexos</h2>
        <ul class="mt-2 space-y-1 text-sm">
            <?php if ($adjuntos === []) { ?><li class="texto-suave">Sin anexos.</li><?php } ?>
            <?php foreach ($adjuntos as $adjunto) { ?>
                <li>
                    <a class="enlace" href="/admin/cuentas-cobro/adjuntos/<?= (int) $adjunto['id'] ?>" target="_blank" rel="noopener">
                        <?= $e($adjunto['tipo'] . ' — ' . $adjunto['nombre_original']) ?>
                    </a>
                </li>
            <?php } ?>
        </ul>
    </section>

    <?php if ($enviada) { ?>
        <section class="tarjeta mt-4">
            <h2 class="text-lg font-semibold texto-titular">Decisión</h2>
            <p class="mt-1 text-sm texto-suave">
                <a class="enlace" href="/admin/cuentas-cobro/bandeja/<?= $id ?>/vista-previa" target="_blank" rel="noopener">
                    Ver la vista previa del informe de supervisión GJ-FR-004</a> antes de decidir.
            </p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="etiqueta" for="fecha-informe">Fecha del informe</label>
                    <input class="campo" id="fecha-informe" type="date" name="fecha_informe" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="sm:col-span-2">
                    <label class="etiqueta" for="observacion">Observación (obligatoria si marcó No o Parcial, o si devuelve)</label>
                    <textarea class="campo" id="observacion" name="observacion" rows="3"
                              placeholder="En lenguaje claro: el contratista la leerá tal cual."></textarea>
                </div>
            </div>
            <?php if (!$tieneFirma) { ?>
                <p class="alerta alerta-error mt-3" role="alert">Registre su firma en la bandeja antes de aprobar.</p>
            <?php } ?>
            <div class="mt-4 flex flex-wrap gap-3">
                <button class="boton-principal" type="submit" <?= $tieneFirma ? '' : 'disabled' ?>
                        onclick="return confirm('¿Aprobar y firmar? Se genera el informe de supervisión y el paquete definitivo.');">
                    Aprobar y firmar
                </button>
                <button class="boton-secundario" type="submit"
                        formaction="/admin/cuentas-cobro/bandeja/<?= $id ?>/devolver"
                        onclick="return confirm('¿Devolver al contratista con la observación escrita?');">
                    Devolver al contratista
                </button>
            </div>
        </section>
    <?php } ?>
</form>

<p class="mt-4"><a class="enlace" href="/admin/cuentas-cobro/bandeja">Volver a la bandeja</a></p>

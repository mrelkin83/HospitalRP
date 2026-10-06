<?php
/**
 * R2 — Revisión previa de una cuenta (el paso antes del supervisor).
 * Espera: $revisor, $cuenta, $contrato, $actividades, $evidencias, $ss, $adjuntos,
 * $periodoTexto, $tieneFirma, $mensaje, $error, $tokenCsrf.
 */
$moneda = static fn (string $v): string => '$ ' . number_format((int) explode('.', $v, 2)[0], 0, ',', '.');
$id = (int) $cuenta['id'];
$enviada = $cuenta['estado'] === 'ENVIADA';
?>
<h1 class="text-2xl">Cuenta <?= $e((string) ($cuenta['radicado'] ?? '')) ?> — N° <?= (int) $cuenta['consecutivo'] ?></h1>
<p class="mt-1 texto-suave">
    <?= $e((string) $contrato['contratista_nombre']) ?> · Contrato <?= $e((string) $contrato['numero']) ?> ·
    Periodo <?= $e($periodoTexto) ?> · Estado: <strong><?= $e((string) $cuenta['estado']) ?></strong>
</p>

<?php if ($mensaje !== null) { ?><div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div><?php } ?>
<?php if ($error !== null) { ?><div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div><?php } ?>

<?php if ($enviada && !$tieneFirma) { ?>
    <div class="alerta alerta-error mt-4" role="alert">
        <strong>Falta su firma.</strong> Regístrela en la bandeja de revisión antes de marcar esta cuenta como revisada.
    </div>
<?php } ?>

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
                       href="/admin/cuentas-cobro/revision/<?= $id ?>/documento/<?= $e($claveDoc) ?>"><?= $e($rotuloDoc) ?></a>
                <?php } else { ?>
                    <span class="texto-suave"><?= $e($rotuloDoc) ?>: sin subir</span>
                <?php } ?>
            </li>
        <?php } ?>
    </ul>
</section>

<section class="tarjeta mt-4">
    <h2 class="text-lg font-semibold texto-titular">Documentos firmados por el contratista</h2>
    <div class="mt-3 flex flex-wrap gap-3">
        <a class="boton-secundario" href="/admin/cuentas-cobro/revision/<?= $id ?>/descargar/cuenta" target="_blank" rel="noopener">Cuenta de cobro</a>
        <a class="boton-secundario" href="/admin/cuentas-cobro/revision/<?= $id ?>/descargar/informe" target="_blank" rel="noopener">Informe de actividades</a>
    </div>
    <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
        <div><dt class="texto-suave">Valor del contrato</dt><dd class="font-semibold"><?= $e($moneda((string) $contrato['valor_total'])) ?></dd></div>
        <div><dt class="texto-suave">Pago de esta cuenta</dt><dd class="font-semibold"><?= $e($moneda((string) $cuenta['valor'])) ?></dd></div>
    </dl>
</section>

<section class="tarjeta mt-4">
    <h2 class="text-lg font-semibold texto-titular">Actividades reportadas</h2>
    <div class="mt-3 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left texto-suave">
                    <th class="p-2">N°</th><th class="p-2">Obligación / actividad</th><th class="p-2">Ejecución reportada</th>
                    <th class="p-2">Producto y evidencia</th>
                </tr>
            </thead>
            <tbody>
                <?php $n = 0; foreach ($actividades as $actividad) { $n++; ?>
                    <tr class="border-t borde-suave align-top">
                        <td class="p-2"><?= (int) ($actividad['orden'] ?? $n) ?></td>
                        <td class="p-2"><?= $e((string) $actividad['texto']) ?></td>
                        <td class="p-2"><?= $e((string) ($actividad['descripcion_ejecucion'] ?? '— sin descripción —')) ?></td>
                        <td class="p-2">
                            <?php // El revisor es la primera línea: certificar sin ver el soporte es lo que esto evita. ?>
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
                                                <a class="enlace" href="/admin/cuentas-cobro/revision/evidencia/<?= (int) $evidencia['id'] ?>"
                                                   target="_blank" rel="noopener">
                                                    <?= $e((string) ($evidencia['nombre_original'] ?? 'archivo')) ?>
                                                </a>
                                            <?php } ?>
                                        </li>
                                    <?php } ?>
                                </ul>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<section class="tarjeta mt-4">
    <h2 class="text-lg font-semibold texto-titular">Seguridad social y anexos</h2>
    <?php if ($ss !== null) { ?>
        <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
            <div><dt class="texto-suave">Planilla N°</dt><dd class="font-semibold"><?= $e((string) $ss['numero_planilla']) ?></dd></div>
            <div><dt class="texto-suave">IBC</dt><dd class="font-semibold"><?= $e($moneda((string) $ss['ibc'])) ?></dd></div>
            <div><dt class="texto-suave">Salud / Pensión / ARL</dt>
                <dd class="font-semibold"><?= $e($moneda((string) $ss['aporte_salud'])) ?> / <?= $e($moneda((string) $ss['aporte_pension'])) ?> / <?= $e($moneda((string) $ss['aporte_arl'])) ?></dd></div>
        </dl>
    <?php } else { ?>
        <p class="mt-2 texto-suave">Sin datos de planilla.</p>
    <?php } ?>
    <?php if ($adjuntos !== []) { ?>
        <ul class="mt-3 list-inside list-disc text-sm">
            <?php foreach ($adjuntos as $adjunto) { ?>
                <li>
                    <a class="enlace" href="/admin/cuentas-cobro/revision/adjunto/<?= (int) $adjunto['id'] ?>" target="_blank" rel="noopener">
                        <?= $e((string) $adjunto['tipo']) ?> — <?= $e((string) $adjunto['nombre_original']) ?>
                    </a>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>
</section>

<?php if ($enviada) { ?>
    <section class="tarjeta mt-4">
        <h2 class="text-lg font-semibold texto-titular">Su decisión</h2>
        <form class="mt-3" method="post" action="/admin/cuentas-cobro/revision/<?= $id ?>/revisar">
            <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
            <label class="etiqueta" for="obs-revisar">Observación (opcional)</label>
            <textarea class="campo" id="obs-revisar" name="observacion" rows="2"
                      placeholder="Queda en el historial de la cuenta."></textarea>
            <p class="mt-3">
                <button class="boton-principal" type="submit" <?= $tieneFirma ? '' : 'disabled' ?>>
                    La información cumple: firmar la revisión y pasarla al supervisor
                </button>
            </p>
        </form>
        <form class="mt-4 border-t borde-suave pt-4" method="post" action="/admin/cuentas-cobro/revision/<?= $id ?>/devolver">
            <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
            <label class="etiqueta" for="obs-devolver">Motivo de la devolución (el contratista lo leerá tal cual)</label>
            <textarea class="campo" id="obs-devolver" name="observacion" rows="3" required minlength="15"
                      placeholder="Ejemplo: La planilla PILA adjunta es del mes anterior; adjunte la del periodo cobrado."></textarea>
            <p class="mt-3"><button class="boton-secundario" type="submit">Devolver al contratista</button></p>
        </form>
    </section>
<?php } ?>

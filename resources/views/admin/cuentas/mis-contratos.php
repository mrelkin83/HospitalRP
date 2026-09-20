<?php
/**
 * S4 — Los contratos que superviso, con el acta de inicio pendiente a la
 * vista: sin acta no hay cronograma y sin cronograma no hay cuentas.
 * Espera: $supervisor, $contratos, $mensaje, $error, $tokenCsrf.
 */
$moneda = static fn (string $v): string => '$ ' . number_format((int) explode('.', $v, 2)[0], 0, ',', '.');
?>
<h1 class="text-2xl">Mis contratos</h1>
<p class="mt-2 texto-suave">
    Los contratos a su cargo. Cuando falte el acta de inicio, regístrela aquí:
    con ella el sistema calcula las fechas de cada cuota.
</p>

<?php if ($mensaje !== null) { ?><div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div><?php } ?>
<?php if ($error !== null) { ?><div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div><?php } ?>

<div class="tarjeta mt-4 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left texto-suave">
                <th class="p-2">Contrato</th><th class="p-2">Contratista</th><th class="p-2">Valor</th>
                <th class="p-2">Acta de inicio</th><th class="p-2">Terminación</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($contratos === []) { ?>
                <tr><td class="p-4 texto-suave" colspan="5">No tiene contratos activos a su cargo.</td></tr>
            <?php } ?>
            <?php foreach ($contratos as $contrato) { $kid = (int) $contrato['id']; ?>
                <tr class="border-t borde-suave">
                    <td class="p-2 font-semibold"><?= $e((string) $contrato['numero']) ?></td>
                    <td class="p-2"><?= $e($contrato['contratista_nombre'] . ' · ' . $contrato['numero_documento']) ?></td>
                    <td class="p-2 whitespace-nowrap"><?= $e($moneda((string) $contrato['valor_total'])) ?></td>
                    <td class="p-2">
                        <?php if ($contrato['fecha_acta_inicio'] !== null) { ?>
                            <?= $e((string) $contrato['fecha_acta_inicio']) ?>
                        <?php } else { ?>
                            <form class="flex flex-wrap items-end gap-2" method="post"
                                  action="/admin/cuentas-cobro/mis-contratos/<?= $kid ?>/acta">
                                <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                                <div>
                                    <label class="etiqueta" for="acta-<?= $kid ?>">Fecha del acta</label>
                                    <input class="campo" id="acta-<?= $kid ?>" type="date" name="fecha_acta_inicio" required>
                                </div>
                                <button class="boton-principal" type="submit"
                                        onclick="return confirm('Se calcularán los periodos de todas las cuotas. ¿Registrar el acta?');">
                                    Registrar acta
                                </button>
                            </form>
                        <?php } ?>
                    </td>
                    <td class="p-2 whitespace-nowrap"><?= $e((string) ($contrato['fecha_terminacion'] ?? '—')) ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<p class="mt-4"><a class="enlace" href="/admin/cuentas-cobro/bandeja">Volver a la bandeja</a></p>

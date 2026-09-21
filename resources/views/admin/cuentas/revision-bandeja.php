<?php
/**
 * R1 — Bandeja de revisión (previa al supervisor). Cola compartida: los
 * cuatro revisores ven las mismas cuentas y cualquiera toma cualquiera.
 * Espera: $revisor (?fila), $pestana, $cuentas, $mensaje, $error, $tokenCsrf.
 */
$moneda = static fn (string $v): string => '$ ' . number_format((int) explode('.', $v, 2)[0], 0, ',', '.');
$pestanas = [
    'pendientes' => 'Por revisar',
    'revisadas' => 'Revisadas',
    'devueltas' => 'Devueltas',
    'todas' => 'Todas',
];
?>
<h1 class="text-2xl">Bandeja de revisión</h1>
<p class="mt-2 texto-suave">
    Las cuentas de cobro enviadas por los contratistas. Verifique la
    información y, si cumple, fírmela como revisada: pasará al supervisor.
    Si no cumple, devuélvala con el motivo en lenguaje claro.
</p>

<?php if ($mensaje !== null) { ?><div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div><?php } ?>
<?php if ($error !== null) { ?><div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div><?php } ?>

<?php if ($revisor === null) { ?>
    <div class="alerta alerta-aviso mt-4" role="alert">
        Su usuario no está vinculado como revisor de cuentas (o está inactivo).
        Pida al administrador que lo vincule en «Cuentas de cobro → Revisores».
    </div>
    <?php return; ?>
<?php } ?>

<?php if (($revisor['firma_archivo'] ?? null) === null) { ?>
    <div class="alerta alerta-error mt-4" role="alert">
        <p><strong>Falta su firma.</strong> Sin ella no puede marcar cuentas como revisadas: la revisión sale firmada.</p>
        <form class="mt-2 flex flex-wrap items-end gap-3" method="post" action="/admin/cuentas-cobro/revision/firma"
              enctype="multipart/form-data">
            <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
            <div>
                <label class="etiqueta" for="firma-rev">Imagen de su firma (JPG o PNG)</label>
                <input class="campo" id="firma-rev" type="file" name="firma" accept=".jpg,.jpeg,.png" required>
            </div>
            <button class="boton-principal" type="submit">Guardar firma</button>
        </form>
    </div>
<?php } ?>

<nav class="mt-4 flex flex-wrap gap-2" aria-label="Estados de las cuentas">
    <?php foreach ($pestanas as $clave => $etiqueta) { ?>
        <a class="<?= $pestana === $clave ? 'boton-principal' : 'boton-secundario' ?>"
           href="/admin/cuentas-cobro/revision?pestana=<?= $e($clave) ?>"
           <?= $pestana === $clave ? 'aria-current="page"' : '' ?>><?= $e($etiqueta) ?></a>
    <?php } ?>
</nav>

<div class="tarjeta mt-4 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left texto-suave">
                <th class="p-2">Radicado</th><th class="p-2">Contratista</th><th class="p-2">Contrato</th>
                <th class="p-2">N°</th><th class="p-2">Periodo</th><th class="p-2">Valor</th>
                <th class="p-2">Estado</th><th class="p-2">Días en espera</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($cuentas === []) { ?>
                <tr><td class="p-4 texto-suave" colspan="8">No hay cuentas en esta pestaña.</td></tr>
            <?php } ?>
            <?php foreach ($cuentas as $cuenta) { ?>
                <tr class="border-t borde-suave">
                    <td class="p-2">
                        <a class="font-semibold enlace" href="/admin/cuentas-cobro/revision/<?= (int) $cuenta['id'] ?>">
                            <?= $e((string) ($cuenta['radicado'] ?? 'Borrador')) ?>
                        </a>
                    </td>
                    <td class="p-2"><?= $e((string) $cuenta['contratista_nombre']) ?></td>
                    <td class="p-2"><?= $e((string) $cuenta['numero_contrato']) ?></td>
                    <td class="p-2"><?= (int) $cuenta['consecutivo'] ?></td>
                    <td class="p-2 whitespace-nowrap"><?= $e($cuenta['periodo_desde'] . ' a ' . $cuenta['periodo_hasta']) ?></td>
                    <td class="p-2 whitespace-nowrap"><?= $e($moneda((string) $cuenta['valor'])) ?></td>
                    <td class="p-2"><?= $e((string) $cuenta['estado']) ?></td>
                    <td class="p-2"><?= $cuenta['estado'] === 'ENVIADA' && $cuenta['dias_en_espera'] !== null ? (int) $cuenta['dias_en_espera'] : '—' ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

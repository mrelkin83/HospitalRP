<?php
/**
 * A2 — Cargar minuta: subir el PDF y ver las extracciones recientes.
 * Espera: $extracciones, $iaDisponible, $ocrDisponible, $mensaje, $error,
 * $tokenCsrf.
 */
$estados = [
    'PENDIENTE' => 'Pendiente', 'PROCESANDO' => 'Procesando…', 'LISTA' => 'Lista para revisar',
    'ERROR' => 'Con error', 'APLICADA' => 'Aplicada a contrato',
];
?>
<h1 class="text-2xl">Cargar minuta</h1>
<p class="mt-2 texto-suave">
    Suba el PDF escaneado de la Oficina Jurídica: el sistema lo convierte en
    imágenes, la IA prellena el formulario del contrato y usted revisa. Nada
    se activa sin su revisión.
</p>

<?php if ($mensaje !== null) { ?><div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div><?php } ?>
<?php if ($error !== null) { ?><div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div><?php } ?>

<?php if (!$iaDisponible) { ?>
    <div class="alerta alerta-error mt-4" role="status">
        El asistente de IA no está activo (Sistema → Asistente de IA). Puede
        cargar minutas igual: el formulario se llena a mano con las imágenes al
        lado<?= $ocrDisponible ? ', con ayuda del OCR' : '' ?>.
    </div>
<?php } ?>

<form class="tarjeta mt-4 flex flex-wrap items-end gap-3" method="post"
      action="/admin/cuentas-cobro/minutas" enctype="multipart/form-data">
    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
    <div class="grow">
        <label class="etiqueta" for="minuta">PDF de la minuta (máx. 10 MB)</label>
        <input class="campo" id="minuta" type="file" name="minuta" accept=".pdf" required>
    </div>
    <button class="boton-principal" type="submit">Subir y extraer</button>
</form>
<p class="texto-suave mt-1 text-sm">La extracción tarda alrededor de un minuto: no cierre la pestaña.</p>

<div class="tarjeta mt-4 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left texto-suave">
                <th class="p-2">#</th><th class="p-2">Subida</th><th class="p-2">Por</th>
                <th class="p-2">Estado</th><th class="p-2">Motor</th><th class="p-2">Contrato</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($extracciones === []) { ?>
                <tr><td class="p-4 texto-suave" colspan="6">Aún no se ha cargado ninguna minuta.</td></tr>
            <?php } ?>
            <?php foreach ($extracciones as $extraccion) { ?>
                <tr class="border-t borde-suave">
                    <td class="p-2">
                        <a class="font-semibold enlace" href="/admin/cuentas-cobro/minutas/<?= (int) $extraccion['id'] ?>">
                            Extracción #<?= (int) $extraccion['id'] ?>
                        </a>
                    </td>
                    <td class="p-2 whitespace-nowrap"><?= $e(substr((string) $extraccion['creado_en'], 0, 16)) ?></td>
                    <td class="p-2"><?= $e((string) ($extraccion['subida_por'] ?? '')) ?></td>
                    <td class="p-2"><?= $e($estados[(string) $extraccion['estado']] ?? (string) $extraccion['estado']) ?></td>
                    <td class="p-2"><?= $e(trim(($extraccion['proveedor'] ?? '') . ' ' . ($extraccion['modelo'] ?? ''))) ?></td>
                    <td class="p-2"><?= $e((string) ($extraccion['numero_contrato'] ?? '—')) ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

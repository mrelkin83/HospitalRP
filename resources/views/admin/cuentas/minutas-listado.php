<?php
/**
 * A2 — Cargar minuta: subir el lote y trabajar la bandeja de extracciones.
 * Espera: $extracciones, $conteos, $enCola, $estadoFiltro, $maximoPorLote,
 * $usuarioId, $iaDisponible, $ocrDisponible, $mensaje, $error, $tokenCsrf.
 */
$estados = [
    'PENDIENTE' => 'En cola', 'PROCESANDO' => 'Procesando…', 'LISTA' => 'Lista para revisar',
    'ERROR' => 'Con error', 'APLICADA' => 'Aplicada a contrato',
];
?>
<h1 class="text-2xl">Cargar minuta</h1>
<p class="mt-2 texto-suave">
    Suba los PDF escaneados de la Oficina Jurídica —hasta <?= (int) $maximoPorLote ?>
    por tanda—: quedan en cola, el sistema los convierte en imágenes y prellena
    el formulario del contrato. Nada se activa sin su revisión.
</p>

<?php if ($mensaje !== null) { ?><div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div><?php } ?>
<?php if ($error !== null) { ?><div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div><?php } ?>

<?php if (!$iaDisponible) { ?>
    <div class="alerta alerta-error mt-4" role="status">
        El asistente de IA no está activo (Sistema → Asistente de IA). Puede
        cargar minutas igual: si el PDF trae su propio texto se extrae sin IA, y
        si no, el formulario se llena a mano con las imágenes al lado<?= $ocrDisponible ? ', con ayuda del OCR' : '' ?>.
    </div>
<?php } ?>

<form class="tarjeta mt-4 flex flex-wrap items-end gap-3" method="post"
      action="/admin/cuentas-cobro/minutas" enctype="multipart/form-data">
    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
    <div class="grow">
        <label class="etiqueta" for="minuta">PDF de las minutas (máx. 10 MB cada uno)</label>
        <input class="campo" id="minuta" type="file" name="minuta[]" accept=".pdf" multiple required>
    </div>
    <button class="boton-principal" type="submit">Subir y encolar</button>
</form>
<p class="texto-suave mt-1 text-sm">
    La extracción corre por detrás: puede cerrar esta pantalla y volver.
</p>

<?php if ($enCola > 0) { ?>
    <div class="alerta mt-4" role="status">
        <?= (int) $enCola ?> minuta(s) en cola. Esta pantalla se actualiza sola cada 15 segundos.
    </div>
    <?php // El refresco solo mientras hay trabajo: recargar una pantalla quieta es ruido. ?>
    <meta http-equiv="refresh" content="15">
<?php } ?>

<nav class="mt-4 flex flex-wrap gap-2" aria-label="Filtrar por estado">
    <?php $enlaceBase = '/admin/cuentas-cobro/minutas'; ?>
    <a class="<?= $estadoFiltro === null ? 'boton-secundario' : 'enlace' ?>" href="<?= $enlaceBase ?>">
        Todas (<?= array_sum($conteos) ?>)
    </a>
    <?php foreach ($estados as $clave => $rotulo) { ?>
        <?php if (($conteos[$clave] ?? 0) > 0) { ?>
            <a class="<?= $estadoFiltro === $clave ? 'boton-secundario' : 'enlace' ?>"
               href="<?= $enlaceBase ?>?estado=<?= $e($clave) ?>">
                <?= $e($rotulo) ?> (<?= (int) $conteos[$clave] ?>)
            </a>
        <?php } ?>
    <?php } ?>
</nav>

<div class="tarjeta mt-4 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left texto-suave">
                <th class="p-2">#</th><th class="p-2">Subida</th><th class="p-2">Estado</th>
                <th class="p-2">Motor</th><th class="p-2">Quién la revisa</th><th class="p-2">Contrato</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($extracciones === []) { ?>
                <tr><td class="p-4 texto-suave" colspan="6">No hay minutas en este estado.</td></tr>
            <?php } ?>
            <?php foreach ($extracciones as $extraccion) { ?>
                <?php $mia = (int) ($extraccion['asignado_a'] ?? 0) === (int) $usuarioId; ?>
                <tr class="border-t borde-suave">
                    <td class="p-2">
                        <a class="font-semibold enlace" href="/admin/cuentas-cobro/minutas/<?= (int) $extraccion['id'] ?>">
                            Extracción #<?= (int) $extraccion['id'] ?>
                        </a>
                        <?php if ((int) $extraccion['intentos'] > 1) { ?>
                            <span class="texto-suave">· <?= (int) $extraccion['intentos'] ?> intentos</span>
                        <?php } ?>
                    </td>
                    <td class="p-2 whitespace-nowrap"><?= $e(substr((string) $extraccion['creado_en'], 0, 16)) ?></td>
                    <td class="p-2"><?= $e($estados[(string) $extraccion['estado']] ?? (string) $extraccion['estado']) ?></td>
                    <td class="p-2"><?= $e(trim(((string) ($extraccion['proveedor'] ?? '')) . ' ' . ((string) ($extraccion['modelo'] ?? '')))) ?></td>
                    <td class="p-2">
                        <?php if ($extraccion['asignado_a'] === null) { ?>
                            <span class="texto-suave">libre</span>
                        <?php } elseif ($mia) { ?>
                            <strong>usted</strong>
                        <?php } else { ?>
                            <?= $e((string) ($extraccion['asignada_a_nombre'] ?? '')) ?>
                        <?php } ?>
                    </td>
                    <td class="p-2"><?= $e((string) ($extraccion['numero_contrato'] ?? '—')) ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

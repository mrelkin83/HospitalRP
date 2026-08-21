<?php
/**
 * Mensaje de página completa dentro del panel.
 * Espera: $tipo ('error'|'exito'|'aviso'), $mensaje (string), $volverA (string).
 */
$clase = match ($tipo) {
    'exito' => 'alerta-exito',
    'aviso' => 'alerta-aviso',
    default => 'alerta-error',
};
?>
<div class="max-w-xl">
    <div class="alerta <?= $clase ?>" role="alert"><?= $e($mensaje) ?></div>
    <p class="mt-4"><a class="boton boton-secundario" href="<?= $e($volverA) ?>">Volver</a></p>
</div>

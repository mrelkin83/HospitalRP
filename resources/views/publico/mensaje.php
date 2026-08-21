<?php
/**
 * Mensaje de página completa en el sitio público (404, «en construcción»…).
 * Espera: $encabezado (string), $mensaje (string).
 */
?>
<div class="mx-auto max-w-6xl px-4 py-16 text-center">
    <h1><?= $e($encabezado) ?></h1>
    <p class="mt-4 texto-suave max-w-prose mx-auto"><?= $e($mensaje) ?></p>
    <p class="mt-8"><a class="boton boton-primario" href="/">Volver al inicio</a></p>
</div>

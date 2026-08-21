<?php
/**
 * Radicación exitosa. Espera: $tipo (TipoTramite), $radicado (string), $venceEn (string).
 */

use App\Core\Support\Fechas;
?>
<div class="mx-auto max-w-6xl px-4 py-16">
    <div class="mx-auto max-w-xl text-center">
        <span class="insignia insignia-exito text-base">✓ Solicitud radicada</span>
        <h1 class="mt-4">Tu número de radicado</h1>
        <p class="mt-6 tarjeta text-2xl font-bold texto-titular tracking-wide"><?= $e($radicado) ?></p>
        <p class="mt-4 texto-suave">
            Guárdalo: con él y tu número de documento puedes consultar el estado
            en cualquier momento. Si dejaste un correo, también te lo enviamos.
        </p>
        <?php if ($venceEn !== '') { ?>
            <p class="mt-2 texto-suave">Fecha límite de respuesta: <strong><?= $e(Fechas::larga($venceEn)) ?></strong>.</p>
        <?php } ?>
        <p class="mt-8 flex flex-wrap justify-center gap-3">
            <a class="boton boton-primario" href="/atencion-ciudadania/pqrsdf/consultar">Consultar mi trámite</a>
            <a class="boton boton-secundario" href="/">Volver al inicio</a>
        </p>
    </div>
</div>

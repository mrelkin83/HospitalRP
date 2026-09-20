<?php
/**
 * P6 — Enviada: radicado y botones grandes de descarga y reenvío.
 * Espera: $contratista, $contrato, $cuenta, $enlaceWhatsapp, $mensaje,
 * $error, $tokenCsrf.
 */
ob_start(); ?>

<?php if ($mensaje !== null) { ?><div class="alerta-exito" role="status"><?= $e($mensaje) ?></div><?php } ?>
<?php if ($error !== null) { ?><div class="alerta-error" role="alert"><?= $e($error) ?></div><?php } ?>

<div class="alerta-exito" role="status">
    <p><strong>Su cuenta N° <?= (int) $cuenta['consecutivo'] ?> fue enviada a
    <?= $e((string) ($contrato['supervisor_nombre'] ?? 'su supervisor')) ?>.</strong></p>
    <p>Radicado: <strong><?= $e((string) $cuenta['radicado']) ?></strong>. Guárdelo.</p>
</div>

<a class="boton" href="/cuentas/pdf/<?= (int) $cuenta['id'] ?>/cuenta">Descargar la cuenta de cobro</a>
<a class="boton-secundario" href="/cuentas/pdf/<?= (int) $cuenta['id'] ?>/informe">Descargar el informe de actividades</a>

<form method="post" action="/cuentas/enviada/<?= (int) $cuenta['id'] ?>/correo">
    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
    <button class="boton-secundario" type="submit">Enviar a mi correo</button>
</form>

<a class="boton-secundario" href="<?= $e($enlaceWhatsapp) ?>" target="_blank" rel="noopener">Enviar por WhatsApp</a>

<a class="boton-secundario" href="/cuentas/inicio">Volver al inicio</a>

<p class="ayuda">El supervisor revisará su cuenta. Si algo necesita corrección,
se lo haremos saber aquí mismo y por su correo.</p>

<?php $contenido = (string) ob_get_clean();
echo $vistas->render('publico/cuentas/_marco', [
    'titulo' => 'Cuenta enviada',
    'contenido' => $contenido,
    'conSesion' => true,
    'tokenCsrf' => $tokenCsrf,
]);

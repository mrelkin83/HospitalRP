<?php
/**
 * P0b — Escribir el código de 6 dígitos que llegó al correo.
 * Espera: $mensaje (?string), $error (?string), $tokenCsrf.
 */
ob_start(); ?>

<?php if ($mensaje !== null) { ?>
    <div class="alerta-exito" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta-error" role="alert"><?= $e($error) ?></div>
<?php } ?>

<form class="tarjeta" method="post" action="/cuentas/codigo">
    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">

    <label for="codigo">Escriba el código</label>
    <input id="codigo" name="codigo" type="text" inputmode="numeric" autocomplete="one-time-code"
           required pattern="\d{6}" maxlength="6" aria-describedby="ayuda-codigo">
    <span class="ayuda" id="ayuda-codigo">Son 6 números. El código vence en 10 minutos.</span>

    <button class="boton" type="submit">Entrar</button>
</form>

<p class="ayuda">¿No le llegó? Espere un minuto y
    <a class="enlace-suave" href="/cuentas/ingreso">pida un código nuevo</a>.</p>

<?php $contenido = (string) ob_get_clean();
echo $vistas->render('publico/cuentas/_marco', [
    'titulo' => 'Revise su correo',
    'contenido' => $contenido,
    'tokenCsrf' => $tokenCsrf,
]);

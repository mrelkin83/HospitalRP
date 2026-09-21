<?php
/**
 * P0 — Ingreso con contraseña. Un selector deja entrar por cédula O por
 * número de contrato; el OTP de seis dígitos se retiró.
 * Espera: $error, $mensaje, $tokenCsrf.
 */
ob_start(); ?>

<?php if ($error !== null) { ?>
    <div class="alerta-error" role="alert"><?= $e($error) ?></div>
<?php } ?>
<?php if ($mensaje !== null) { ?>
    <div class="alerta-exito" role="status"><?= $e($mensaje) ?></div>
<?php } ?>

<form class="tarjeta" method="post" action="/cuentas/ingreso">
    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">

    <fieldset>
        <legend>¿Con qué dato quiere entrar?</legend>
        <label class="opcion">
            <input type="radio" name="modo" value="cedula" checked>
            Número de cédula
        </label>
        <label class="opcion">
            <input type="radio" name="modo" value="contrato">
            Número de contrato
        </label>
    </fieldset>

    <label for="identificador">Cédula o número de contrato</label>
    <input id="identificador" name="identificador" type="text" autocomplete="username"
           required aria-describedby="ayuda-identificador">
    <span class="ayuda" id="ayuda-identificador">
        La cédula va solo con números. El número de contrato está en la primera
        página de su copia; ejemplo: 1407-2026.
    </span>

    <label for="contrasena">Contraseña</label>
    <input id="contrasena" name="contrasena" type="password" autocomplete="current-password"
           required aria-describedby="ayuda-contrasena">
    <span class="ayuda" id="ayuda-contrasena">
        La que usted creó. Si la olvidó, pídala restablecer en la oficina de
        Contratación del hospital.
    </span>

    <button class="boton" type="submit">Entrar</button>
</form>

<p class="ayuda">
    ¿Primera vez aquí? <a href="/cuentas/crear-contrasena">Cree su contraseña</a>
    con la fecha de suscripción de su contrato.
    Este portal es solo para contratistas de prestación de servicios del hospital.
</p>

<?php $contenido = (string) ob_get_clean();
echo $vistas->render('publico/cuentas/_marco', [
    'titulo' => 'Portal de cuentas de cobro',
    'contenido' => $contenido,
    'tokenCsrf' => $tokenCsrf,
]);

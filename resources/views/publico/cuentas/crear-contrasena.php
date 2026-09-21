<?php
/**
 * P0b — Crear la contraseña (primer ingreso o tras un restablecimiento).
 * La identidad se confirma con la fecha de suscripción del contrato.
 * Espera: $error, $mensaje, $tokenCsrf.
 */
ob_start(); ?>

<?php if ($error !== null) { ?>
    <div class="alerta-error" role="alert"><?= $e($error) ?></div>
<?php } ?>
<?php if ($mensaje !== null) { ?>
    <div class="alerta-exito" role="status"><?= $e($mensaje) ?></div>
<?php } ?>

<form class="tarjeta" method="post" action="/cuentas/crear-contrasena">
    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">

    <fieldset>
        <legend>¿Con qué dato se identifica?</legend>
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
    <input id="identificador" name="identificador" type="text" autocomplete="off" required>

    <label for="fecha-suscripcion">Fecha de suscripción del contrato</label>
    <input id="fecha-suscripcion" name="fecha_suscripcion" type="date" required
           aria-describedby="ayuda-fecha">
    <span class="ayuda" id="ayuda-fecha">
        Aparece junto a las firmas, en su copia del contrato. Con ella
        confirmamos que es usted.
    </span>

    <label for="contrasena">Contraseña nueva</label>
    <input id="contrasena" name="contrasena" type="password" autocomplete="new-password"
           required minlength="8" aria-describedby="ayuda-contrasena">
    <span class="ayuda" id="ayuda-contrasena">
        Mínimo 8 caracteres. Puede ser una frase fácil de recordar.
    </span>

    <label for="contrasena-confirmacion">Repita la contraseña</label>
    <input id="contrasena-confirmacion" name="contrasena_confirmacion" type="password"
           autocomplete="new-password" required minlength="8">

    <button class="boton" type="submit">Crear contraseña y entrar</button>
</form>

<p class="ayuda">
    ¿Ya tiene contraseña? <a href="/cuentas/ingreso">Vuelva al ingreso</a>.
</p>

<?php $contenido = (string) ob_get_clean();
echo $vistas->render('publico/cuentas/_marco', [
    'titulo' => 'Crear contraseña — Portal de cuentas de cobro',
    'contenido' => $contenido,
    'tokenCsrf' => $tokenCsrf,
]);

<?php
/**
 * P0 — Ingreso: cédula + número de contrato. Si es el primer ingreso y no
 * hay a dónde enviar el código, se pide además la fecha de suscripción.
 * Espera: $error, $primeraVez, $tokenCsrf.
 */
ob_start(); ?>

<?php if ($error !== null) { ?>
    <div class="alerta-error" role="alert"><?= $e($error) ?></div>
<?php } ?>

<form class="tarjeta" method="post" action="/cuentas/ingreso">
    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">

    <label for="cedula">Número de cédula</label>
    <input id="cedula" name="cedula" type="text" inputmode="numeric" autocomplete="off"
           required pattern="[0-9.\s]{5,20}" aria-describedby="ayuda-cedula">
    <span class="ayuda" id="ayuda-cedula">Solo los números, sin puntos.</span>

    <label for="contrato">Número de contrato</label>
    <input id="contrato" name="contrato" type="text" autocomplete="off" required
           aria-describedby="ayuda-contrato">
    <span class="ayuda" id="ayuda-contrato">Está en la primera página de su contrato. Ejemplo: 1407-2026.</span>

    <?php if ($primeraVez) { ?>
        <label for="fecha-suscripcion">Fecha de suscripción del contrato</label>
        <input id="fecha-suscripcion" name="fecha_suscripcion" type="date" required
               aria-describedby="ayuda-fecha">
        <span class="ayuda" id="ayuda-fecha">Como es su primer ingreso, confirme la fecha que aparece en su contrato.</span>
    <?php } ?>

    <button class="boton" type="submit">Continuar</button>
</form>

<p class="ayuda">
    Le enviaremos un código de 6 números para confirmar que es usted.
    Este portal es solo para contratistas de prestación de servicios del hospital.
</p>

<?php $contenido = (string) ob_get_clean();
echo $vistas->render('publico/cuentas/_marco', [
    'titulo' => 'Portal de cuentas de cobro',
    'contenido' => $contenido,
    'tokenCsrf' => $tokenCsrf,
]);

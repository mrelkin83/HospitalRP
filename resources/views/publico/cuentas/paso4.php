<?php
/**
 * P5 — Revisar y enviar: vista previa de los dos documentos y la firma.
 * Espera: $contratista, $contrato, $cuenta, $periodoTexto, $tieneFirma,
 * $error, $tokenCsrf.
 */
$moneda = static fn (string $v): string => '$ ' . number_format((int) explode('.', $v, 2)[0], 0, ',', '.');
ob_start(); ?>

<p>Cuenta de cobro N° <?= (int) $cuenta['consecutivo'] ?> — Periodo <?= $e($periodoTexto) ?> —
   Valor <strong><?= $e($moneda((string) $cuenta['valor'])) ?></strong>.</p>

<?php if ($error !== null) { ?><div class="alerta-error" role="alert"><?= $e($error) ?></div><?php } ?>

<section class="tarjeta">
    <h2>Así quedará su cuenta de cobro</h2>
    <iframe class="visor-pdf" src="/cuentas/nueva/vista-previa/cuenta" title="Vista previa del documento"
            aria-label="Vista previa de la cuenta de cobro">
        <p>Su navegador no muestra el PDF aquí.
           <a class="enlace-suave" href="/cuentas/nueva/vista-previa/cuenta" target="_blank" rel="noopener">Ábralo en otra pestaña</a>.</p>
    </iframe>
</section>

<section class="tarjeta">
    <h2>Así quedará su informe de actividades</h2>
    <iframe class="visor-pdf" src="/cuentas/nueva/vista-previa/informe" title="Vista previa del documento"
            aria-label="Vista previa del informe de actividades">
        <p>Su navegador no muestra el PDF aquí.
           <a class="enlace-suave" href="/cuentas/nueva/vista-previa/informe" target="_blank" rel="noopener">Ábralo en otra pestaña</a>.</p>
    </iframe>
</section>

<?php if (!$tieneFirma) { ?>
    <div class="alerta-error" role="alert">
        Falta su firma. <a class="enlace-suave" href="/cuentas/mis-datos?paso=3">Regístrela en «Mis datos»</a> y vuelva aquí.
    </div>
<?php } else { ?>
    <form class="tarjeta" method="post" action="/cuentas/nueva/enviar">
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
        <h2>Al enviar, usted firma estos documentos</h2>
        <p>Se usará la firma que registró en «Mis datos», con constancia de fecha, hora y conexión.</p>

        <label class="casilla">
            <input type="checkbox" name="confirmo" value="1" required>
            <span>Confirmo que la información es verdadera y que las actividades corresponden
            a lo realizado en el periodo.</span>
        </label>

        <button class="boton" type="submit">Enviar al supervisor</button>
    </form>
    <p class="ayuda"><a class="enlace-suave" href="/cuentas/nueva/2">Volver a revisar lo que escribió</a></p>
<?php } ?>

<?php $contenido = (string) ob_get_clean();
echo $vistas->render('publico/cuentas/_marco', [
    'titulo' => 'Revisar y enviar',
    'contenido' => $contenido,
    'paso' => 4,
    'conSesion' => true,
    'tokenCsrf' => $tokenCsrf,
]);

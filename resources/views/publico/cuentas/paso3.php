<?php
/**
 * P4 — Su planilla de seguridad social, con los valores sugeridos por el
 * sistema ya puestos (§8.3): el contratista solo corrige si su planilla
 * dice otra cosa.
 * Espera: $contrato, $cuenta, $guardada (?fila), $sugeridos, $mesSugerido,
 * $planilla (adjuntos), $error, $tokenCsrf.
 */
$pesosEnteros = static fn (mixed $v): string => $v === null ? '' : explode('.', (string) $v, 2)[0];
$valor = static fn (string $campo): string => $guardada !== null
    ? $pesosEnteros($guardada[$campo])
    : (string) ($sugeridos[$campo] ?? '');
ob_start(); ?>

<p>Escriba los datos de la planilla PILA que pagó para este periodo.</p>

<?php if ($error !== null) { ?><div class="alerta-error" role="alert"><?= $e($error) ?></div><?php } ?>

<form class="tarjeta" method="post" action="/cuentas/nueva/3" enctype="multipart/form-data">
    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">

    <label for="numero-planilla">Número de la planilla</label>
    <input id="numero-planilla" name="numero_planilla" type="text" inputmode="numeric" required
           value="<?= $e((string) ($guardada['numero_planilla'] ?? '')) ?>"
           aria-describedby="ayuda-planilla">
    <span class="ayuda" id="ayuda-planilla">Aparece en el comprobante de pago de su PILA.</span>

    <div class="dos-columnas">
        <div>
            <label for="mes-salud">Mes de salud</label>
            <input id="mes-salud" name="mes_salud" type="month" required
                   value="<?= $e((string) ($guardada['mes_salud'] ?? $mesSugerido)) ?>">
        </div>
        <div>
            <label for="mes-pension">Mes de pensión</label>
            <input id="mes-pension" name="mes_pension" type="month" required
                   value="<?= $e((string) ($guardada['mes_pension'] ?? $mesSugerido)) ?>">
        </div>
    </div>
    <p class="ayuda">Casi siempre es el mes anterior al del periodo que está cobrando.</p>

    <div class="dos-columnas">
        <div>
            <label for="ibc">Ingreso base de cotización (IBC)</label>
            <input id="ibc" name="ibc" type="number" min="1" required value="<?= $e($valor('ibc')) ?>"
                   aria-describedby="ayuda-sugeridos">
        </div>
        <div>
            <label for="aporte-salud">Aporte a salud</label>
            <input id="aporte-salud" name="aporte_salud" type="number" min="1" required
                   value="<?= $e($valor('aporte_salud')) ?>" aria-describedby="ayuda-sugeridos">
        </div>
        <div>
            <label for="aporte-pension">Aporte a pensión</label>
            <input id="aporte-pension" name="aporte_pension" type="number" min="1" required
                   value="<?= $e($valor('aporte_pension')) ?>" aria-describedby="ayuda-sugeridos">
        </div>
        <div>
            <label for="aporte-arl">Aporte a ARL (clase <?= (int) $sugeridos['clase'] ?>)</label>
            <input id="aporte-arl" name="aporte_arl" type="number" min="1" required
                   value="<?= $e($valor('aporte_arl')) ?>" aria-describedby="ayuda-sugeridos">
        </div>
    </div>
    <p class="ayuda" id="ayuda-sugeridos">Estos son valores sugeridos — corríjalos si su planilla dice otra cosa.</p>

    <?php if ((int) ($sugeridos['aporte_fsp'] ?? 0) > 0 || (int) $pesosEnteros($guardada['aporte_fsp'] ?? null) > 0) { ?>
        <label for="aporte-fsp">Fondo de solidaridad pensional</label>
        <input id="aporte-fsp" name="aporte_fsp" type="number" min="0"
               value="<?= $e($guardada !== null ? $pesosEnteros($guardada['aporte_fsp']) : (string) $sugeridos['aporte_fsp']) ?>">
    <?php } else { ?>
        <input type="hidden" name="aporte_fsp" value="0">
    <?php } ?>

    <label for="planilla">Suba su planilla (foto o PDF)</label>
    <input id="planilla" name="planilla" type="file" accept=".pdf,.jpg,.jpeg,.png"
           <?= $planilla === [] ? 'required' : '' ?> aria-describedby="ayuda-archivo">
    <span class="ayuda" id="ayuda-archivo">
        <?= $planilla !== [] ? 'Ya subió una planilla para esta cuenta; solo suba otra si quiere reemplazar la anterior.' : 'Una foto clara del comprobante sirve.' ?>
    </span>

    <button class="boton" type="submit">Continuar</button>
</form>

<?php $contenido = (string) ob_get_clean();
echo $vistas->render('publico/cuentas/_marco', [
    'titulo' => 'Su planilla de seguridad social',
    'contenido' => $contenido,
    'paso' => 3,
    'conSesion' => true,
    'tokenCsrf' => $tokenCsrf,
]);

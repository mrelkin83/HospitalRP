<?php
/**
 * P3 — Qué hizo este mes: una tarjeta por obligación, con autoguardado.
 * Espera: $contrato, $cuenta, $actividades, $periodoTexto, $error,
 * $mensaje, $tokenCsrf.
 */
$precargada = array_filter($actividades, static fn (array $a): bool => ($a['descripcion_ejecucion'] ?? null) !== null);
ob_start(); ?>

<p>Cuenta de cobro N° <?= (int) $cuenta['consecutivo'] ?> — Periodo <?= $e($periodoTexto) ?>.</p>

<?php if ($mensaje !== null) { ?><div class="alerta-exito" role="status"><?= $e($mensaje) ?></div><?php } ?>
<?php if ($error !== null) { ?><div class="alerta-error" role="alert"><?= $e($error) ?></div><?php } ?>
<?php if ($precargada !== [] && (string) $cuenta['estado'] === 'BORRADOR') { ?>
    <div class="alerta-aviso" role="status">Copiamos lo del mes pasado; cámbielo si hizo algo diferente.</div>
<?php } ?>

<form method="post" action="/cuentas/nueva/2" id="formulario-actividades"
      data-csrf="<?= $e($tokenCsrf) ?>">
    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">

    <?php foreach ($actividades as $actividad) { $oid = (int) $actividad['obligacion_id']; ?>
        <section class="tarjeta">
            <h2>Obligación <?= (int) $actividad['orden'] ?></h2>
            <p><?= $e((string) $actividad['texto']) ?></p>
            <label for="actividad-<?= $oid ?>">Qué hizo usted para cumplirla</label>
            <textarea id="actividad-<?= $oid ?>" name="actividad[<?= $oid ?>]"
                      class="actividad" data-obligacion="<?= $oid ?>"
                      aria-describedby="guardado-<?= $oid ?>"><?= $e((string) ($actividad['descripcion_ejecucion'] ?? '')) ?></textarea>
            <span class="ayuda" id="guardado-<?= $oid ?>" aria-live="polite"></span>
            <button class="boton-secundario" type="button" data-limpiar="actividad-<?= $oid ?>">Limpiar</button>
        </section>
    <?php } ?>

    <label class="casilla tarjeta">
        <input type="checkbox" name="continuar_con_vacias" value="1">
        <span>Dejé alguna obligación sin descripción a propósito (el supervisor lo verá así).</span>
    </label>

    <button class="boton" type="submit">Continuar</button>
</form>

<script src="/js/cuentas-portal.js" defer></script>

<?php $contenido = (string) ob_get_clean();
echo $vistas->render('publico/cuentas/_marco', [
    'titulo' => 'Qué hizo este mes',
    'contenido' => $contenido,
    'paso' => 2,
    'conSesion' => true,
    'tokenCsrf' => $tokenCsrf,
]);

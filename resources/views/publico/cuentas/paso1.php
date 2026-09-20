<?php
/**
 * P2 — Sus datos del contrato, solo lectura, con «¿Están correctos?».
 * Espera: $contratista, $contrato, $cuota, $imputaciones, $error, $tokenCsrf.
 */
$moneda = static fn (string $v): string => '$ ' . number_format((int) explode('.', $v, 2)[0], 0, ',', '.');
$objeto = (string) $contrato['objeto'];
$resumido = mb_strlen($objeto) > 200;
$cdp = implode(', ', array_column(array_filter($imputaciones, static fn (array $i): bool => $i['tipo'] === 'CDP'), 'numero'));
$rp = implode(', ', array_column(array_filter($imputaciones, static fn (array $i): bool => $i['tipo'] === 'RP'), 'numero'));
ob_start(); ?>

<?php if ($error !== null) { ?><div class="alerta-error" role="alert"><?= $e($error) ?></div><?php } ?>

<section class="tarjeta">
    <dl class="dato dos-columnas">
        <div><dt>Contrato N°</dt><dd><?= $e((string) $contrato['numero']) ?></dd></div>
        <div><dt>Valor total del contrato</dt><dd><?= $e($moneda((string) $contrato['valor_total'])) ?></dd></div>
        <div><dt>Valor de esta cuenta</dt><dd><?= $e($moneda((string) $cuota['valor'])) ?></dd></div>
        <div><dt>Periodo de esta cuenta</dt><dd>Del <?= $e((string) $cuota['periodo_desde']) ?> al <?= $e((string) $cuota['periodo_hasta']) ?></dd></div>
        <div><dt>Supervisor</dt><dd><?= $e((string) ($contrato['supervisor_nombre'] ?? 'Por asignar')) ?></dd></div>
        <div><dt>CDP / RP</dt><dd><?= $e($cdp !== '' ? $cdp : '—') ?> / <?= $e($rp !== '' ? $rp : '—') ?></dd></div>
    </dl>
    <dl class="dato">
        <dt>Objeto del contrato</dt>
        <?php if ($resumido) { ?>
            <dd><?= $e(mb_substr($objeto, 0, 200)) ?>…</dd>
            <details><summary class="enlace-suave">Ver completo</summary><p><?= $e($objeto) ?></p></details>
        <?php } else { ?>
            <dd><?= $e($objeto) ?></dd>
        <?php } ?>
    </dl>
</section>

<h2>¿Estos datos están correctos?</h2>

<form method="post" action="/cuentas/nueva/1">
    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
    <button class="boton" type="submit">Sí, continuar</button>
</form>

<details class="tarjeta">
    <summary class="enlace-suave">Algo está mal en estos datos</summary>
    <form method="post" action="/cuentas/nueva/reportar">
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
        <label for="detalle">Cuéntenos qué dato está mal</label>
        <textarea id="detalle" name="detalle" required maxlength="1000"
                  aria-describedby="ayuda-detalle"></textarea>
        <span class="ayuda" id="ayuda-detalle">Le avisaremos a la oficina encargada. Usted puede seguir con su cuenta mientras tanto.</span>
        <button class="boton-secundario" type="submit">Enviar el aviso</button>
    </form>
</details>

<?php $contenido = (string) ob_get_clean();
echo $vistas->render('publico/cuentas/_marco', [
    'titulo' => 'Sus datos del contrato',
    'contenido' => $contenido,
    'paso' => 1,
    'conSesion' => true,
    'tokenCsrf' => $tokenCsrf,
]);

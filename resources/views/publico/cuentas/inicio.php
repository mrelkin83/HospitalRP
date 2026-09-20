<?php
/**
 * P1 — Inicio del contratista: UN bloque grande con lo que toca hacer hoy
 * y la lista de cuentas anteriores.
 * Espera: $contratista, $contrato, $editable, $enRevision, $cuotaDisponible,
 * $cuotaFutura, $anteriores, $mensaje, $error, $tokenCsrf.
 */
$moneda = static fn (string $v): string => '$ ' . number_format((int) explode('.', $v, 2)[0], 0, ',', '.');
$fechaLarga = static function (?string $f): string {
    if ($f === null) { return ''; }
    $meses = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $t = strtotime($f);
    return date('j', $t) . ' de ' . $meses[(int) date('n', $t)] . ' de ' . date('Y', $t);
};
$estados = [
    'BORRADOR' => 'Sin terminar', 'ENVIADA' => 'En revisión', 'DEVUELTA' => 'Devuelta para corregir',
    'APROBADA' => 'Aprobada', 'ENTREGADA_FINANCIERA' => 'En Financiera', 'ANULADA' => 'Anulada',
];
ob_start(); ?>

<?php if ($mensaje !== null) { ?><div class="alerta-exito" role="status"><?= $e($mensaje) ?></div><?php } ?>
<?php if ($error !== null) { ?><div class="alerta-error" role="alert"><?= $e($error) ?></div><?php } ?>

<p>Contrato <strong><?= $e((string) $contrato['numero']) ?></strong> · Supervisor:
   <?= $e((string) ($contrato['supervisor_nombre'] ?? 'por asignar')) ?></p>

<?php if ($editable !== null && $editable['estado'] === 'DEVUELTA') { ?>
    <section class="tarjeta">
        <h2>Su cuenta N° <?= (int) $editable['consecutivo'] ?> fue devuelta</h2>
        <p><strong>Motivo:</strong> <?= $e((string) ($editable['observacion_supervisor'] ?? 'El supervisor pidió corregirla.')) ?></p>
        <a class="boton" href="/cuentas/nueva/2">Corregir y reenviar</a>
    </section>
<?php } elseif ($editable !== null) { ?>
    <section class="tarjeta">
        <h2>Tiene una cuenta sin terminar</h2>
        <p>La cuenta N° <?= (int) $editable['consecutivo'] ?> quedó guardada donde la dejó.</p>
        <a class="boton" href="/cuentas/nueva/2">Continuar</a>
    </section>
<?php } elseif ($enRevision !== null) { ?>
    <section class="tarjeta">
        <h2>Su cuenta N° <?= (int) $enRevision['consecutivo'] ?> está en revisión</h2>
        <p>La tiene el supervisor desde el <?= $e($fechaLarga(substr((string) $enRevision['enviada_en'], 0, 10))) ?>.
           Le avisaremos cuando haya novedades.</p>
    </section>
<?php } elseif ($cuotaDisponible !== null) { ?>
    <section class="tarjeta">
        <h2>Ya puede presentar su cuenta</h2>
        <p>Periodo: del <?= $e($fechaLarga((string) $cuotaDisponible['periodo_desde'])) ?>
           al <?= $e($fechaLarga((string) $cuotaDisponible['periodo_hasta'])) ?> ·
           Valor: <strong><?= $e($moneda((string) $cuotaDisponible['valor'])) ?></strong></p>
        <a class="boton" href="/cuentas/nueva/1">Presentar cuenta de cobro N° <?= (int) $cuotaDisponible['numero_cuota'] ?></a>
    </section>
<?php } elseif ($cuotaFutura !== null) { ?>
    <section class="tarjeta">
        <h2>No tiene cuentas pendientes</h2>
        <p>La próxima podrá presentarse desde el
           <?= $e($fechaLarga((string) $cuotaFutura['periodo_hasta'])) ?>.</p>
    </section>
<?php } else { ?>
    <section class="tarjeta">
        <h2>No tiene cuentas pendientes</h2>
        <p>Cuando el hospital registre el acta de inicio de su contrato, aquí
           aparecerá el botón para presentar su primera cuenta.</p>
    </section>
<?php } ?>

<?php if ($anteriores !== []) { ?>
    <h2>Sus cuentas</h2>
    <ul class="lista-cuentas">
        <?php foreach ($anteriores as $cuenta) { ?>
            <li>
                <span>N° <?= (int) $cuenta['consecutivo'] ?> · <?= $e($moneda((string) $cuenta['valor'])) ?><br>
                    <span class="estado"><?= $e($estados[(string) $cuenta['estado']] ?? (string) $cuenta['estado']) ?></span></span>
                <?php if ($cuenta['pdf_cuenta_cobro'] !== null) { ?>
                    <a class="enlace-suave" href="/cuentas/pdf/<?= (int) $cuenta['id'] ?>/cuenta">Ver PDF</a>
                <?php } ?>
            </li>
        <?php } ?>
    </ul>
<?php } ?>

<p><a class="enlace-suave" href="/cuentas/mis-datos">Mis datos</a> — teléfono, correo, banco y firma.</p>

<?php $contenido = (string) ob_get_clean();
echo $vistas->render('publico/cuentas/_marco', [
    'titulo' => 'Hola, ' . $contratista['nombres'],
    'contenido' => $contenido,
    'conSesion' => true,
    'tokenCsrf' => $tokenCsrf,
]);

<?php
/**
 * P3 — Qué hizo este mes: una tarjeta por obligación, con autoguardado.
 * Espera: $contrato, $cuenta, $actividades, $modoManual, $periodoTexto, $error,
 * $mensaje, $tokenCsrf, $productos, $evidencias, $maximoEvidencias,
 * $claveSinEntregable, $claveEnlace.
 */
$precargada = array_filter($actividades, static fn (array $a): bool => ($a['descripcion_ejecucion'] ?? null) !== null);

/**
 * El respaldo de una actividad: con qué producto la prueba y el archivo o
 * enlace que lo demuestra. Se dibuja igual en las dos modalidades
 * —obligaciones de la minuta y actividades escritas a mano—, así que vive
 * en una sola función y no en dos copias que se desincronizan.
 */
$respaldo = static function (array $actividad) use ($e, $productos, $evidencias, $maximoEvidencias, $claveSinEntregable, $claveEnlace, $tokenCsrf): void {
    $aid = (int) $actividad['id'];
    $suyas = $evidencias[$aid] ?? [];
    $elegido = (int) ($actividad['producto_id'] ?? 0);
    ?>
    <label for="producto-<?= $aid ?>">Con qué lo demuestra</label>
    <select class="campo" id="producto-<?= $aid ?>" name="producto[<?= $aid ?>]">
        <option value="">— Elija el producto —</option>
        <?php foreach ($productos as $producto) { ?>
            <option value="<?= (int) $producto['id'] ?>" <?= $elegido === (int) $producto['id'] ? 'selected' : '' ?>>
                <?= $e((string) $producto['nombre']) ?>
            </option>
        <?php } ?>
    </select>
    <p class="ayuda">
        Si esta obligación no deja un documento ni una foto, elija
        «NO APLICA». En los demás casos hay que adjuntar el soporte.
    </p>

    <?php if ($suyas !== []) { ?>
        <ul class="lista-evidencias">
            <?php foreach ($suyas as $evidencia) { ?>
                <li>
                    <?php if ((string) $evidencia['tipo'] === 'ENLACE') { ?>
                        <span><?= $e((string) $evidencia['enlace']) ?></span>
                    <?php } else { ?>
                        <span><?= $e((string) ($evidencia['nombre_original'] ?? 'archivo')) ?></span>
                    <?php } ?>
                    <form method="post" action="/cuentas/nueva/2/evidencia/<?= (int) $evidencia['id'] ?>/eliminar">
                        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                        <button class="boton-secundario" type="submit">Quitar</button>
                    </form>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>

    <?php if (count($suyas) < $maximoEvidencias) { ?>
        <label for="evidencia-<?= $aid ?>">Adjunte la evidencia (foto o PDF, máx. 10 MB)</label>
        <input class="campo" id="evidencia-<?= $aid ?>" type="file" name="evidencia[<?= $aid ?>][]"
               accept=".pdf,.jpg,.jpeg,.png" multiple>
        <label for="enlace-<?= $aid ?>">…o pegue el enlace, si eligió «LINK DE ACCESO»</label>
        <input class="campo" id="enlace-<?= $aid ?>" type="url" name="enlace[<?= $aid ?>]"
               placeholder="https://…" inputmode="url">
    <?php } else { ?>
        <p class="ayuda">Ya adjuntó el máximo de <?= (int) $maximoEvidencias ?> evidencias para esta actividad.</p>
    <?php }
};
ob_start(); ?>

<p>Cuenta de cobro N° <?= (int) $cuenta['consecutivo'] ?> — Periodo <?= $e($periodoTexto) ?>.</p>

<?php if ($mensaje !== null) { ?><div class="alerta-exito" role="status"><?= $e($mensaje) ?></div><?php } ?>
<?php if ($error !== null) { ?><div class="alerta-error" role="alert"><?= $e($error) ?></div><?php } ?>
<?php if ($precargada !== [] && (string) $cuenta['estado'] === 'BORRADOR') { ?>
    <div class="alerta-aviso" role="status">Copiamos lo del mes pasado; cámbielo si hizo algo diferente.</div>
<?php } ?>

<form method="post" action="/cuentas/nueva/2" id="formulario-actividades"
      enctype="multipart/form-data" data-csrf="<?= $e($tokenCsrf) ?>">
    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">

    <?php if ($modoManual) { ?>
        <div class="alerta-aviso" role="status">
            Este contrato no tiene obligaciones cargadas (la minuta no existe en
            digital o el escaneado no se puede leer), así que las actividades se
            escriben aquí a mano. Para quitar una, borre su título y guarde.
        </div>

        <?php $n = 0; foreach ($actividades as $actividad) { $aid = (int) $actividad['id']; $n++; ?>
            <section class="tarjeta">
                <h2>Actividad <?= $n ?></h2>
                <label for="titulo-<?= $aid ?>">A qué se comprometió (la obligación del contrato)</label>
                <input class="campo" id="titulo-<?= $aid ?>" name="actividad_manual[<?= $aid ?>][titulo]"
                       type="text" maxlength="300" value="<?= $e((string) ($actividad['titulo_manual'] ?? '')) ?>">
                <label for="descripcion-<?= $aid ?>">Qué hizo usted este periodo para cumplirla</label>
                <textarea id="descripcion-<?= $aid ?>" name="actividad_manual[<?= $aid ?>][descripcion]"><?= $e((string) ($actividad['descripcion_ejecucion'] ?? '')) ?></textarea>
                <?php $respaldo($actividad); ?>
            </section>
        <?php } ?>

        <?php for ($i = 0; $i < 3; $i++) { $n++; ?>
            <section class="tarjeta">
                <h2>Actividad nueva</h2>
                <label for="nuevo-titulo-<?= $i ?>">A qué se comprometió (la obligación del contrato)</label>
                <input class="campo" id="nuevo-titulo-<?= $i ?>" name="nueva_actividad[<?= $i ?>][titulo]"
                       type="text" maxlength="300" placeholder="Deje en blanco si no la necesita">
                <label for="nuevo-descripcion-<?= $i ?>">Qué hizo usted este periodo para cumplirla</label>
                <textarea id="nuevo-descripcion-<?= $i ?>" name="nueva_actividad[<?= $i ?>][descripcion]"></textarea>
            </section>
        <?php } ?>

        <p class="ayuda">¿Necesita más de tres filas nuevas? Guarde con «Continuar», vuelva a este paso y aparecerán otras tres.</p>
    <?php } else { ?>
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
                <?php $respaldo($actividad); ?>
            </section>
        <?php } ?>
    <?php } ?>

    <label class="casilla tarjeta">
        <input type="checkbox" name="continuar_con_vacias" value="1">
        <span>Dejé alguna <?= $modoManual ? 'actividad' : 'obligación' ?> sin descripción a propósito (el supervisor lo verá así).</span>
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

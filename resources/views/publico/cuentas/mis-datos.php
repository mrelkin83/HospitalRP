<?php
/**
 * P1a — Mis datos, en tres pantallas cortas: (1) contacto, (2) banco,
 * (3) documentos + firma + autorización de datos.
 * Espera: $contratista, $paso (1..3), $error, $mensaje, $tokenCsrf.
 */
$c = $contratista;
ob_start(); ?>

<?php if ($mensaje !== null) { ?><div class="alerta-exito" role="status"><?= $e($mensaje) ?></div><?php } ?>
<?php if ($error !== null) { ?><div class="alerta-error" role="alert"><?= $e($error) ?></div><?php } ?>

<?php if ($paso === 1) { ?>
    <form class="tarjeta" method="post" action="/cuentas/mis-datos/1">
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
        <h2>1 de 3 — Cómo lo contactamos</h2>

        <label for="celular">Su número de celular</label>
        <input id="celular" name="celular" type="tel" inputmode="numeric" required
               pattern="3\d{9}" value="<?= $e((string) ($c['celular'] ?? '')) ?>"
               aria-describedby="ayuda-celular">
        <span class="ayuda" id="ayuda-celular">10 números, empezando por 3. Ejemplo: 3001234567.</span>

        <label for="correo">Su correo electrónico</label>
        <input id="correo" name="correo" type="email" value="<?= $e((string) ($c['correo'] ?? '')) ?>"
               aria-describedby="ayuda-correo">
        <span class="ayuda" id="ayuda-correo">A este correo le llegará el código para entrar.</span>

        <label for="direccion">Su dirección</label>
        <input id="direccion" name="direccion" type="text" maxlength="200"
               value="<?= $e((string) ($c['direccion'] ?? '')) ?>">

        <button class="boton" type="submit">Continuar</button>
    </form>
<?php } elseif ($paso === 2) { ?>
    <form class="tarjeta" method="post" action="/cuentas/mis-datos/2" enctype="multipart/form-data">
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
        <h2>2 de 3 — Dónde le consignan</h2>

        <label for="banco">Banco</label>
        <input id="banco" name="banco" type="text" required maxlength="100"
               value="<?= $e((string) ($c['banco'] ?? '')) ?>">

        <label for="tipo-cuenta">Tipo de cuenta</label>
        <select id="tipo-cuenta" name="tipo_cuenta" required>
            <option value="">Elija una…</option>
            <option value="AHORROS" <?= ($c['tipo_cuenta'] ?? '') === 'AHORROS' ? 'selected' : '' ?>>Ahorros</option>
            <option value="CORRIENTE" <?= ($c['tipo_cuenta'] ?? '') === 'CORRIENTE' ? 'selected' : '' ?>>Corriente</option>
        </select>

        <label for="numero-cuenta">Número de la cuenta</label>
        <input id="numero-cuenta" name="numero_cuenta" type="text" inputmode="numeric" required
               maxlength="40" value="<?= $e((string) ($c['numero_cuenta'] ?? '')) ?>">

        <label for="certificacion">Certificación bancaria (foto o PDF)</label>
        <input id="certificacion" name="certificacion_bancaria" type="file" accept=".pdf,.jpg,.jpeg,.png"
               aria-describedby="ayuda-cert">
        <span class="ayuda" id="ayuda-cert"><?= ($c['certificacion_bancaria_archivo'] ?? null) !== null ? 'Ya tenemos una; solo suba otra si cambió de cuenta.' : 'La emite su banco; puede tomarle una foto.' ?></span>

        <button class="boton" type="submit">Continuar</button>
    </form>
<?php } else { ?>
    <form class="tarjeta" method="post" action="/cuentas/mis-datos/3" enctype="multipart/form-data">
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
        <h2>3 de 3 — Sus documentos y su firma</h2>

        <label for="cedula-archivo">Foto de su cédula</label>
        <input id="cedula-archivo" name="cedula" type="file" accept=".pdf,.jpg,.jpeg,.png"
               aria-describedby="ayuda-ced">
        <span class="ayuda" id="ayuda-ced"><?= ($c['cedula_archivo'] ?? null) !== null ? 'Ya la tenemos.' : 'Por ambos lados si es posible.' ?></span>

        <label for="rut-archivo">Su RUT</label>
        <input id="rut-archivo" name="rut" type="file" accept=".pdf,.jpg,.jpeg,.png"
               aria-describedby="ayuda-rut">
        <span class="ayuda" id="ayuda-rut"><?= ($c['rut_archivo'] ?? null) !== null ? 'Ya lo tenemos.' : 'Se descarga gratis en www.dian.gov.co.' ?></span>

        <h2>Su firma</h2>
        <p class="ayuda">Súbala como imagen O dibújela con el dedo en el recuadro. Es la firma que saldrá en sus cuentas de cobro.</p>
        <?= ($c['firma_archivo'] ?? null) !== null ? '<p class="alerta-exito">Ya tenemos su firma registrada. Solo cámbiela si lo necesita.</p>' : '' ?>

        <label for="firma-archivo">Subir imagen de la firma</label>
        <input id="firma-archivo" name="firma" type="file" accept=".jpg,.jpeg,.png">

        <label for="firma-lienzo">O dibújela aquí</label>
        <canvas id="firma-lienzo" class="firma-lienzo" width="600" height="200"
                aria-label="Recuadro para dibujar su firma con el dedo o el ratón"></canvas>
        <input type="hidden" name="firma_dibujada" id="firma-dibujada">
        <button class="boton-secundario" type="button" id="limpiar-firma">Borrar y dibujar de nuevo</button>

        <?php if (($c['acepto_tratamiento_datos_en'] ?? null) === null) { ?>
            <h2>Sus datos personales</h2>
            <label class="casilla">
                <input type="checkbox" name="acepto_datos" value="1" required>
                <span>Autorizo a la E.S.E. Hospital Rosario Pumarejo de López el tratamiento de mis
                datos personales para la gestión de mis cuentas de cobro, conforme a la Ley 1581 de
                2012 y a la política de tratamiento de datos de la entidad. (Texto provisional
                mientras la oficina jurídica entrega el definitivo.)</span>
            </label>
        <?php } ?>

        <button class="boton" type="submit">Guardar y terminar</button>
    </form>
    <script src="/js/cuentas-portal.js" defer></script>
<?php } ?>

<?php $contenido = (string) ob_get_clean();
echo $vistas->render('publico/cuentas/_marco', [
    'titulo' => 'Sus datos',
    'contenido' => $contenido,
    'conSesion' => true,
    'tokenCsrf' => $tokenCsrf,
]);

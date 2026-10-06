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

<?php if ($faltantes !== []) { ?>
    <div class="alerta-aviso" role="status">
        Para poder presentar su cuenta de cobro todavía falta:
        <strong><?= $e(implode(', ', $faltantes)) ?></strong>.
    </div>
<?php } ?>

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
        <span class="ayuda" id="ayuda-correo">A este correo le avisamos cuando su cuenta sea revisada, aprobada o devuelta.</span>


        <button class="boton" type="submit">Continuar</button>
    </form>
    <?php if (($c['banco'] ?? null) !== null) { ?>
        <p class="ayuda"><a class="enlace-suave" href="/cuentas/mis-datos?paso=2">Ir al paso 2 sin cambiar nada</a></p>
    <?php } ?>
<?php } elseif ($paso === 2) { ?>
    <form class="tarjeta" method="post" action="/cuentas/mis-datos/2" enctype="multipart/form-data">
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
        <h2>2 de 3 — Dónde le consignan</h2>

        <?php /* Desplegable y no texto libre: escrito a mano salían
                 «Bancolombia», «BANCOLOMBIA S.A.» y «bancolombia» para la
                 misma entidad, en un documento con el que Tesorería paga. */ ?>
        <label for="banco">Banco</label>
        <select id="banco" name="banco" required>
            <option value="">Elija su banco…</option>
            <?php foreach ($bancos as $banco) { ?>
                <option value="<?= $e($banco) ?>" <?= ($c['banco'] ?? '') === $banco ? 'selected' : '' ?>>
                    <?= $e($banco) ?>
                </option>
            <?php } ?>
        </select>

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

        <?php /* Adelante y atrás: sin el segundo, corregir un dato del
                 paso anterior obliga a salir y volver a entrar. */ ?>
        <button class="boton" type="submit">Continuar</button>
        <a class="boton-secundario" href="/cuentas/mis-datos?paso=1">Atrás</a>
    </form>
<?php } else { ?>
    <form class="tarjeta" method="post" action="/cuentas/mis-datos/3" enctype="multipart/form-data">
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
        <h2>3 de 3 — Sus documentos y su firma</h2>

        <h2>Copia de su cédula</h2>
        <?php /* Un PDF escaneado ya trae las dos caras; una foto, no. De ahí
                 que el campo acepte VARIOS archivos y la regla sea: un PDF, o
                 dos fotos. Pedir «un archivo por cara» obligaba a partir en
                 dos un PDF que ya estaba bien. */ ?>
        <div class="alerta-aviso" role="note">
            <p><strong>Si ya la tiene escaneada:</strong> suba el PDF con las dos caras. Con eso basta.</p>
            <p><strong>Si la va a fotografiar con el celular:</strong> tome <strong>dos fotos</strong>,
            una por cada cara, y selecciónelas juntas — en una sola toma no caben las dos.</p>
            <p>Para que se lea: ponga el documento sobre una superficie plana y de color
            oscuro, con buena luz y sin sombras encima, y tome la foto de frente,
            procurando que la cédula ocupe casi toda la pantalla.</p>
        </div>

        <label for="cedula-archivo">Su cédula</label>
        <input id="cedula-archivo" name="cedula[]" type="file" accept="image/*,.pdf" multiple
               aria-describedby="ayuda-ced">
        <span class="ayuda" id="ayuda-ced">
            <?php
            $tieneCedula = ($c['cedula_archivo'] ?? null) !== null;
            $esPdf = $tieneCedula && str_ends_with((string) $c['cedula_archivo'], '.pdf');
            $tieneReverso = ($c['cedula_reverso_archivo'] ?? null) !== null;

            if ($esPdf) {
                echo 'Ya tenemos su cédula en PDF. Solo suba otra si quiere reemplazarla.';
            } elseif ($tieneCedula && $tieneReverso) {
                echo 'Ya tenemos las dos caras. Solo suba otras si quiere reemplazarlas.';
            } elseif ($tieneCedula) {
                echo 'Tenemos una sola cara: falta la otra. Tome la foto que falta y súbala.';
            } else {
                echo 'Un PDF con las dos caras, o las dos fotos seleccionadas juntas.';
            }
            ?>
        </span>

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
        <a class="boton-secundario" href="/cuentas/mis-datos?paso=2">Atrás</a>
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

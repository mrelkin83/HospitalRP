<?php
/**
 * Ajustes del sitio, agrupados y dibujados según el tipo de cada clave.
 * Espera: $grupos, $cambios (list<string>), $mensaje (?string),
 * $sinCambios (bool), $tokenCsrf.
 */
?>
<h1 class="text-2xl">Ajustes del sitio</h1>
<p class="mt-2 texto-suave">
    Los datos que cambian sin tocar el código: teléfonos, correos, dirección,
    redes sociales. Lo que se guarde aquí se ve de inmediato en el sitio
    público, y cada cambio queda en el registro de auditoría.
</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status">
        <p><?= $e($mensaje) ?></p>
        <?php if ($cambios !== []) { ?>
            <p class="mt-1 text-sm">Se actualizaron: <?= $e(implode(', ', $cambios)) ?></p>
        <?php } ?>
    </div>
<?php } ?>
<?php if ($sinCambios) { ?>
    <div class="alerta alerta-aviso mt-4" role="status">No cambiaste ningún valor.</div>
<?php } ?>

<form method="post" action="/admin/configuracion">
    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">

    <?php foreach ($grupos as $claveGrupo => $grupo) { ?>
        <section class="tarjeta mt-6">
            <h2 class="text-base texto-titular"><?= $e($grupo['titulo']) ?></h2>

            <div class="mt-4 flex flex-col gap-4">
                <?php foreach ($grupo['ajustes'] as $ajuste) {
                    $clave = (string) $ajuste['clave'];
                    $campo = 'ajustes[' . $clave . ']';
                    $idCampo = 'ajuste-' . str_replace('.', '-', $clave);
                    $valor = (string) ($ajuste['valor'] ?? '');
                    $tipo = (string) $ajuste['tipo'];
                    ?>
                    <div>
                        <?php if ($tipo === 'booleano') { ?>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" id="<?= $e($idCampo) ?>" name="<?= $e($campo) ?>" value="1"
                                       <?= in_array($valor, ['1', 'true', 'on'], true) ? 'checked' : '' ?>>
                                <span class="font-semibold"><?= $e((string) $ajuste['etiqueta']) ?></span>
                            </label>
                        <?php } else { ?>
                            <label class="etiqueta" for="<?= $e($idCampo) ?>"><?= $e((string) $ajuste['etiqueta']) ?></label>

                            <?php if ($tipo === 'texto_largo' || $tipo === 'html' || $tipo === 'json') { ?>
                                <textarea class="campo" id="<?= $e($idCampo) ?>" name="<?= $e($campo) ?>" rows="4"><?= $e($valor) ?></textarea>
                            <?php } elseif ($tipo === 'numero') { ?>
                                <input class="campo" id="<?= $e($idCampo) ?>" type="number" step="any" name="<?= $e($campo) ?>" value="<?= $e($valor) ?>">
                            <?php } elseif ($tipo === 'color') { ?>
                                <input class="campo" id="<?= $e($idCampo) ?>" type="color" name="<?= $e($campo) ?>" value="<?= $e($valor !== '' ? $valor : '#000000') ?>">
                            <?php } else { ?>
                                <input class="campo" id="<?= $e($idCampo) ?>" type="text" name="<?= $e($campo) ?>" maxlength="500" value="<?= $e($valor) ?>">
                            <?php } ?>
                        <?php } ?>

                        <p class="mt-1 text-sm texto-suave">
                            <?php if (($ajuste['ayuda'] ?? null) !== null) { ?>
                                <?= $e((string) $ajuste['ayuda']) ?> ·
                            <?php } ?>
                            <code><?= $e($clave) ?></code>
                        </p>
                    </div>
                <?php } ?>
            </div>
        </section>
    <?php } ?>

    <p class="mt-6"><button type="submit" class="boton boton-primario">Guardar ajustes</button></p>
</form>

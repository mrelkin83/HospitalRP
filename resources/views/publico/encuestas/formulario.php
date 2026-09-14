<?php
/**
 * /encuestas/{slug} — cuestionario.
 * Espera: $encuesta, $preguntas, $errores (pregunta_id => mensaje).
 *
 * El aviso de «no escribas datos personales» va ANTES del formulario y no al
 * final: si el ciudadano escribe su cédula en una pregunta abierta, ese dato
 * queda guardado, y avisar después no sirve de nada.
 */
$escala = ['1', '2', '3', '4', '5'];
?>
<div class="mx-auto max-w-2xl px-4 py-10">
    <h1><?= $e((string) $encuesta['titulo']) ?></h1>

    <?php if (!empty($encuesta['descripcion'])) { ?>
        <p class="mt-3 texto-suave max-w-prose"><?= $e((string) $encuesta['descripcion']) ?></p>
    <?php } ?>

    <div class="mt-6 tarjeta">
        <p class="text-sm">
            Esta encuesta es <strong>anónima</strong>. No registramos tu nombre,
            documento, teléfono ni correo. <strong>No escribas datos personales
            ni información de salud</strong> en las preguntas abiertas.
        </p>
    </div>

    <?php if ($errores !== []) { ?>
        <div class="mt-6 tarjeta" role="alert">
            <p>Revisa las preguntas marcadas: faltan respuestas obligatorias.</p>
        </div>
    <?php } ?>

    <form method="post" action="/encuestas/<?= $e((string) $encuesta['slug']) ?>" class="mt-8 flex flex-col gap-8">
        <?php foreach ($preguntas as $p) {
            $campo = 'p' . $p['id'];
            $error = $errores[$p['id']] ?? null;
            $idError = $campo . '-error';
            ?>
            <fieldset<?= $error !== null ? ' aria-describedby="' . $e($idError) . '"' : '' ?>>
                <legend class="texto-titular">
                    <?= $e($p['enunciado']) ?>
                    <?php if ($p['obligatoria']) { ?><span aria-hidden="true">*</span><span class="sr-only">(obligatoria)</span><?php } ?>
                </legend>

                <?php if ($error !== null) { ?>
                    <p id="<?= $e($idError) ?>" class="mt-1 text-sm" role="alert"><?= $e($error) ?></p>
                <?php } ?>

                <?php if ($p['tipo'] === 'texto') { ?>
                    <textarea id="<?= $e($campo) ?>" name="<?= $e($campo) ?>[]" rows="4" maxlength="2000"
                              class="mt-2 w-full"<?= $p['obligatoria'] ? ' required' : '' ?>></textarea>

                <?php } elseif ($p['tipo'] === 'escala') { ?>
                    <p class="mt-1 text-sm texto-suave">1 es muy malo y 5 es excelente.</p>
                    <div class="mt-2 flex flex-wrap gap-4">
                        <?php foreach ($escala as $valor) { ?>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="<?= $e($campo) ?>[]" value="<?= $e($valor) ?>"
                                       <?= $p['obligatoria'] ? 'required' : '' ?>>
                                <?= $e($valor) ?>
                            </label>
                        <?php } ?>
                    </div>

                <?php } else {
                    $multiple = $p['tipo'] === 'opcion_multiple'; ?>
                    <div class="mt-2 flex flex-col gap-2">
                        <?php foreach ($p['opciones'] as $opcion) { ?>
                            <label class="flex items-center gap-2">
                                <input type="<?= $multiple ? 'checkbox' : 'radio' ?>"
                                       name="<?= $e($campo) ?>[]" value="<?= $e($opcion) ?>"
                                       <?= !$multiple && $p['obligatoria'] ? 'required' : '' ?>>
                                <?= $e($opcion) ?>
                            </label>
                        <?php } ?>
                    </div>
                <?php } ?>
            </fieldset>
        <?php } ?>

        <?php /* Trampa para robots: oculta a la vista y al lector de pantalla. */ ?>
        <div hidden aria-hidden="true">
            <label for="sitio_web">No llenar</label>
            <input type="text" id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off">
        </div>

        <button type="submit" class="boton self-start">Enviar respuesta</button>
    </form>
</div>

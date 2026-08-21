<?php
/**
 * Formulario dinámico de radicación.
 * Espera: $tipo (TipoTramite), $enviado (?SolicitudTramite), $errores (array<string,string>).
 */

$error = static function (string $campo) use ($errores, $e): string {
    if (!isset($errores[$campo])) {
        return '';
    }

    return '<p class="error-campo" id="error-' . $campo . '">' . $e($errores[$campo]) . '</p>';
};

$anonimoMarcado = $enviado?->anonimo ?? false;
?>
<div class="mx-auto max-w-6xl px-4 py-10">
    <nav aria-label="Miga de pan" class="text-sm texto-suave">
        <a href="/">Inicio</a> › <a href="/atencion-ciudadania/tramites">Trámites</a>
    </nav>

    <h1 class="mt-4 max-w-3xl"><?= $e($tipo->nombre) ?></h1>

    <?php if ($tipo->descripcion !== null) { ?>
        <p class="mt-3 texto-suave max-w-prose"><?= $e($tipo->descripcion) ?></p>
    <?php } ?>
    <p class="mt-2 text-sm texto-suave">
        Plazo de respuesta: <?= $tipo->diasTermino ?> días <?= $tipo->diasHabiles ? 'hábiles' : 'calendario' ?>
        <?php if ($tipo->fundamentoLegal !== null) { ?> · <?= $e($tipo->fundamentoLegal) ?><?php } ?>
    </p>

    <?php if ($errores !== []) { ?>
        <div class="alerta alerta-error mt-6 max-w-3xl" role="alert">
            Revisa los campos marcados: falta información o hay un dato inválido.
        </div>
    <?php } ?>

    <form class="mt-8 max-w-3xl flex flex-col gap-6" method="post"
          action="/atencion-ciudadania/tramites/<?= $e($tipo->slug) ?>" enctype="multipart/form-data">

        <?php if ($tipo->permiteAnonimo) { ?>
            <div class="tarjeta">
                <label class="flex items-start gap-3">
                    <input class="mt-1" type="checkbox" name="anonimo" value="1" <?= $anonimoMarcado ? 'checked' : '' ?>>
                    <span>
                        <strong class="texto-titular">Presentar de forma anónima</strong>
                        <span class="block text-sm texto-suave">
                            No te pediremos identificación, pero no podremos responderte
                            directamente ni podrás consultar el estado con tu documento.
                        </span>
                    </span>
                </label>
            </div>
        <?php } ?>

        <fieldset class="tarjeta">
            <legend class="etiqueta px-1">Tus datos<?= $tipo->permiteAnonimo ? ' (si no eres anónimo)' : '' ?></legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="etiqueta" for="nombre">Nombre completo</label>
                    <input class="campo" type="text" id="nombre" name="nombre" maxlength="200" autocomplete="name"
                           value="<?= $e($enviado?->nombre ?? '') ?>"
                           <?= isset($errores['nombre']) ? 'aria-invalid="true" aria-describedby="error-nombre"' : '' ?>>
                    <?= $error('nombre') ?>
                </div>
                <div class="grid grid-cols-[auto_1fr] gap-2">
                    <div>
                        <label class="etiqueta" for="tipo_documento">Tipo</label>
                        <select class="campo" id="tipo_documento" name="tipo_documento">
                            <?php foreach (['CC', 'TI', 'CE', 'PA', 'NIT', 'OTRO'] as $td) { ?>
                                <option value="<?= $td ?>" <?= ($enviado?->tipoDocumento ?? 'CC') === $td ? 'selected' : '' ?>><?= $td ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div>
                        <label class="etiqueta" for="numero_documento">Número de documento</label>
                        <input class="campo" type="text" id="numero_documento" name="numero_documento" maxlength="40"
                               value="<?= $e($enviado?->numeroDocumento ?? '') ?>"
                               <?= isset($errores['numero_documento']) ? 'aria-invalid="true" aria-describedby="error-numero_documento"' : '' ?>>
                        <?= $error('numero_documento') ?>
                    </div>
                </div>
                <div>
                    <label class="etiqueta" for="correo">Correo electrónico</label>
                    <input class="campo" type="email" id="correo" name="correo" maxlength="160" autocomplete="email"
                           value="<?= $e($enviado?->correo ?? '') ?>"
                           <?= isset($errores['correo']) ? 'aria-invalid="true" aria-describedby="error-correo"' : '' ?>>
                    <p class="ayuda-campo">Al correo llegan el radicado y las novedades.</p>
                    <?= $error('correo') ?>
                </div>
                <div>
                    <label class="etiqueta" for="telefono">Teléfono (opcional)</label>
                    <input class="campo" type="tel" id="telefono" name="telefono" maxlength="60" autocomplete="tel"
                           value="<?= $e($enviado?->telefono ?? '') ?>">
                </div>
                <div>
                    <label class="etiqueta" for="municipio">Municipio (opcional)</label>
                    <input class="campo" type="text" id="municipio" name="municipio" maxlength="120"
                           value="<?= $e($enviado?->municipio ?? 'Valledupar') ?>">
                </div>
                <div>
                    <label class="etiqueta" for="medio_respuesta">¿Cómo prefieres la respuesta?</label>
                    <select class="campo" id="medio_respuesta" name="medio_respuesta">
                        <option value="correo">Correo electrónico</option>
                        <option value="telefono" <?= ($enviado?->medioRespuesta ?? '') === 'telefono' ? 'selected' : '' ?>>Llamada telefónica</option>
                        <option value="fisico" <?= ($enviado?->medioRespuesta ?? '') === 'fisico' ? 'selected' : '' ?>>Correspondencia física</option>
                        <option value="presencial" <?= ($enviado?->medioRespuesta ?? '') === 'presencial' ? 'selected' : '' ?>>Presencial</option>
                    </select>
                </div>
            </div>
        </fieldset>

        <fieldset class="tarjeta">
            <legend class="etiqueta px-1">Tu solicitud</legend>

            <?php foreach ($tipo->campos as $campo) { ?>
                <?php $id = 'campo_' . $campo['id']; $valor = $enviado?->valores[$campo['id']] ?? ''; ?>
                <div class="mt-4 first:mt-0">
                    <label class="etiqueta" for="<?= $id ?>"><?= $e($campo['etiqueta']) ?><?= $campo['requerido'] ? '' : ' (opcional)' ?></label>
                    <?php if (in_array($campo['tipo_campo'], ['select', 'radio'], true)) { ?>
                        <select class="campo" id="<?= $id ?>" name="<?= $id ?>" <?= $campo['requerido'] ? 'required' : '' ?>>
                            <option value="">— Elige —</option>
                            <?php foreach ($campo['opciones'] as $opcion) { ?>
                                <option value="<?= $e($opcion) ?>" <?= $valor === $opcion ? 'selected' : '' ?>><?= $e($opcion) ?></option>
                            <?php } ?>
                        </select>
                    <?php } elseif ($campo['tipo_campo'] === 'texto_largo') { ?>
                        <textarea class="campo" id="<?= $id ?>" name="<?= $id ?>" rows="4" <?= $campo['requerido'] ? 'required' : '' ?>><?= $e($valor) ?></textarea>
                    <?php } else { ?>
                        <?php $tipoInput = match ($campo['tipo_campo']) {
                            'numero' => 'number', 'fecha' => 'date', 'correo' => 'email', 'telefono' => 'tel',
                            default => 'text',
                        }; ?>
                        <input class="campo" type="<?= $tipoInput ?>" id="<?= $id ?>" name="<?= $id ?>"
                               value="<?= $e($valor) ?>" <?= $campo['requerido'] ? 'required' : '' ?>>
                    <?php } ?>
                    <?php if ($campo['ayuda'] !== null) { ?>
                        <p class="ayuda-campo"><?= $e($campo['ayuda']) ?></p>
                    <?php } ?>
                    <?= $error($id) ?>
                </div>
            <?php } ?>

            <div class="mt-4">
                <label class="etiqueta" for="asunto">Asunto</label>
                <input class="campo" type="text" id="asunto" name="asunto" required maxlength="300"
                       value="<?= $e($enviado?->asunto ?? '') ?>"
                       <?= isset($errores['asunto']) ? 'aria-invalid="true" aria-describedby="error-asunto"' : '' ?>>
                <?= $error('asunto') ?>
            </div>

            <div class="mt-4">
                <label class="etiqueta" for="descripcion">Cuéntanos tu solicitud</label>
                <textarea class="campo" id="descripcion" name="descripcion" rows="6" required
                          <?= isset($errores['descripcion']) ? 'aria-invalid="true" aria-describedby="error-descripcion"' : '' ?>><?= $e($enviado?->descripcion ?? '') ?></textarea>
                <?= $error('descripcion') ?>
            </div>

            <div class="mt-4">
                <label class="etiqueta" for="adjuntos">Archivos adjuntos<?= $tipo->requiereAdjunto ? '' : ' (opcional)' ?></label>
                <input class="campo" type="file" id="adjuntos" name="adjuntos[]" multiple
                       accept="image/jpeg,image/png,image/webp,application/pdf"
                       <?= isset($errores['adjuntos']) ? 'aria-invalid="true" aria-describedby="error-adjuntos"' : '' ?>>
                <p class="ayuda-campo">Hasta <?= $tipo->maxAdjuntos ?> archivos (imágenes o PDF, máximo 20 MB cada uno).</p>
                <?= $error('adjuntos') ?>
            </div>
        </fieldset>

        <div class="tarjeta <?= isset($errores['autoriza_datos']) ? 'border-error-600' : '' ?>">
            <label class="flex items-start gap-3">
                <?php /* JAMÁS premarcada (Ley 1581, CLAUDE.md §6). */ ?>
                <input class="mt-1" type="checkbox" name="autoriza_datos" value="1"
                       <?= isset($errores['autoriza_datos']) ? 'aria-invalid="true" aria-describedby="error-autoriza_datos"' : '' ?>>
                <span>
                    Autorizo a la E.S.E. Hospital Rosario Pumarejo de López el tratamiento de mis
                    datos personales para gestionar y responder esta solicitud, según la
                    <a href="/politica-tratamiento-datos" target="_blank" rel="noopener">política de tratamiento de datos</a>.
                </span>
            </label>
            <?= $error('autoriza_datos') ?>
        </div>

        <div>
            <button type="submit" class="boton boton-primario text-lg">Radicar solicitud</button>
        </div>
    </form>
</div>

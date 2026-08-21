<?php
/**
 * Consulta pública por radicado + documento.
 * Espera: $resultado (?array{tramite: array, seguimientos: list}), $noEncontrado (bool).
 */

use App\Core\Support\Fechas;
?>
<div class="mx-auto max-w-6xl px-4 py-10">
    <nav aria-label="Miga de pan" class="text-sm texto-suave">
        <a href="/">Inicio</a> › <a href="/atencion-ciudadania">Atención a la Ciudadanía</a>
    </nav>

    <h1 class="mt-4">Consulta tu trámite</h1>
    <p class="mt-3 texto-suave max-w-prose">
        Escribe el número de radicado y el documento con el que radicaste.
        Las solicitudes anónimas se consultan solo con el radicado.
    </p>

    <form class="tarjeta mt-6 max-w-xl grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end"
          method="post" action="/atencion-ciudadania/pqrsdf/consultar">
        <div>
            <label class="etiqueta" for="radicado">Número de radicado</label>
            <input class="campo" type="text" id="radicado" name="radicado" required
                   placeholder="HRPL-2026-000123">
        </div>
        <div>
            <label class="etiqueta" for="numero_documento">Número de documento</label>
            <input class="campo" type="text" id="numero_documento" name="numero_documento" maxlength="40">
        </div>
        <button type="submit" class="boton boton-primario">Consultar</button>
    </form>

    <?php if ($noEncontrado) { ?>
        <div class="alerta alerta-error mt-6 max-w-xl" role="alert">
            No encontramos una solicitud con esos datos. Verifica el radicado y
            el documento; si el problema persiste, comunícate con nuestras
            <a href="/contacto">líneas de atención</a>.
        </div>
    <?php } ?>

    <?php if ($resultado !== null) { ?>
        <?php $tramite = $resultado['tramite']; ?>
        <section class="mt-8 max-w-xl" aria-labelledby="resultado-titulo">
            <div class="tarjeta">
                <h2 id="resultado-titulo" class="text-base"><?= $e((string) $tramite['radicado']) ?></h2>
                <p class="mt-1 text-sm texto-suave"><?= $e((string) $tramite['tipo_nombre']) ?></p>
                <p class="mt-3">
                    <span class="insignia <?= ((int) $tramite['estado_es_final']) === 1 ? 'insignia-exito' : 'insignia-aviso' ?>">
                        <?= $e((string) $tramite['estado_nombre']) ?>
                    </span>
                </p>
                <dl class="mt-4 grid gap-x-8 gap-y-2 sm:grid-cols-2 text-sm">
                    <div>
                        <dt class="font-semibold texto-suave">Radicado el</dt>
                        <dd><?= $e(Fechas::larga((string) $tramite['recibido_en'])) ?></dd>
                    </div>
                    <div>
                        <dt class="font-semibold texto-suave">Fecha límite de respuesta</dt>
                        <dd><?= $e(Fechas::larga((string) $tramite['vence_en'])) ?></dd>
                    </div>
                </dl>
            </div>

            <?php if ($resultado['seguimientos'] !== []) { ?>
                <h3 class="mt-6 text-base">Historial</h3>
                <ol class="mt-3 flex flex-col gap-3">
                    <?php foreach ($resultado['seguimientos'] as $seguimiento) { ?>
                        <li class="tarjeta">
                            <p class="text-sm texto-suave"><?= $e(Fechas::larga((string) $seguimiento['creado_en'])) ?></p>
                            <?php if (($seguimiento['estado_nuevo_nombre'] ?? null) !== null) { ?>
                                <p class="mt-1 font-semibold texto-titular"><?= $e((string) $seguimiento['estado_nuevo_nombre']) ?></p>
                                <?php if (($seguimiento['descripcion_publica'] ?? null) !== null) { ?>
                                    <p class="mt-1 text-sm texto-suave"><?= $e((string) $seguimiento['descripcion_publica']) ?></p>
                                <?php } ?>
                            <?php } ?>
                            <?php if (($seguimiento['nota'] ?? null) !== null && $seguimiento['nota'] !== '') { ?>
                                <p class="mt-1 text-sm"><?= $e((string) $seguimiento['nota']) ?></p>
                            <?php } ?>
                        </li>
                    <?php } ?>
                </ol>
            <?php } ?>
        </section>
    <?php } ?>
</div>

<?php
/**
 * Detalle de un trámite en la bandeja.
 * Espera: $tramite (fila), $tipo (?TipoTramite), $valores, $adjuntos,
 *         $seguimientos (todos, internos incluidos), $dependencias,
 *         $usuarios, $mensaje, $tokenCsrf.
 */

use App\Core\Support\Fechas;
?>
<nav class="text-sm"><a href="/admin/tramites">← Volver a la bandeja</a></nav>

<div class="mt-3 flex flex-wrap items-center gap-3">
    <h1 class="text-2xl"><?= $e((string) $tramite['radicado']) ?></h1>
    <span class="insignia <?= ((int) $tramite['estado_es_final']) === 1 ? 'insignia-exito' : 'insignia-aviso' ?>">
        <?= $e((string) $tramite['estado_nombre']) ?>
    </span>
    <span class="text-sm texto-suave">vence el <?= $e(Fechas::larga((string) $tramite['vence_en'])) ?></span>
</div>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>

<div class="mt-6 grid gap-8 lg:grid-cols-3">
    <div class="lg:col-span-2 flex flex-col gap-6">
        <section class="tarjeta" aria-labelledby="t-solicitud">
            <h2 id="t-solicitud" class="text-base"><?= $e((string) $tramite['asunto']) ?></h2>
            <p class="mt-3 whitespace-pre-line"><?= $e((string) $tramite['descripcion']) ?></p>

            <?php if ($valores !== []) { ?>
                <dl class="mt-4 grid gap-x-8 gap-y-2 sm:grid-cols-2 text-sm border-t borde-suave pt-4">
                    <?php foreach ($valores as $valor) { ?>
                        <div>
                            <dt class="font-semibold texto-suave"><?= $e((string) $valor['etiqueta']) ?></dt>
                            <dd><?= $e((string) $valor['valor']) ?></dd>
                        </div>
                    <?php } ?>
                </dl>
            <?php } ?>

            <?php if ($adjuntos !== []) { ?>
                <h3 class="mt-4 text-sm font-bold texto-titular">Adjuntos del ciudadano</h3>
                <ul class="mt-2 flex flex-col gap-1 text-sm">
                    <?php foreach ($adjuntos as $adjunto) { ?>
                        <li>
                            <a href="/archivos/<?= (int) $adjunto['archivo_id'] ?>"><?= $e((string) $adjunto['nombre_original']) ?></a>
                            <span class="texto-suave">(<?= sprintf('%.1f MB', ((int) $adjunto['tamano']) / 1_048_576) ?>)</span>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
        </section>

        <section class="tarjeta" aria-labelledby="t-solicitante">
            <h2 id="t-solicitante" class="text-base">Solicitante</h2>
            <?php if (((int) $tramite['anonimo']) === 1) { ?>
                <p class="mt-2 texto-suave">Solicitud anónima.</p>
            <?php } else { ?>
                <dl class="mt-3 grid gap-x-8 gap-y-2 sm:grid-cols-2 text-sm">
                    <div><dt class="font-semibold texto-suave">Nombre</dt><dd><?= $e((string) ($tramite['nombre'] ?? '—')) ?></dd></div>
                    <div><dt class="font-semibold texto-suave">Documento</dt><dd><?= $e((string) ($tramite['tipo_documento'] ?? '')) ?> <?= $e((string) ($tramite['numero_documento'] ?? '—')) ?></dd></div>
                    <div><dt class="font-semibold texto-suave">Correo</dt><dd><?= $e((string) ($tramite['correo'] ?? '—')) ?></dd></div>
                    <div><dt class="font-semibold texto-suave">Teléfono</dt><dd><?= $e((string) ($tramite['telefono'] ?? '—')) ?></dd></div>
                    <div><dt class="font-semibold texto-suave">Medio de respuesta</dt><dd><?= $e((string) $tramite['medio_respuesta']) ?></dd></div>
                </dl>
            <?php } ?>
        </section>

        <section aria-labelledby="t-historial">
            <h2 id="t-historial" class="text-lg">Bitácora</h2>
            <ol class="mt-3 flex flex-col gap-3">
                <?php foreach ($seguimientos as $seguimiento) { ?>
                    <li class="tarjeta <?= ((int) $seguimiento['visible_ciudadano']) === 1 ? '' : 'fondo-superficie' ?>">
                        <p class="text-sm texto-suave">
                            <?= $e((string) $seguimiento['creado_en']) ?>
                            · <?= $e((string) ($seguimiento['usuario_nombre'] ?? 'sistema')) ?>
                            · <?= ((int) $seguimiento['visible_ciudadano']) === 1
                                ? '<span class="insignia insignia-exito">Visible al ciudadano</span>'
                                : '<span class="insignia insignia-aviso">Nota interna</span>' ?>
                        </p>
                        <?php if (($seguimiento['estado_nuevo_nombre'] ?? null) !== null) { ?>
                            <p class="mt-1 font-semibold texto-titular">→ <?= $e((string) $seguimiento['estado_nuevo_nombre']) ?></p>
                        <?php } ?>
                        <?php if (($seguimiento['nota'] ?? null) !== null && $seguimiento['nota'] !== '') { ?>
                            <p class="mt-1 text-sm whitespace-pre-line"><?= $e((string) $seguimiento['nota']) ?></p>
                        <?php } ?>
                    </li>
                <?php } ?>
            </ol>
        </section>
    </div>

    <aside class="flex flex-col gap-6">
        <section class="tarjeta" aria-labelledby="t-gestion">
            <h2 id="t-gestion" class="text-base">Gestionar</h2>
            <form class="mt-3" method="post" action="/admin/tramites/<?= (int) $tramite['id'] ?>/estado">
                <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                <label class="etiqueta" for="estado_id">Cambiar estado a</label>
                <select class="campo" id="estado_id" name="estado_id">
                    <?php foreach ($tipo?->estados ?? [] as $estado) { ?>
                        <option value="<?= $estado['id'] ?>" <?= $estado['id'] === (int) $tramite['estado_id'] ? 'selected' : '' ?>>
                            <?= $e($estado['nombre']) ?>
                        </option>
                    <?php } ?>
                </select>
                <label class="etiqueta mt-3" for="nota-estado">Nota (opcional)</label>
                <textarea class="campo" id="nota-estado" name="nota" rows="3"
                          placeholder="Respuesta o comentario para el ciudadano…"></textarea>
                <label class="mt-2 flex items-center gap-2 text-sm">
                    <input type="checkbox" name="visible_ciudadano" value="1" checked>
                    Visible para el ciudadano
                </label>
                <button type="submit" class="boton boton-primario mt-3 w-full">Aplicar</button>
            </form>
        </section>

        <section class="tarjeta" aria-labelledby="t-asignacion">
            <h2 id="t-asignacion" class="text-base">Asignación</h2>
            <form class="mt-3" method="post" action="/admin/tramites/<?= (int) $tramite['id'] ?>/asignar">
                <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                <label class="etiqueta" for="asignado_a">Responsable</label>
                <select class="campo" id="asignado_a" name="asignado_a">
                    <option value="">Sin asignar</option>
                    <?php foreach ($usuarios as $usuario) { ?>
                        <option value="<?= $usuario['id'] ?>" <?= ((int) ($tramite['asignado_a'] ?? 0)) === $usuario['id'] ? 'selected' : '' ?>>
                            <?= $e($usuario['nombre']) ?>
                        </option>
                    <?php } ?>
                </select>
                <label class="etiqueta mt-3" for="dependencia_id">Dependencia</label>
                <select class="campo" id="dependencia_id" name="dependencia_id">
                    <option value="">Sin asignar</option>
                    <?php foreach ($dependencias as $dependencia) { ?>
                        <option value="<?= $dependencia['id'] ?>" <?= ((int) ($tramite['dependencia_id'] ?? 0)) === $dependencia['id'] ? 'selected' : '' ?>>
                            <?= $e($dependencia['nombre']) ?>
                        </option>
                    <?php } ?>
                </select>
                <button type="submit" class="boton boton-secundario mt-3 w-full">Guardar asignación</button>
            </form>
        </section>

        <section class="tarjeta" aria-labelledby="t-nota">
            <h2 id="t-nota" class="text-base">Agregar nota</h2>
            <form class="mt-3" method="post" action="/admin/tramites/<?= (int) $tramite['id'] ?>/nota">
                <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                <label class="sr-only" for="nota-suelta">Nota</label>
                <textarea class="campo" id="nota-suelta" name="nota" rows="3" required></textarea>
                <label class="mt-2 flex items-center gap-2 text-sm">
                    <input type="checkbox" name="visible_ciudadano" value="1">
                    Visible para el ciudadano
                </label>
                <button type="submit" class="boton boton-secundario mt-3 w-full">Agregar</button>
            </form>
        </section>
    </aside>
</div>

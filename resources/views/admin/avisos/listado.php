<?php
/**
 * Avisos del sitio (banner de la home y modo emergencia).
 * Espera: $avisos (list<fila>), $mensaje (?string), $error (?string), $tokenCsrf.
 */

$campos = static function (array $a, string $tokenCsrf) use ($e): string {
    $fechaLocal = static fn (?string $f): string => $f === null ? '' : str_replace(' ', 'T', substr($f, 0, 16));
    $sel = static fn (string $actual, string $valor): string => $actual === $valor ? 'selected' : '';

    ob_start(); ?>
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="etiqueta" for="titulo-<?= $a['id'] ?>">Título</label>
                <input class="campo" id="titulo-<?= $a['id'] ?>" type="text" name="titulo" required maxlength="200" value="<?= $e((string) $a['titulo']) ?>">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="etiqueta" for="tipo-<?= $a['id'] ?>">Tipo</label>
                    <select class="campo" id="tipo-<?= $a['id'] ?>" name="tipo">
                        <option value="informativo" <?= $sel((string) $a['tipo'], 'informativo') ?>>Informativo</option>
                        <option value="urgencia" <?= $sel((string) $a['tipo'], 'urgencia') ?>>Urgencias</option>
                        <option value="campana" <?= $sel((string) $a['tipo'], 'campana') ?>>Campaña</option>
                        <option value="emergencia" <?= $sel((string) $a['tipo'], 'emergencia') ?>>Emergencia</option>
                    </select>
                </div>
                <div>
                    <label class="etiqueta" for="nivel-<?= $a['id'] ?>">Nivel</label>
                    <select class="campo" id="nivel-<?= $a['id'] ?>" name="nivel">
                        <option value="info" <?= $sel((string) $a['nivel'], 'info') ?>>Información</option>
                        <option value="alerta" <?= $sel((string) $a['nivel'], 'alerta') ?>>Alerta</option>
                        <option value="critico" <?= $sel((string) $a['nivel'], 'critico') ?>>CRÍTICO (modo emergencia)</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="mt-4">
            <label class="etiqueta" for="mensaje-<?= $a['id'] ?>">Mensaje</label>
            <textarea class="campo" id="mensaje-<?= $a['id'] ?>" name="mensaje" rows="2" required maxlength="1000"><?= $e((string) $a['mensaje']) ?></textarea>
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="etiqueta" for="url-<?= $a['id'] ?>">Enlace (opcional)</label>
                <input class="campo" id="url-<?= $a['id'] ?>" type="text" name="url" maxlength="500" value="<?= $e((string) ($a['url'] ?? '')) ?>">
            </div>
            <div>
                <label class="etiqueta" for="texto_boton-<?= $a['id'] ?>">Texto del botón (opcional)</label>
                <input class="campo" id="texto_boton-<?= $a['id'] ?>" type="text" name="texto_boton" maxlength="80" value="<?= $e((string) ($a['texto_boton'] ?? '')) ?>">
            </div>
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-4 sm:items-end">
            <div>
                <label class="etiqueta" for="desde-<?= $a['id'] ?>">Mostrar desde</label>
                <input class="campo" id="desde-<?= $a['id'] ?>" type="datetime-local" name="desde" value="<?= $e($fechaLocal($a['desde'] ?? null)) ?>">
            </div>
            <div>
                <label class="etiqueta" for="hasta-<?= $a['id'] ?>">Hasta</label>
                <input class="campo" id="hasta-<?= $a['id'] ?>" type="datetime-local" name="hasta" value="<?= $e($fechaLocal($a['hasta'] ?? null)) ?>">
            </div>
            <div>
                <label class="etiqueta" for="prioridad-<?= $a['id'] ?>">Prioridad</label>
                <input class="campo" id="prioridad-<?= $a['id'] ?>" type="number" name="prioridad" value="<?= (int) $a['prioridad'] ?>">
            </div>
            <label class="flex items-center gap-2 pb-2">
                <input type="checkbox" name="activo" value="1" <?= ((int) $a['activo']) === 1 ? 'checked' : '' ?>>
                <span class="font-semibold">Activo</span>
            </label>
        </div>
    <?php return (string) ob_get_clean();
};
?>
<h1 class="text-2xl">Avisos</h1>
<p class="mt-2 texto-suave">
    El aviso activo de mayor prioridad se muestra en todo el sitio. Un aviso
    de nivel <strong>crítico</strong> activo pone la portada en modo
    emergencia: úsalo solo cuando de verdad lo sea.
</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>

<details class="tarjeta mt-6">
    <summary class="cursor-pointer font-semibold texto-titular">Crear aviso</summary>
    <form class="mt-4" method="post" action="/admin/avisos">
        <?= $campos([
            'id' => 'nuevo', 'tipo' => 'informativo', 'nivel' => 'info', 'titulo' => '',
            'mensaje' => '', 'url' => null, 'texto_boton' => null, 'desde' => null,
            'hasta' => null, 'activo' => 0, 'prioridad' => 0,
        ], $tokenCsrf) ?>
        <p class="mt-4"><button type="submit" class="boton boton-primario">Crear aviso</button></p>
    </form>
</details>

<ul class="mt-6 flex flex-col gap-4">
    <?php foreach ($avisos as $aviso) { ?>
        <li class="tarjeta">
            <div class="flex flex-wrap items-center gap-3">
                <strong class="texto-titular"><?= $e((string) $aviso['titulo']) ?></strong>
                <?php if (((int) $aviso['activo']) === 1) { ?>
                    <span class="insignia <?= $aviso['nivel'] === 'critico' ? 'insignia-error' : 'insignia-exito' ?>">
                        <?= $aviso['nivel'] === 'critico' ? 'ACTIVO · EMERGENCIA' : 'Activo' ?>
                    </span>
                <?php } else { ?>
                    <span class="insignia insignia-aviso">Inactivo</span>
                <?php } ?>
                <span class="text-sm texto-suave">nivel <?= $e((string) $aviso['nivel']) ?> · prioridad <?= (int) $aviso['prioridad'] ?></span>
            </div>
            <details class="mt-3">
                <summary class="cursor-pointer text-sm font-semibold texto-titular">Editar</summary>
                <form class="mt-4" method="post" action="/admin/avisos/<?= (int) $aviso['id'] ?>">
                    <?= $campos($aviso, $tokenCsrf) ?>
                    <p class="mt-4"><button type="submit" class="boton boton-primario">Guardar cambios</button></p>
                </form>
            </details>
        </li>
    <?php } ?>
</ul>

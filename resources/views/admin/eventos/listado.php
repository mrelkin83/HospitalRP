<?php
/**
 * Panel · Calendario de eventos.
 * Espera: $eventos, $tipos, $mensaje, $error, $tokenCsrf.
 */
$etiqueta = [
    'institucional' => 'Institucional',
    'participacion_ciudadana' => 'Participación ciudadana',
    'rendicion_cuentas' => 'Rendición de cuentas',
    'capacitacion' => 'Capacitación',
    'jornada_salud' => 'Jornada de salud',
    'otro' => 'Otro',
];
$paraInput = static fn (?string $f): string => $f === null ? '' : date('Y-m-d\TH:i', (int) strtotime($f));

$campos = static function (array $f, array $tipos, string $tokenCsrf) use ($e, $etiqueta, $paraInput): string {
    $id = (string) ($f['id'] ?? 'nuevo');
    ob_start(); ?>
    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
    <div>
        <label class="etiqueta" for="titulo-<?= $e($id) ?>">Título</label>
        <input class="campo" id="titulo-<?= $e($id) ?>" type="text" name="titulo" required maxlength="300"
               value="<?= $e((string) ($f['titulo'] ?? '')) ?>">
    </div>
    <div class="mt-3">
        <label class="etiqueta" for="descripcion-<?= $e($id) ?>">Descripción</label>
        <textarea class="campo" id="descripcion-<?= $e($id) ?>" name="descripcion" rows="3"><?= $e((string) ($f['descripcion'] ?? '')) ?></textarea>
    </div>
    <div class="mt-3 grid gap-4 sm:grid-cols-3">
        <div>
            <label class="etiqueta" for="inicio-<?= $e($id) ?>">Inicio</label>
            <input class="campo" id="inicio-<?= $e($id) ?>" type="datetime-local" name="inicio" required
                   value="<?= $e($paraInput($f['inicio'] ?? null)) ?>">
        </div>
        <div>
            <label class="etiqueta" for="fin-<?= $e($id) ?>">Fin (opcional)</label>
            <input class="campo" id="fin-<?= $e($id) ?>" type="datetime-local" name="fin"
                   value="<?= $e($paraInput($f['fin'] ?? null)) ?>">
        </div>
        <div>
            <label class="etiqueta" for="tipo-<?= $e($id) ?>">Tipo</label>
            <select class="campo" id="tipo-<?= $e($id) ?>" name="tipo">
                <?php foreach ($tipos as $t) { ?>
                    <option value="<?= $e($t) ?>" <?= ($f['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= $e($etiqueta[$t] ?? $t) ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
    <div class="mt-3 grid gap-4 sm:grid-cols-2">
        <div>
            <label class="etiqueta" for="lugar-<?= $e($id) ?>">Lugar</label>
            <input class="campo" id="lugar-<?= $e($id) ?>" type="text" name="lugar" maxlength="300"
                   value="<?= $e((string) ($f['lugar'] ?? '')) ?>">
        </div>
        <div>
            <label class="etiqueta" for="url-<?= $e($id) ?>">Enlace externo</label>
            <input class="campo" id="url-<?= $e($id) ?>" type="url" name="url_externa" maxlength="500"
                   value="<?= $e((string) ($f['url_externa'] ?? '')) ?>">
        </div>
    </div>
    <div class="mt-3 flex flex-wrap gap-6">
        <label class="flex items-center gap-2">
            <input type="checkbox" name="todo_el_dia" value="1" <?= ((int) ($f['todo_el_dia'] ?? 0)) === 1 ? 'checked' : '' ?>>
            <span class="font-semibold">Todo el día</span>
        </label>
        <label class="flex items-center gap-2">
            <input type="checkbox" name="publicado" value="1" <?= ((int) ($f['publicado'] ?? 0)) === 1 ? 'checked' : '' ?>>
            <span class="font-semibold">Publicado</span>
        </label>
    </div>
    <?php return (string) ob_get_clean();
};
?>
<h1 class="text-2xl">Calendario de eventos</h1>
<p class="mt-2 text-sm texto-suave">
    Agenda institucional. Cubre los ítems 1.12 y 6.5 del índice de transparencia.
    Un evento que termina antes de empezar no se guarda: desaparecería del calendario.
</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>

<details class="tarjeta mt-6">
    <summary class="cursor-pointer font-semibold texto-titular">Crear evento</summary>
    <form class="mt-4" method="post" action="/admin/eventos">
        <?= $campos(['id' => 'nuevo', 'tipo' => 'institucional'], $tipos, $tokenCsrf) ?>
        <p class="mt-4"><button type="submit" class="boton boton-primario">Crear</button></p>
    </form>
</details>

<ul class="mt-6 flex flex-col gap-4">
    <?php foreach ($eventos as $fila) { ?>
        <li class="tarjeta">
            <div class="flex flex-wrap items-center gap-3">
                <strong class="texto-titular min-w-0 flex-1"><?= $e((string) $fila['titulo']) ?></strong>
                <span class="insignia <?= ((int) $fila['publicado']) === 1 ? 'insignia-exito' : 'insignia-aviso' ?>">
                    <?= ((int) $fila['publicado']) === 1 ? 'Publicado' : 'Oculto' ?>
                </span>
                <time class="text-sm texto-suave" datetime="<?= $e(date('Y-m-d', (int) strtotime((string) $fila['inicio']))) ?>">
                    <?= $e(date('d/m/Y H:i', (int) strtotime((string) $fila['inicio']))) ?>
                </time>
            </div>
            <details class="mt-3">
                <summary class="cursor-pointer text-sm font-semibold texto-titular">Editar</summary>
                <form class="mt-4" method="post" action="/admin/eventos/<?= (int) $fila['id'] ?>">
                    <?= $campos($fila, $tipos, $tokenCsrf) ?>
                    <p class="mt-4"><button type="submit" class="boton boton-primario">Guardar cambios</button></p>
                </form>
                <form class="mt-2" method="post" action="/admin/eventos/<?= (int) $fila['id'] ?>/eliminar">
                    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                    <button type="submit" class="text-sm text-error-600 underline">Eliminar evento</button>
                </form>
            </details>
        </li>
    <?php } ?>
</ul>

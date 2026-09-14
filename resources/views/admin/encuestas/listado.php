<?php
/**
 * Panel · Encuestas de satisfacción.
 * Espera: $encuestas, $estados, $mensaje, $error, $tokenCsrf.
 */
$etiqueta = ['borrador' => 'Borrador', 'abierta' => 'Abierta', 'cerrada' => 'Cerrada'];
$paraInput = static fn (?string $f): string => $f === null ? '' : date('Y-m-d\TH:i', (int) strtotime($f));

$campos = static function (array $f, array $estados, string $tokenCsrf) use ($e, $etiqueta, $paraInput): string {
    $id = (string) ($f['id'] ?? 'nueva');
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
    <div class="mt-3 grid gap-4 sm:grid-cols-2">
        <div>
            <label class="etiqueta" for="estado-<?= $e($id) ?>">Estado</label>
            <select class="campo" id="estado-<?= $e($id) ?>" name="estado">
                <?php foreach ($estados as $s) { ?>
                    <option value="<?= $e($s) ?>" <?= ($f['estado'] ?? '') === $s ? 'selected' : '' ?>><?= $e($etiqueta[$s] ?? $s) ?></option>
                <?php } ?>
            </select>
        </div>
        <div>
            <label class="etiqueta" for="cierre-<?= $e($id) ?>">Cierra el (opcional)</label>
            <input class="campo" id="cierre-<?= $e($id) ?>" type="datetime-local" name="cierre"
                   value="<?= $e($paraInput($f['cierre'] ?? null)) ?>">
        </div>
    </div>
    <?php return (string) ob_get_clean();
};
?>
<h1 class="text-2xl">Encuestas de satisfacción</h1>
<div class="alerta mt-4">
    <p class="text-sm">
        Las respuestas son <strong>anónimas</strong>: no se guarda quién respondió y
        no hay forma de averiguarlo. Los resultados se leen agregados. Al redactar
        preguntas abiertas, <strong>no pidas datos personales ni información de salud</strong>.
    </p>
</div>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>

<details class="tarjeta mt-6">
    <summary class="cursor-pointer font-semibold texto-titular">Crear encuesta</summary>
    <form class="mt-4" method="post" action="/admin/encuestas">
        <?= $campos(['id' => 'nueva', 'estado' => 'borrador'], $estados, $tokenCsrf) ?>
        <p class="mt-4"><button type="submit" class="boton boton-primario">Crear</button></p>
    </form>
</details>

<ul class="mt-6 flex flex-col gap-4">
    <?php foreach ($encuestas as $fila) { ?>
        <li class="tarjeta">
            <div class="flex flex-wrap items-center gap-3">
                <strong class="texto-titular min-w-0 flex-1">
                    <a href="/admin/encuestas/<?= (int) $fila['id'] ?>"><?= $e((string) $fila['titulo']) ?></a>
                </strong>
                <span class="insignia <?= $fila['estado'] === 'abierta' ? 'insignia-exito' : 'insignia-aviso' ?>">
                    <?= $e($etiqueta[$fila['estado']] ?? (string) $fila['estado']) ?>
                </span>
                <span class="text-sm texto-suave"><?= (int) $fila['respuestas'] ?> respuestas</span>
            </div>
            <details class="mt-3">
                <summary class="cursor-pointer text-sm font-semibold texto-titular">Editar</summary>
                <form class="mt-4" method="post" action="/admin/encuestas/<?= (int) $fila['id'] ?>">
                    <?= $campos($fila, $estados, $tokenCsrf) ?>
                    <p class="mt-4"><button type="submit" class="boton boton-primario">Guardar cambios</button></p>
                </form>
            </details>
            <p class="mt-3 text-sm">
                <a href="/admin/encuestas/<?= (int) $fila['id'] ?>">Preguntas y resultados &rarr;</a>
            </p>
        </li>
    <?php } ?>
</ul>

<?php
/**
 * Especialidades médicas.
 * Espera: $especialidades (list<Especialidad>), $sedes (list<Sede>),
 *         $mensaje (?string), $error (?string), $tokenCsrf.
 */

use App\Modules\Directorio\Entity\Especialidad;

$campos = static function (?Especialidad $esp, string $clave, array $sedes, string $tokenCsrf) use ($e): string {
    ob_start(); ?>
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
        <div class="grid gap-4 sm:grid-cols-[2fr_auto_auto] sm:items-end">
            <div>
                <label class="etiqueta" for="nombre-<?= $clave ?>">Nombre</label>
                <input class="campo" id="nombre-<?= $clave ?>" type="text" name="nombre" required maxlength="160" value="<?= $e($esp?->nombre ?? '') ?>">
            </div>
            <div>
                <label class="etiqueta" for="orden-<?= $clave ?>">Orden</label>
                <input class="campo w-24" id="orden-<?= $clave ?>" type="number" name="orden" value="<?= $esp?->orden ?? 0 ?>">
            </div>
            <label class="flex items-center gap-2 pb-2">
                <input type="checkbox" name="activa" value="1" <?= ($esp?->activa ?? true) ? 'checked' : '' ?>>
                <span class="font-semibold">Activa</span>
            </label>
        </div>
        <div class="mt-4">
            <label class="etiqueta" for="descripcion-<?= $clave ?>">Descripción (opcional)</label>
            <textarea class="campo" id="descripcion-<?= $clave ?>" name="descripcion" rows="2"><?= $e($esp?->descripcion ?? '') ?></textarea>
        </div>
        <fieldset class="mt-4">
            <legend class="etiqueta">Sedes donde se presta</legend>
            <div class="flex flex-wrap gap-4">
                <?php foreach ($sedes as $sede) { ?>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="sedes[]" value="<?= $sede->id ?>"
                               <?= in_array($sede->id, $esp?->sedeIds ?? [], true) ? 'checked' : '' ?>>
                        <?= $e($sede->nombre) ?>
                    </label>
                <?php } ?>
                <?php if ($sedes === []) { ?>
                    <p class="text-sm texto-suave">Primero crea las sedes en su sección.</p>
                <?php } ?>
            </div>
        </fieldset>
    <?php return (string) ob_get_clean();
};
?>
<h1 class="text-2xl">Especialidades</h1>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>

<details class="tarjeta mt-6">
    <summary class="cursor-pointer font-semibold texto-titular">Crear especialidad</summary>
    <form class="mt-4" method="post" action="/admin/especialidades">
        <?= $campos(null, 'nueva', $sedes, $tokenCsrf) ?>
        <p class="mt-4"><button type="submit" class="boton boton-primario">Crear</button></p>
    </form>
</details>

<ul class="mt-6 flex flex-col gap-4">
    <?php foreach ($especialidades as $especialidad) { ?>
        <li class="tarjeta">
            <div class="flex flex-wrap items-center gap-3">
                <strong class="texto-titular"><?= $e($especialidad->nombre) ?></strong>
                <span class="text-sm texto-suave"><?= count($especialidad->sedeIds) ?> sede(s)</span>
                <?php if (!$especialidad->activa) { ?>
                    <span class="insignia insignia-aviso">Inactiva</span>
                <?php } ?>
            </div>
            <details class="mt-3">
                <summary class="cursor-pointer text-sm font-semibold texto-titular">Editar</summary>
                <form class="mt-4" method="post" action="/admin/especialidades/<?= $especialidad->id ?>">
                    <?= $campos($especialidad, (string) $especialidad->id, $sedes, $tokenCsrf) ?>
                    <p class="mt-4"><button type="submit" class="boton boton-primario">Guardar cambios</button></p>
                </form>
            </details>
        </li>
    <?php } ?>
</ul>

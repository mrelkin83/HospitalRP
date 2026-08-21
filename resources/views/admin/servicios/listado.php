<?php
/**
 * Servicios asistenciales.
 * Espera: $servicios (list<Servicio>), $sedes (list<Sede>),
 *         $mensaje (?string), $error (?string), $tokenCsrf.
 */

use App\Modules\Directorio\Entity\Servicio;

$campos = static function (?Servicio $srv, string $clave, array $sedes, string $tokenCsrf) use ($e): string {
    ob_start(); ?>
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
        <div class="grid gap-4 sm:grid-cols-[2fr_1fr_auto_auto] sm:items-end">
            <div>
                <label class="etiqueta" for="nombre-<?= $clave ?>">Nombre</label>
                <input class="campo" id="nombre-<?= $clave ?>" type="text" name="nombre" required maxlength="160" value="<?= $e($srv?->nombre ?? '') ?>">
            </div>
            <div>
                <label class="etiqueta" for="telefono-<?= $clave ?>">Teléfono / extensión</label>
                <input class="campo" id="telefono-<?= $clave ?>" type="text" name="telefono" maxlength="60" value="<?= $e($srv?->telefono ?? '') ?>">
            </div>
            <div>
                <label class="etiqueta" for="orden-<?= $clave ?>">Orden</label>
                <input class="campo w-24" id="orden-<?= $clave ?>" type="number" name="orden" value="<?= $srv?->orden ?? 0 ?>">
            </div>
            <label class="flex items-center gap-2 pb-2">
                <input type="checkbox" name="activo" value="1" <?= ($srv?->activo ?? true) ? 'checked' : '' ?>>
                <span class="font-semibold">Activo</span>
            </label>
        </div>
        <div class="mt-4">
            <label class="etiqueta" for="descripcion-<?= $clave ?>">Descripción</label>
            <textarea class="campo" id="descripcion-<?= $clave ?>" name="descripcion" rows="2"><?= $e($srv?->descripcion ?? '') ?></textarea>
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="etiqueta" for="requisitos-<?= $clave ?>">Requisitos (qué debe traer el usuario)</label>
                <textarea class="campo" id="requisitos-<?= $clave ?>" name="requisitos" rows="2"><?= $e($srv?->requisitos ?? '') ?></textarea>
            </div>
            <div>
                <label class="etiqueta" for="horario-<?= $clave ?>">Horario de atención</label>
                <textarea class="campo" id="horario-<?= $clave ?>" name="horario" rows="2" maxlength="500"><?= $e($srv?->horario ?? '') ?></textarea>
            </div>
        </div>
        <fieldset class="mt-4">
            <legend class="etiqueta">Sedes donde se presta</legend>
            <div class="flex flex-wrap gap-4">
                <?php foreach ($sedes as $sede) { ?>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="sedes[]" value="<?= $sede->id ?>"
                               <?= in_array($sede->id, $srv?->sedeIds ?? [], true) ? 'checked' : '' ?>>
                        <?= $e($sede->nombre) ?>
                    </label>
                <?php } ?>
            </div>
        </fieldset>
    <?php return (string) ob_get_clean();
};
?>
<h1 class="text-2xl">Servicios</h1>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>

<details class="tarjeta mt-6">
    <summary class="cursor-pointer font-semibold texto-titular">Crear servicio</summary>
    <form class="mt-4" method="post" action="/admin/servicios">
        <?= $campos(null, 'nuevo', $sedes, $tokenCsrf) ?>
        <p class="mt-4"><button type="submit" class="boton boton-primario">Crear</button></p>
    </form>
</details>

<ul class="mt-6 flex flex-col gap-4">
    <?php foreach ($servicios as $servicio) { ?>
        <li class="tarjeta">
            <div class="flex flex-wrap items-center gap-3">
                <strong class="texto-titular"><?= $e($servicio->nombre) ?></strong>
                <span class="text-sm texto-suave"><?= count($servicio->sedeIds) ?> sede(s)</span>
                <?php if (!$servicio->activo) { ?>
                    <span class="insignia insignia-aviso">Inactivo</span>
                <?php } ?>
            </div>
            <details class="mt-3">
                <summary class="cursor-pointer text-sm font-semibold texto-titular">Editar</summary>
                <form class="mt-4" method="post" action="/admin/servicios/<?= $servicio->id ?>">
                    <?= $campos($servicio, (string) $servicio->id, $sedes, $tokenCsrf) ?>
                    <p class="mt-4"><button type="submit" class="boton boton-primario">Guardar cambios</button></p>
                </form>
            </details>
        </li>
    <?php } ?>
</ul>

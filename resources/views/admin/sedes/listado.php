<?php
/**
 * Sedes del hospital.
 * Espera: $sedes (list<Sede>), $mensaje (?string), $error (?string), $tokenCsrf.
 */

use App\Modules\Directorio\Entity\Sede;

$campos = static function (?Sede $s, string $clave, string $tokenCsrf) use ($e): string {
    ob_start(); ?>
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="etiqueta" for="nombre-<?= $clave ?>">Nombre</label>
                <input class="campo" id="nombre-<?= $clave ?>" type="text" name="nombre" required maxlength="160" value="<?= $e($s?->nombre ?? '') ?>">
            </div>
            <div>
                <label class="etiqueta" for="telefono-<?= $clave ?>">Teléfono</label>
                <input class="campo" id="telefono-<?= $clave ?>" type="text" name="telefono" maxlength="60" value="<?= $e($s?->telefono ?? '') ?>">
            </div>
            <div>
                <label class="etiqueta" for="direccion-<?= $clave ?>">Dirección</label>
                <input class="campo" id="direccion-<?= $clave ?>" type="text" name="direccion" required maxlength="255" value="<?= $e($s?->direccion ?? '') ?>">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="etiqueta" for="municipio-<?= $clave ?>">Municipio</label>
                    <input class="campo" id="municipio-<?= $clave ?>" type="text" name="municipio" required maxlength="120" value="<?= $e($s?->municipio ?? 'Valledupar') ?>">
                </div>
                <div>
                    <label class="etiqueta" for="departamento-<?= $clave ?>">Departamento</label>
                    <input class="campo" id="departamento-<?= $clave ?>" type="text" name="departamento" maxlength="120" value="<?= $e($s?->departamento ?? 'Cesar') ?>">
                </div>
            </div>
        </div>
        <div class="mt-4">
            <label class="etiqueta" for="descripcion-<?= $clave ?>">Descripción (opcional)</label>
            <textarea class="campo" id="descripcion-<?= $clave ?>" name="descripcion" rows="2"><?= $e($s?->descripcion ?? '') ?></textarea>
        </div>
        <div class="mt-4">
            <label class="etiqueta" for="horario-<?= $clave ?>">Horario (una línea por franja)</label>
            <textarea class="campo" id="horario-<?= $clave ?>" name="horario" rows="3"
                      placeholder="Lunes a viernes: 7:00 a. m. – 5:00 p. m."><?= $e(implode("\n", $s?->horario ?? [])) ?></textarea>
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-4 sm:items-end">
            <div>
                <label class="etiqueta" for="correo-<?= $clave ?>">Correo</label>
                <input class="campo" id="correo-<?= $clave ?>" type="email" name="correo" maxlength="160" value="<?= $e($s?->correo ?? '') ?>">
            </div>
            <div>
                <label class="etiqueta" for="latitud-<?= $clave ?>">Latitud</label>
                <input class="campo" id="latitud-<?= $clave ?>" type="text" name="latitud" value="<?= $e($s?->latitud ?? '') ?>" placeholder="10.4631">
            </div>
            <div>
                <label class="etiqueta" for="longitud-<?= $clave ?>">Longitud</label>
                <input class="campo" id="longitud-<?= $clave ?>" type="text" name="longitud" value="<?= $e($s?->longitud ?? '') ?>" placeholder="-73.2532">
            </div>
            <div>
                <label class="etiqueta" for="orden-<?= $clave ?>">Orden</label>
                <input class="campo w-24" id="orden-<?= $clave ?>" type="number" name="orden" value="<?= $s?->orden ?? 0 ?>">
            </div>
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 sm:items-end">
            <div>
                <label class="etiqueta" for="imagen-<?= $clave ?>">Foto de la sede (opcional)</label>
                <input class="campo" id="imagen-<?= $clave ?>" type="file" name="imagen" accept="image/jpeg,image/png,image/webp,image/avif">
                <label class="etiqueta mt-2" for="imagen_alt-<?= $clave ?>">Descripción de la foto</label>
                <input class="campo" id="imagen_alt-<?= $clave ?>" type="text" name="imagen_alt" maxlength="255"
                       placeholder="Fachada de la sede principal">
            </div>
            <div class="flex flex-wrap gap-6 pb-2">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="tiene_urgencias" value="1" <?= ($s?->tieneUrgencias ?? false) ? 'checked' : '' ?>>
                    <span class="font-semibold">Tiene urgencias 24/7</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="activa" value="1" <?= ($s?->activa ?? true) ? 'checked' : '' ?>>
                    <span class="font-semibold">Activa</span>
                </label>
            </div>
        </div>
    <?php return (string) ob_get_clean();
};
?>
<h1 class="text-2xl">Sedes</h1>
<p class="mt-2 texto-suave">Direcciones, teléfonos y horarios que ve el ciudadano. La bandera de urgencias alimenta la portada.</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>

<details class="tarjeta mt-6">
    <summary class="cursor-pointer font-semibold texto-titular">Crear sede</summary>
    <form class="mt-4" method="post" action="/admin/sedes" enctype="multipart/form-data">
        <?= $campos(null, 'nueva', $tokenCsrf) ?>
        <p class="mt-4"><button type="submit" class="boton boton-primario">Crear sede</button></p>
    </form>
</details>

<ul class="mt-6 flex flex-col gap-4">
    <?php foreach ($sedes as $sede) { ?>
        <li class="tarjeta">
            <div class="flex flex-wrap items-center gap-3">
                <strong class="texto-titular"><?= $e($sede->nombre) ?></strong>
                <span class="text-sm texto-suave"><?= $e($sede->direccion) ?>, <?= $e($sede->municipio) ?></span>
                <?php if ($sede->tieneUrgencias) { ?>
                    <span class="insignia insignia-exito">Urgencias 24/7</span>
                <?php } ?>
                <?php if (!$sede->activa) { ?>
                    <span class="insignia insignia-aviso">Inactiva</span>
                <?php } ?>
            </div>
            <details class="mt-3">
                <summary class="cursor-pointer text-sm font-semibold texto-titular">Editar</summary>
                <form class="mt-4" method="post" action="/admin/sedes/<?= $sede->id ?>" enctype="multipart/form-data">
                    <?= $campos($sede, (string) $sede->id, $tokenCsrf) ?>
                    <p class="mt-4"><button type="submit" class="boton boton-primario">Guardar cambios</button></p>
                </form>
            </details>
        </li>
    <?php } ?>
</ul>

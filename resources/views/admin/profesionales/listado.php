<?php
/**
 * Directorio médico (panel).
 * Espera: $profesionales (list<Profesional>), $especialidades (list<Especialidad>),
 *         $mensaje (?string), $error (?string), $tokenCsrf.
 */

use App\Modules\Directorio\Entity\Profesional;

$campos = static function (?Profesional $p, string $clave, array $especialidades, string $tokenCsrf) use ($e): string {
    ob_start(); ?>
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
        <div class="grid gap-4 sm:grid-cols-[2fr_1fr_auto] sm:items-end">
            <div>
                <label class="etiqueta" for="nombre-<?= $clave ?>">Nombre completo</label>
                <input class="campo" id="nombre-<?= $clave ?>" type="text" name="nombre" required maxlength="200" value="<?= $e($p?->nombre ?? '') ?>">
            </div>
            <div>
                <label class="etiqueta" for="rethus-<?= $clave ?>">Registro ReTHUS (opcional)</label>
                <input class="campo" id="rethus-<?= $clave ?>" type="text" name="registro_rethus" maxlength="60" value="<?= $e($p?->registroRethus ?? '') ?>">
            </div>
            <div>
                <label class="etiqueta" for="orden-<?= $clave ?>">Orden</label>
                <input class="campo w-24" id="orden-<?= $clave ?>" type="number" name="orden" value="<?= $p?->orden ?? 0 ?>">
            </div>
        </div>
        <div class="mt-4">
            <label class="etiqueta" for="perfil-<?= $clave ?>">Perfil profesional (público)</label>
            <textarea class="campo" id="perfil-<?= $clave ?>" name="perfil" rows="3"
                      placeholder="Formación, experiencia y áreas de interés. Nunca teléfonos ni correos personales."><?= $e($p?->perfil ?? '') ?></textarea>
        </div>
        <fieldset class="mt-4">
            <legend class="etiqueta">Especialidades</legend>
            <div class="flex flex-wrap gap-4">
                <?php foreach ($especialidades as $esp) { ?>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="especialidades[]" value="<?= $esp->id ?>"
                               <?= in_array($esp->id, $p?->especialidadIds ?? [], true) ? 'checked' : '' ?>>
                        <?= $e($esp->nombre) ?>
                    </label>
                <?php } ?>
            </div>
        </fieldset>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 sm:items-end">
            <div>
                <label class="etiqueta" for="foto-<?= $clave ?>">Foto (opcional)</label>
                <input class="campo" id="foto-<?= $clave ?>" type="file" name="foto" accept="image/jpeg,image/png,image/webp,image/avif">
            </div>
            <div class="flex flex-wrap gap-6 pb-2">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="autoriza_publicacion" value="1" <?= ($p?->autorizaPublicacion ?? false) ? 'checked' : '' ?>>
                    <span class="font-semibold">Autorizó la publicación de sus datos</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="activo" value="1" <?= ($p?->activo ?? true) ? 'checked' : '' ?>>
                    <span class="font-semibold">Activo</span>
                </label>
            </div>
        </div>
    <?php return (string) ob_get_clean();
};
?>
<h1 class="text-2xl">Directorio médico</h1>
<p class="mt-2 texto-suave">
    Un profesional solo aparece en el sitio público si marcó la casilla de
    autorización <strong>y</strong> está activo. Nunca registres aquí teléfonos
    ni correos personales.
</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>

<details class="tarjeta mt-6">
    <summary class="cursor-pointer font-semibold texto-titular">Agregar profesional</summary>
    <form class="mt-4" method="post" action="/admin/profesionales" enctype="multipart/form-data">
        <?= $campos(null, 'nuevo', $especialidades, $tokenCsrf) ?>
        <p class="mt-4"><button type="submit" class="boton boton-primario">Agregar</button></p>
    </form>
</details>

<ul class="mt-6 flex flex-col gap-4">
    <?php foreach ($profesionales as $profesional) { ?>
        <li class="tarjeta">
            <div class="flex flex-wrap items-center gap-3">
                <strong class="texto-titular"><?= $e($profesional->nombre) ?></strong>
                <span class="text-sm texto-suave"><?= $e(implode(' · ', $profesional->especialidadNombres)) ?></span>
                <?php if ($profesional->autorizaPublicacion && $profesional->activo) { ?>
                    <span class="insignia insignia-exito">Publicado</span>
                <?php } else { ?>
                    <span class="insignia insignia-aviso"><?= $profesional->autorizaPublicacion ? 'Inactivo' : 'Sin autorización' ?></span>
                <?php } ?>
            </div>
            <details class="mt-3">
                <summary class="cursor-pointer text-sm font-semibold texto-titular">Editar</summary>
                <form class="mt-4" method="post" action="/admin/profesionales/<?= $profesional->id ?>" enctype="multipart/form-data">
                    <?= $campos($profesional, (string) $profesional->id, $especialidades, $tokenCsrf) ?>
                    <p class="mt-4"><button type="submit" class="boton boton-primario">Guardar cambios</button></p>
                </form>
            </details>
        </li>
    <?php } ?>
</ul>

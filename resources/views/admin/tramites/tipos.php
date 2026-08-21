<?php
/**
 * Tipos de trámite configurables.
 * Espera: $tipos (list<TipoTramite completos>), $dependencias, $mensaje, $error, $tokenCsrf.
 */

use App\Modules\Tramites\Entity\TipoTramite;

$campos = static function (?TipoTramite $t, string $clave, array $dependencias, string $tokenCsrf) use ($e): string {
    ob_start(); ?>
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="etiqueta" for="nombre-<?= $clave ?>">Nombre</label>
                <input class="campo" id="nombre-<?= $clave ?>" type="text" name="nombre" required maxlength="200" value="<?= $e($t?->nombre ?? '') ?>">
            </div>
            <div>
                <label class="etiqueta" for="dep-<?= $clave ?>">Dependencia que responde</label>
                <select class="campo" id="dep-<?= $clave ?>" name="dependencia_id">
                    <option value="">Sin asignar</option>
                    <?php foreach ($dependencias as $dependencia) { ?>
                        <option value="<?= $dependencia['id'] ?>" <?= ($t?->dependenciaId ?? 0) === $dependencia['id'] ? 'selected' : '' ?>>
                            <?= $e($dependencia['nombre']) ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="mt-3">
            <label class="etiqueta" for="descripcion-<?= $clave ?>">Descripción (la ve el ciudadano)</label>
            <textarea class="campo" id="descripcion-<?= $clave ?>" name="descripcion" rows="2"><?= $e($t?->descripcion ?? '') ?></textarea>
        </div>
        <div class="mt-3 grid gap-4 sm:grid-cols-4 sm:items-end">
            <div>
                <label class="etiqueta" for="dias-<?= $clave ?>">Término (días)</label>
                <input class="campo" id="dias-<?= $clave ?>" type="number" name="dias_termino" min="1" required value="<?= $t?->diasTermino ?? 15 ?>">
            </div>
            <div>
                <label class="etiqueta" for="tdias-<?= $clave ?>">Tipo de días</label>
                <select class="campo" id="tdias-<?= $clave ?>" name="tipo_dias">
                    <option value="habiles" <?= ($t === null || $t->diasHabiles) ? 'selected' : '' ?>>Hábiles</option>
                    <option value="calendario" <?= ($t !== null && !$t->diasHabiles) ? 'selected' : '' ?>>Calendario</option>
                </select>
            </div>
            <div>
                <label class="etiqueta" for="maxadj-<?= $clave ?>">Máx. adjuntos</label>
                <input class="campo" id="maxadj-<?= $clave ?>" type="number" name="max_adjuntos" min="1" max="10" value="<?= $t?->maxAdjuntos ?? 3 ?>">
            </div>
            <div class="flex flex-col gap-1 pb-1 text-sm">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="permite_anonimo" value="1" <?= ($t?->permiteAnonimo ?? false) ? 'checked' : '' ?>>
                    Permite anónimo
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="requiere_adjunto" value="1" <?= ($t?->requiereAdjunto ?? false) ? 'checked' : '' ?>>
                    Exige adjunto
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="activo" value="1" <?= ($t?->activo ?? true) ? 'checked' : '' ?>>
                    <strong>Activo</strong>
                </label>
            </div>
        </div>
        <div class="mt-3 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="etiqueta" for="fund-<?= $clave ?>">Fundamento legal</label>
                <input class="campo" id="fund-<?= $clave ?>" type="text" name="fundamento_legal" maxlength="255"
                       value="<?= $e($t?->fundamentoLegal ?? '') ?>" placeholder="Ley 1755 de 2015, art. 14">
            </div>
            <div>
                <label class="etiqueta" for="not-<?= $clave ?>">Correos a notificar (separados por coma)</label>
                <input class="campo" id="not-<?= $clave ?>" type="text" name="notificar_correos" maxlength="500"
                       value="<?= $e($t?->notificarCorreos ?? '') ?>">
            </div>
        </div>
    <?php return (string) ob_get_clean();
};
?>
<h1 class="text-2xl">Tipos de trámite</h1>
<p class="mt-2 texto-suave max-w-prose">
    El término, los campos del formulario y los estados de cada trámite se
    configuran aquí, sin tocar código. <strong>Los términos sembrados son
    provisionales:</strong> ajústalos cuando la oficina jurídica los valide.
</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>

<details class="tarjeta mt-6">
    <summary class="cursor-pointer font-semibold texto-titular">Crear tipo de trámite</summary>
    <form class="mt-4" method="post" action="/admin/tipos-tramite">
        <?= $campos(null, 'nuevo', $dependencias, $tokenCsrf) ?>
        <p class="mt-4"><button type="submit" class="boton boton-primario">Crear (nace con los estados estándar)</button></p>
    </form>
</details>

<ul class="mt-6 flex flex-col gap-4">
    <?php foreach ($tipos as $tipo) { ?>
        <li class="tarjeta">
            <div class="flex flex-wrap items-center gap-3">
                <strong class="texto-titular"><?= $e($tipo->nombre) ?></strong>
                <span class="text-sm texto-suave"><?= $tipo->diasTermino ?> días <?= $tipo->diasHabiles ? 'hábiles' : 'calendario' ?></span>
                <?php if (!$tipo->activo) { ?>
                    <span class="insignia insignia-aviso">Inactivo</span>
                <?php } ?>
                <?php if (str_contains((string) $tipo->fundamentoLegal, 'PROVISIONAL')) { ?>
                    <span class="insignia insignia-error">Término provisional</span>
                <?php } ?>
            </div>

            <details class="mt-3">
                <summary class="cursor-pointer text-sm font-semibold texto-titular">Editar tipo</summary>
                <form class="mt-4" method="post" action="/admin/tipos-tramite/<?= $tipo->id ?>">
                    <?= $campos($tipo, (string) $tipo->id, $dependencias, $tokenCsrf) ?>
                    <p class="mt-4"><button type="submit" class="boton boton-primario">Guardar cambios</button></p>
                </form>
            </details>

            <details class="mt-2">
                <summary class="cursor-pointer text-sm font-semibold texto-titular">Campos del formulario (<?= count($tipo->campos) ?>)</summary>
                <?php if ($tipo->campos !== []) { ?>
                    <ul class="mt-3 flex flex-col gap-1 text-sm">
                        <?php foreach ($tipo->campos as $campo) { ?>
                            <li class="flex items-center gap-2">
                                <span class="min-w-0 flex-1">
                                    <?= $e($campo['etiqueta']) ?>
                                    <span class="texto-suave">(<?= $e($campo['tipo_campo']) ?><?= $campo['requerido'] ? ', obligatorio' : '' ?>)</span>
                                </span>
                                <form method="post" action="/admin/tipos-tramite/campos/<?= $campo['id'] ?>/eliminar">
                                    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                                    <button type="submit" class="text-sm text-error-600 underline">Quitar</button>
                                </form>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
                <form class="mt-3 grid gap-3 sm:grid-cols-[2fr_1fr_2fr_auto_auto] sm:items-end"
                      method="post" action="/admin/tipos-tramite/<?= $tipo->id ?>/campos">
                    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                    <div>
                        <label class="etiqueta" for="ce-<?= $tipo->id ?>">Etiqueta</label>
                        <input class="campo" id="ce-<?= $tipo->id ?>" type="text" name="etiqueta" required maxlength="200">
                    </div>
                    <div>
                        <label class="etiqueta" for="ct-<?= $tipo->id ?>">Tipo</label>
                        <select class="campo" id="ct-<?= $tipo->id ?>" name="tipo_campo">
                            <option value="texto">Texto</option>
                            <option value="texto_largo">Texto largo</option>
                            <option value="select">Lista de opciones</option>
                            <option value="numero">Número</option>
                            <option value="fecha">Fecha</option>
                            <option value="correo">Correo</option>
                            <option value="telefono">Teléfono</option>
                        </select>
                    </div>
                    <div>
                        <label class="etiqueta" for="co-<?= $tipo->id ?>">Opciones (si es lista, separadas por coma)</label>
                        <input class="campo" id="co-<?= $tipo->id ?>" type="text" name="opciones">
                    </div>
                    <label class="flex items-center gap-2 pb-2 text-sm">
                        <input type="checkbox" name="requerido" value="1">
                        Obligatorio
                    </label>
                    <button type="submit" class="boton boton-secundario">Añadir</button>
                </form>
            </details>
        </li>
    <?php } ?>
</ul>

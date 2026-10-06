<?php
/**
 * Panel · Directorio de servidores públicos (ITA 1.6 y 1.6.1).
 * Espera: $servidores, $dependencias, $vinculaciones, $mensaje, $error, $tokenCsrf.
 */
$etiqueta = [
    'planta' => 'Planta',
    'libre_nombramiento' => 'Libre nombramiento y remoción',
    'provisional' => 'Provisional',
    'contrato' => 'Contrato de prestación de servicios',
    'otro' => 'Otra',
];

$campos = static function (array $f, array $dependencias, array $vinculaciones, string $tokenCsrf) use ($e, $etiqueta): string {
    $id = (string) ($f['id'] ?? 'nuevo');
    ob_start(); ?>
    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="etiqueta" for="nombres-<?= $e($id) ?>">Nombres</label>
            <input class="campo" id="nombres-<?= $e($id) ?>" type="text" name="nombres" required maxlength="120"
                   value="<?= $e((string) ($f['nombres'] ?? '')) ?>">
        </div>
        <div>
            <label class="etiqueta" for="apellidos-<?= $e($id) ?>">Apellidos</label>
            <input class="campo" id="apellidos-<?= $e($id) ?>" type="text" name="apellidos" required maxlength="120"
                   value="<?= $e((string) ($f['apellidos'] ?? '')) ?>">
        </div>
    </div>
    <div class="mt-3 grid gap-4 sm:grid-cols-2">
        <div>
            <label class="etiqueta" for="cargo-<?= $e($id) ?>">Cargo</label>
            <input class="campo" id="cargo-<?= $e($id) ?>" type="text" name="cargo" required maxlength="200"
                   value="<?= $e((string) ($f['cargo'] ?? '')) ?>">
        </div>
        <div>
            <label class="etiqueta" for="vinculacion-<?= $e($id) ?>">Tipo de vinculación</label>
            <select class="campo" id="vinculacion-<?= $e($id) ?>" name="tipo_vinculacion">
                <?php foreach ($vinculaciones as $v) { ?>
                    <option value="<?= $e($v) ?>" <?= ($f['tipo_vinculacion'] ?? '') === $v ? 'selected' : '' ?>><?= $e($etiqueta[$v] ?? $v) ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
    <div class="mt-3 grid gap-4 sm:grid-cols-2">
        <div>
            <label class="etiqueta" for="dependencia-<?= $e($id) ?>">Dependencia</label>
            <select class="campo" id="dependencia-<?= $e($id) ?>" name="dependencia_id">
                <option value="">Sin asignar</option>
                <?php foreach ($dependencias as $d) { ?>
                    <option value="<?= (int) $d['id'] ?>" <?= ((int) ($f['dependencia_id'] ?? 0)) === $d['id'] ? 'selected' : '' ?>><?= $e($d['nombre']) ?></option>
                <?php } ?>
            </select>
        </div>
        <div>
            <label class="etiqueta" for="oficina-<?= $e($id) ?>">Oficina</label>
            <input class="campo" id="oficina-<?= $e($id) ?>" type="text" name="oficina" maxlength="200"
                   value="<?= $e((string) ($f['oficina'] ?? '')) ?>">
        </div>
    </div>
    <div class="mt-3">
        <label class="etiqueta" for="formacion-<?= $e($id) ?>">Formación académica</label>
        <input class="campo" id="formacion-<?= $e($id) ?>" type="text" name="formacion_academica" maxlength="300"
               value="<?= $e((string) ($f['formacion_academica'] ?? '')) ?>">
    </div>
    <div class="mt-3">
        <label class="etiqueta" for="experiencia-<?= $e($id) ?>">Experiencia laboral y profesional</label>
        <textarea class="campo" id="experiencia-<?= $e($id) ?>" name="experiencia_laboral" rows="2"><?= $e((string) ($f['experiencia_laboral'] ?? '')) ?></textarea>
        <p class="ayuda-campo">Exigida por el criterio 3.5.d del ITA. Resume cargos y años, sin datos personales.</p>
    </div>

    <?php /* ITA 3.5.b. Se pide el LUGAR de nacimiento, nunca la fecha: juntos
             reconstruyen un dato identificador que la ley no obliga a publicar. */ ?>
    <fieldset class="mt-4">
        <legend class="etiqueta">Lugar de nacimiento</legend>
        <p class="text-sm texto-suave">
            Lo exige el criterio 3.5.b del ITA. No registres la fecha de nacimiento.
        </p>
        <div class="mt-2 grid gap-4 sm:grid-cols-3">
            <div>
                <label class="etiqueta" for="paisnac-<?= $e($id) ?>">País</label>
                <input class="campo" id="paisnac-<?= $e($id) ?>" type="text" name="pais_nacimiento" maxlength="80"
                       value="<?= $e((string) ($f['pais_nacimiento'] ?? 'Colombia')) ?>">
            </div>
            <div>
                <label class="etiqueta" for="depnac-<?= $e($id) ?>">Departamento</label>
                <input class="campo" id="depnac-<?= $e($id) ?>" type="text" name="departamento_nacimiento" maxlength="120"
                       value="<?= $e((string) ($f['departamento_nacimiento'] ?? '')) ?>">
            </div>
            <div>
                <label class="etiqueta" for="ciunac-<?= $e($id) ?>">Ciudad</label>
                <input class="campo" id="ciunac-<?= $e($id) ?>" type="text" name="ciudad_nacimiento" maxlength="120"
                       value="<?= $e((string) ($f['ciudad_nacimiento'] ?? '')) ?>">
            </div>
        </div>
    </fieldset>

    <?php /* ITA 3.5.j: solo para contratos de prestación de servicios. */ ?>
    <fieldset class="mt-4">
        <legend class="etiqueta">Contrato de prestación de servicios</legend>
        <p class="text-sm texto-suave">
            Diligénciala solo si la vinculación es por contrato. Lo exige el criterio 3.5.j del ITA.
        </p>
        <div class="mt-2">
            <label class="etiqueta" for="objeto-<?= $e($id) ?>">Objeto del contrato</label>
            <textarea class="campo" id="objeto-<?= $e($id) ?>" name="contrato_objeto" rows="2"><?= $e((string) ($f['contrato_objeto'] ?? '')) ?></textarea>
        </div>
        <div class="mt-3 grid gap-4 sm:grid-cols-3">
            <div>
                <label class="etiqueta" for="valor-<?= $e($id) ?>">Valor total de los honorarios</label>
                <input class="campo" id="valor-<?= $e($id) ?>" type="text" name="contrato_valor" inputmode="numeric"
                       value="<?= ($f['contrato_valor'] ?? null) !== null ? number_format((float) $f['contrato_valor'], 0, ',', '.') : '' ?>"
                       placeholder="1.500.000">
            </div>
            <div>
                <label class="etiqueta" for="desde-<?= $e($id) ?>">Fecha de inicio</label>
                <input class="campo" id="desde-<?= $e($id) ?>" type="date" name="contrato_desde"
                       value="<?= $e((string) ($f['contrato_desde'] ?? '')) ?>">
            </div>
            <div>
                <label class="etiqueta" for="hasta-<?= $e($id) ?>">Fecha de terminación</label>
                <input class="campo" id="hasta-<?= $e($id) ?>" type="date" name="contrato_hasta"
                       value="<?= $e((string) ($f['contrato_hasta'] ?? '')) ?>">
            </div>
        </div>
    </fieldset>
    <fieldset class="mt-4">
        <legend class="etiqueta">Contacto institucional</legend>
        <p class="text-sm texto-suave">
            Solo datos de oficina. No registres teléfono personal, dirección de
            residencia ni documento de identidad.
        </p>
        <div class="mt-2 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="etiqueta" for="correo-<?= $e($id) ?>">Correo institucional</label>
                <input class="campo" id="correo-<?= $e($id) ?>" type="email" name="correo_institucional" maxlength="160"
                       value="<?= $e((string) ($f['correo_institucional'] ?? '')) ?>">
            </div>
            <div>
                <label class="etiqueta" for="telefono-<?= $e($id) ?>">Teléfono de oficina</label>
                <input class="campo" id="telefono-<?= $e($id) ?>" type="tel" name="telefono_institucional" maxlength="60"
                       value="<?= $e((string) ($f['telefono_institucional'] ?? '')) ?>">
            </div>
        </div>
    </fieldset>
    <div class="mt-3 grid gap-4 sm:grid-cols-2">
        <div>
            <label class="etiqueta" for="hv-<?= $e($id) ?>">Enlace a la hoja de vida (SIGEP)</label>
            <input class="campo" id="hv-<?= $e($id) ?>" type="url" name="url_hoja_vida" maxlength="500"
                   value="<?= $e((string) ($f['url_hoja_vida'] ?? '')) ?>">
        </div>
        <div>
            <label class="etiqueta" for="escala-<?= $e($id) ?>">Escala salarial</label>
            <input class="campo" id="escala-<?= $e($id) ?>" type="text" name="escala_salarial" maxlength="160"
                   value="<?= $e((string) ($f['escala_salarial'] ?? '')) ?>">
        </div>
    </div>
    <div class="mt-3 grid gap-4 sm:grid-cols-3 sm:items-end">
        <div class="sm:col-span-2">
            <label class="etiqueta" for="urlescala-<?= $e($id) ?>">Enlace al acto que fija la escala</label>
            <input class="campo" id="urlescala-<?= $e($id) ?>" type="url" name="url_escala_salarial" maxlength="500"
                   value="<?= $e((string) ($f['url_escala_salarial'] ?? '')) ?>">
        </div>
        <div>
            <label class="etiqueta" for="orden-<?= $e($id) ?>">Orden</label>
            <input class="campo" id="orden-<?= $e($id) ?>" type="number" name="orden" value="<?= (int) ($f['orden'] ?? 0) ?>">
        </div>
    </div>
    <label class="mt-3 flex items-center gap-2">
        <input type="checkbox" name="publicado" value="1" <?= ((int) ($f['publicado'] ?? 0)) === 1 ? 'checked' : '' ?>>
        <span class="font-semibold">Publicado</span>
    </label>
    <?php return (string) ob_get_clean();
};
?>
<h1 class="text-2xl">Servidores públicos</h1>
<p class="mt-2 text-sm texto-suave">
    Ítems 1.6 y 1.6.1 del índice de transparencia. Este directorio es distinto del
    directorio médico: aquí va quién responde por cada dependencia y con qué
    remuneración.
</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>

<details class="tarjeta mt-6">
    <summary class="cursor-pointer font-semibold texto-titular">Añadir servidor público</summary>
    <form class="mt-4" method="post" action="/admin/servidores-publicos">
        <?= $campos(['id' => 'nuevo', 'tipo_vinculacion' => 'planta'], $dependencias, $vinculaciones, $tokenCsrf) ?>
        <p class="mt-4"><button type="submit" class="boton boton-primario">Crear</button></p>
    </form>
</details>

<ul class="mt-6 flex flex-col gap-4">
    <?php foreach ($servidores as $fila) { ?>
        <li class="tarjeta">
            <div class="flex flex-wrap items-center gap-3">
                <strong class="texto-titular min-w-0 flex-1">
                    <?= $e((string) $fila['apellidos']) ?> <?= $e((string) $fila['nombres']) ?>
                </strong>
                <span class="insignia <?= ((int) $fila['publicado']) === 1 ? 'insignia-exito' : 'insignia-aviso' ?>">
                    <?= ((int) $fila['publicado']) === 1 ? 'Publicado' : 'Oculto' ?>
                </span>
                <span class="text-sm texto-suave"><?= $e((string) $fila['cargo']) ?></span>
            </div>
            <details class="mt-3">
                <summary class="cursor-pointer text-sm font-semibold texto-titular">Editar</summary>
                <form class="mt-4" method="post" action="/admin/servidores-publicos/<?= (int) $fila['id'] ?>">
                    <?= $campos($fila, $dependencias, $vinculaciones, $tokenCsrf) ?>
                    <p class="mt-4"><button type="submit" class="boton boton-primario">Guardar cambios</button></p>
                </form>
                <form class="mt-2" method="post" action="/admin/servidores-publicos/<?= (int) $fila['id'] ?>/eliminar">
                    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                    <button type="submit" class="text-sm text-error-600 underline">Eliminar ficha</button>
                </form>
            </details>
        </li>
    <?php } ?>
</ul>

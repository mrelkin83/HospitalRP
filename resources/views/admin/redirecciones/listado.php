<?php
/**
 * Redirecciones de URL antiguas.
 * Espera: $redirecciones, $resumen, $busqueda (string), $codigos (list<int>),
 * $mensaje (?string), $error (?string), $tokenCsrf.
 */

use App\Core\Support\Fechas;
?>
<h1 class="text-2xl">Redirecciones</h1>
<p class="mt-2 texto-suave">
    Las direcciones del sitio anterior siguen citadas en oficios, actos
    administrativos y buscadores. Aquí se enruta cada una a su equivalente para
    que no muera en un 404.
</p>

<p class="mt-2 text-sm texto-suave">
    <?= (int) $resumen['total'] ?> reglas · <?= (int) $resumen['activas'] ?> activas ·
    <?= (int) $resumen['hits'] ?> visitas atendidas.
</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>

<details class="tarjeta mt-6">
    <summary class="cursor-pointer font-semibold texto-titular">Crear redirección</summary>
    <form class="mt-4" method="post" action="/admin/redirecciones">
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
        <div class="grid gap-4 sm:grid-cols-[2fr_2fr_auto_auto] sm:items-end">
            <div>
                <label class="etiqueta" for="nueva-origen">Origen (URL vieja)</label>
                <input class="campo" id="nueva-origen" type="text" name="origen" required maxlength="500"
                       placeholder="/index.php/es/transparencia">
            </div>
            <div>
                <label class="etiqueta" for="nueva-destino">Destino</label>
                <input class="campo" id="nueva-destino" type="text" name="destino" required maxlength="500"
                       placeholder="/transparencia">
            </div>
            <div>
                <label class="etiqueta" for="nueva-codigo">Código</label>
                <select class="campo" id="nueva-codigo" name="codigo">
                    <?php foreach ($codigos as $codigo) { ?>
                        <option value="<?= (int) $codigo ?>"><?= (int) $codigo ?></option>
                    <?php } ?>
                </select>
            </div>
            <label class="flex items-center gap-2 pb-2">
                <input type="checkbox" name="activa" value="1" checked>
                <span class="font-semibold">Activa</span>
            </label>
        </div>
        <p class="mt-2 text-sm texto-suave">
            Un origen terminado en <code>*</code> atrapa todo lo que empiece igual.
            301 es permanente (lo que corresponde a una migración); 302, temporal.
        </p>
        <p class="mt-4"><button type="submit" class="boton boton-primario">Crear</button></p>
    </form>
</details>

<form class="mt-6 flex gap-2" method="get" action="/admin/redirecciones">
    <label class="sr-only" for="buscar-redireccion">Buscar</label>
    <input class="campo" id="buscar-redireccion" type="search" name="q" value="<?= $e($busqueda) ?>"
           placeholder="Buscar en origen o destino">
    <button type="submit" class="boton boton-secundario">Buscar</button>
</form>

<ul class="mt-6 flex flex-col gap-3">
    <?php foreach ($redirecciones as $redireccion) {
        $id = (int) $redireccion['id'];
        ?>
        <li class="tarjeta">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <code><?= $e((string) $redireccion['origen']) ?></code>
                <span aria-hidden="true">→</span>
                <code><?= $e((string) $redireccion['destino']) ?></code>
                <span class="insignia"><?= (int) $redireccion['codigo'] ?></span>
                <?php if ((int) $redireccion['activa'] !== 1) { ?>
                    <span class="insignia insignia-aviso">Inactiva</span>
                <?php } ?>
                <span class="texto-suave">
                    <?= (int) $redireccion['hits'] ?> visita(s)
                    <?php if (($redireccion['ultimo_hit'] ?? null) !== null) { ?>
                        · última: <?= $e(Fechas::conHora((string) $redireccion['ultimo_hit'])) ?>
                    <?php } ?>
                </span>
            </div>

            <details class="mt-3">
                <summary class="cursor-pointer text-sm font-semibold texto-titular">Editar</summary>
                <form class="mt-4" method="post" action="/admin/redirecciones/<?= $id ?>">
                    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                    <div class="grid gap-4 sm:grid-cols-[2fr_2fr_auto_auto] sm:items-end">
                        <div>
                            <label class="etiqueta" for="origen-<?= $id ?>">Origen</label>
                            <input class="campo" id="origen-<?= $id ?>" type="text" name="origen" required maxlength="500" value="<?= $e((string) $redireccion['origen']) ?>">
                        </div>
                        <div>
                            <label class="etiqueta" for="destino-<?= $id ?>">Destino</label>
                            <input class="campo" id="destino-<?= $id ?>" type="text" name="destino" required maxlength="500" value="<?= $e((string) $redireccion['destino']) ?>">
                        </div>
                        <div>
                            <label class="etiqueta" for="codigo-<?= $id ?>">Código</label>
                            <select class="campo" id="codigo-<?= $id ?>" name="codigo">
                                <?php foreach ($codigos as $codigo) { ?>
                                    <option value="<?= (int) $codigo ?>" <?= (int) $codigo === (int) $redireccion['codigo'] ? 'selected' : '' ?>><?= (int) $codigo ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <label class="flex items-center gap-2 pb-2">
                            <input type="checkbox" name="activa" value="1" <?= (int) $redireccion['activa'] === 1 ? 'checked' : '' ?>>
                            <span class="font-semibold">Activa</span>
                        </label>
                    </div>
                    <p class="mt-4"><button type="submit" class="boton boton-primario">Guardar</button></p>
                </form>
                <form class="mt-2" method="post" action="/admin/redirecciones/<?= $id ?>/eliminar">
                    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                    <button type="submit" class="boton boton-peligro">Eliminar</button>
                </form>
            </details>
        </li>
    <?php } ?>
</ul>

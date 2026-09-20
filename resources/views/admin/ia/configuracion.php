<?php
/**
 * Asistente de IA: configuración y consumo.
 * Espera: $activada (bool, efectiva), $activadaConfig (bool, lo marcado),
 *         $proveedor, $modelo (string), $proveedores (array<string,string>),
 *         $claves (array<string,bool>), $consumo (list<fila>),
 *         $recientes (list<fila>), $funciones (list<FuncionIa>),
 *         $mensaje (?string), $tokenCsrf.
 */

use App\Modules\Sistema\Entity\FuncionIa;

$etiquetas = [];

foreach ($funciones as $funcion) {
    $etiquetas[$funcion->value] = $funcion->etiqueta();
}
?>
<h1 class="text-2xl">Asistente de IA</h1>
<p class="mt-2 texto-suave max-w-prose">
    Ayuda a redactar en el panel: resúmenes, etiquetas, textos alternativos,
    metadatos y borradores. <strong>Todo resultado es una sugerencia
    editable</strong>: nada se publica ni se guarda solo. Con el asistente
    apagado, el panel funciona exactamente igual.
</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>

<?php if ($activadaConfig && !$activada) { ?>
    <div class="alerta alerta-aviso mt-4" role="alert">
        El asistente está marcado como activo pero el proveedor
        «<?= $e($proveedores[$proveedor] ?? $proveedor) ?>» no tiene clave de API.
        Guárdela aquí abajo: queda cifrada y no vuelve a mostrarse.
    </div>
<?php } ?>

<?php if (!$cifradorListo) { ?>
    <div class="alerta alerta-error mt-4" role="alert">
        Falta la llave de cifrado del servidor (APP_CLAVE_CIFRADO en el .env),
        así que las claves de API no pueden guardarse desde el panel todavía.
        Es una sola configuración técnica, una sola vez.
    </div>
<?php } ?>

<form class="tarjeta mt-6 max-w-2xl" method="post" action="/admin/ia">
    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
    <div class="flex items-center gap-3">
        <input type="checkbox" id="ia-activada" name="activada" value="1" <?= $activadaConfig ? 'checked' : '' ?>>
        <label class="font-medium texto-titular" for="ia-activada">Activar el asistente de IA</label>
    </div>
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <div>
            <label class="etiqueta" for="ia-proveedor">Proveedor</label>
            <select class="campo" id="ia-proveedor" name="proveedor">
                <?php foreach ($proveedores as $codigo => $nombre) { ?>
                    <option value="<?= $e($codigo) ?>" <?= $codigo === $proveedor ? 'selected' : '' ?>>
                        <?= $e($nombre) ?><?= $claves[$codigo] ? ' ✓' : ' — sin clave' ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div>
            <label class="etiqueta" for="ia-modelo">Modelo</label>
            <select class="campo" id="ia-modelo" name="modelo" data-guardado="<?= $e($modelo) ?>">
                <option value="">(predeterminado del proveedor)</option>
                <?php if ($modelo !== '') { ?>
                    <option value="<?= $e($modelo) ?>" selected><?= $e($modelo) ?></option>
                <?php } ?>
            </select>
            <p class="ayuda-campo">
                La lista se carga sola del proveedor elegido y se refresca cada
                hora: los modelos nuevos aparecen sin escribir nada.
                <button type="button" class="enlace" id="ia-actualizar-modelos">Actualizar ahora</button>
                <span id="ia-modelos-nota" aria-live="polite"></span>
            </p>
        </div>
        <div>
            <label class="etiqueta" for="ia-clave">Clave de API del proveedor elegido</label>
            <input class="campo" id="ia-clave" type="password" name="clave_api" maxlength="200"
                   autocomplete="off" placeholder="Vacío = conservar la guardada">
            <label class="mt-1 flex items-center gap-2 text-sm">
                <input type="checkbox" name="clave_borrar" value="1"> Borrar la clave guardada
            </label>
            <p class="ayuda-campo">Se guarda cifrada en la base y no vuelve a mostrarse.</p>
        </div>
        <div>
            <label class="etiqueta" for="ia-url-base">URL base (opcional)</label>
            <input class="campo" id="ia-url-base" type="url" name="url_base" maxlength="200"
                   value="<?= $e((string) $urlBase) ?>" placeholder="https://api.oneprovider.dev">
            <p class="ayuda-campo">Para pasarelas compatibles con Anthropic (OneProvider) o el
            proveedor «Personalizado». Vacío = la API oficial.</p>
        </div>
    </div>
    <p class="mt-4"><button type="submit" class="boton boton-primario">Guardar</button></p>
</form>

<?php // El JS va en archivo aparte: la CSP (script-src 'self') bloquea el inline. ?>
<script src="/js/ia-config.js" defer></script>

<section class="mt-8" aria-labelledby="consumo-titulo">
    <h2 id="consumo-titulo" class="text-lg font-semibold texto-titular">Consumo de los últimos 30 días</h2>
    <?php if ($consumo === []) { ?>
        <p class="mt-2 texto-suave">Sin uso registrado.</p>
    <?php } else { ?>
        <div class="tarjeta mt-3 overflow-x-auto p-0">
            <table class="tabla">
                <thead>
                    <tr>
                        <th scope="col">Función</th>
                        <th scope="col">Llamadas</th>
                        <th scope="col">Fallidas</th>
                        <th scope="col">Tokens entrada</th>
                        <th scope="col">Tokens salida</th>
                        <th scope="col">Último uso</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($consumo as $fila) { ?>
                        <tr>
                            <td><?= $e($etiquetas[(string) $fila['funcion']] ?? (string) $fila['funcion']) ?></td>
                            <td><?= (int) $fila['llamadas'] ?></td>
                            <td><?= (int) $fila['fallidas'] ?></td>
                            <td><?= number_format((int) $fila['tokens_entrada'], 0, ',', '.') ?></td>
                            <td><?= number_format((int) $fila['tokens_salida'], 0, ',', '.') ?></td>
                            <td><?= $e((string) $fila['ultima_vez']) ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</section>

<?php if ($recientes !== []) { ?>
    <section class="mt-8" aria-labelledby="recientes-titulo">
        <h2 id="recientes-titulo" class="text-lg font-semibold texto-titular">Últimas llamadas</h2>
        <div class="tarjeta mt-3 overflow-x-auto p-0">
            <table class="tabla">
                <thead>
                    <tr>
                        <th scope="col">Fecha</th>
                        <th scope="col">Función</th>
                        <th scope="col">Quién</th>
                        <th scope="col">Modelo</th>
                        <th scope="col">Duración</th>
                        <th scope="col">Resultado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recientes as $fila) { ?>
                        <tr>
                            <td class="whitespace-nowrap"><?= $e((string) $fila['creado_en']) ?></td>
                            <td><?= $e($etiquetas[(string) $fila['funcion']] ?? (string) $fila['funcion']) ?></td>
                            <td><?= $e((string) ($fila['usuario_nombre'] ?? '—')) ?></td>
                            <td><?= $e((string) $fila['modelo']) ?></td>
                            <td><?= (int) $fila['duracion_ms'] ?> ms</td>
                            <td>
                                <?php if ((int) $fila['exito'] === 1) { ?>
                                    <span class="insignia insignia-exito">Correcta</span>
                                <?php } else { ?>
                                    <span class="insignia insignia-error" title="<?= $e((string) ($fila['error'] ?? '')) ?>">Falló</span>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>
<?php } ?>

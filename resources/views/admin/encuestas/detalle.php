<?php
/**
 * Panel · Encuesta: preguntas y resultados AGREGADOS.
 * Espera: $encuesta, $preguntas, $resultados, $total, $tiposPregunta,
 *         $mensaje, $tokenCsrf.
 *
 * Nunca se listan respuestas individuales de preguntas cerradas: el ciudadano
 * respondió creyendo que era anónimo y esta pantalla no puede desmentirlo.
 */
$etiquetaTipo = [
    'escala' => 'Escala de 1 a 5',
    'opcion_unica' => 'Opción única',
    'opcion_multiple' => 'Opción múltiple',
    'texto' => 'Texto libre',
];
$id = (int) $encuesta['id'];
?>
<p class="text-sm"><a href="/admin/encuestas">&larr; Encuestas</a></p>
<h1 class="mt-2 text-2xl"><?= $e((string) $encuesta['titulo']) ?></h1>
<p class="mt-2 text-sm texto-suave"><?= (int) $total ?> respuestas recibidas.</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>

<details class="tarjeta mt-6">
    <summary class="cursor-pointer font-semibold texto-titular">Añadir pregunta</summary>
    <form class="mt-4" method="post" action="/admin/encuestas/<?= $id ?>/preguntas">
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
        <div>
            <label class="etiqueta" for="enunciado">Enunciado</label>
            <input class="campo" id="enunciado" type="text" name="enunciado" required maxlength="500">
        </div>
        <div class="mt-3 grid gap-4 sm:grid-cols-3 sm:items-end">
            <div>
                <label class="etiqueta" for="tipo">Tipo</label>
                <select class="campo" id="tipo" name="tipo">
                    <?php foreach ($tiposPregunta as $t) { ?>
                        <option value="<?= $e($t) ?>"><?= $e($etiquetaTipo[$t] ?? $t) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div>
                <label class="etiqueta" for="orden">Orden</label>
                <input class="campo" id="orden" type="number" name="orden" value="<?= count($preguntas) ?>">
            </div>
            <label class="flex items-center gap-2 pb-2">
                <input type="checkbox" name="obligatoria" value="1" checked>
                <span class="font-semibold">Obligatoria</span>
            </label>
        </div>
        <div class="mt-3">
            <label class="etiqueta" for="opciones">Opciones (una por línea)</label>
            <textarea class="campo" id="opciones" name="opciones" rows="4"
                      aria-describedby="opciones-ayuda"></textarea>
            <p id="opciones-ayuda" class="mt-1 text-sm texto-suave">
                Solo se usan en opción única y opción múltiple. En escala y texto libre se ignoran.
            </p>
        </div>
        <p class="mt-4"><button type="submit" class="boton boton-primario">Añadir</button></p>
    </form>
</details>

<?php if ($preguntas === []) { ?>
    <p class="tarjeta mt-6 texto-suave">
        Esta encuesta aún no tiene preguntas. Mientras no tenga al menos una,
        el formulario público se ve vacío.
    </p>
<?php } ?>

<ul class="mt-6 flex flex-col gap-4">
    <?php foreach ($preguntas as $p) {
        $resultado = $resultados[$p['id']] ?? ['tipo' => 'conteo', 'valores' => []]; ?>
        <li class="tarjeta">
            <div class="flex flex-wrap items-baseline gap-3">
                <strong class="texto-titular min-w-0 flex-1"><?= $e($p['enunciado']) ?></strong>
                <span class="text-sm texto-suave"><?= $e($etiquetaTipo[$p['tipo']] ?? $p['tipo']) ?></span>
                <?php if ($p['obligatoria']) { ?><span class="insignia">Obligatoria</span><?php } ?>
            </div>

            <?php if ($resultado['tipo'] === 'conteo') { ?>
                <?php if ($resultado['valores'] === []) { ?>
                    <p class="mt-3 text-sm texto-suave">Sin respuestas todavía.</p>
                <?php } else { ?>
                    <table class="tabla mt-3">
                        <caption class="sr-only">Resultados de <?= $e($p['enunciado']) ?></caption>
                        <thead><tr><th scope="col">Respuesta</th><th scope="col">Veces</th><th scope="col">Porcentaje</th></tr></thead>
                        <tbody>
                            <?php foreach ($resultado['valores'] as $valor => $veces) { ?>
                                <tr>
                                    <th scope="row"><?= $e((string) $valor) ?></th>
                                    <td><?= (int) $veces ?></td>
                                    <td><?= $total > 0 ? round(((int) $veces) * 100 / $total) : 0 ?>%</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                <?php } ?>
            <?php } else { ?>
                <p class="mt-3 text-sm texto-suave">
                    Respuestas de texto libre. No se publican en el sitio.
                </p>
                <?php if ($resultado['valores'] === []) { ?>
                    <p class="mt-2 text-sm texto-suave">Sin respuestas todavía.</p>
                <?php } else { ?>
                    <ul class="mt-2 flex flex-col gap-2">
                        <?php foreach ($resultado['valores'] as $texto) { ?>
                            <li class="text-sm">&laquo;<?= $e($texto) ?>&raquo;</li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            <?php } ?>

            <form class="mt-3" method="post" action="/admin/encuestas/preguntas/<?= $p['id'] ?>/eliminar"
                  onsubmit="return confirm('Al borrar la pregunta se borran también sus respuestas. ¿Continuar?');">
                <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                <button type="submit" class="text-sm text-error-600 underline">
                    Eliminar pregunta y sus respuestas
                </button>
            </form>
        </li>
    <?php } ?>
</ul>

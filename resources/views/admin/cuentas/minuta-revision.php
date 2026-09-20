<?php
/**
 * A2 — Revisión a dos columnas: páginas escaneadas a la izquierda,
 * formulario prellenado a la derecha, alertas en rojo arriba (§10).
 * Espera: $extraccion, $resultado, $alertas, $paginas, $supervisores,
 * $iaDisponible, $ocrDisponible, $mensaje, $error, $tokenCsrf.
 */
$id = (int) $extraccion['id'];
$r = $resultado;
$v = static fn (string $clave, string $porDefecto = ''): string => trim((string) ($r[$clave] ?? $porDefecto));
$contratista = is_array($r['contratista'] ?? null) ? $r['contratista'] : [];
$cuotas = is_array($r['cuotas'] ?? null) ? $r['cuotas'] : [];
$cdps = is_array($r['cdp'] ?? null) ? $r['cdp'] : [];
$obligaciones = is_array($r['obligaciones_especificas'] ?? null) ? implode("\n", array_map(strval(...), $r['obligaciones_especificas'])) : '';
$sgic = is_array($r['obligaciones_sgic'] ?? null) ? implode("\n", array_map(strval(...), $r['obligaciones_sgic'])) : '';
$nombreCompleto = trim((string) ($contratista['nombre_completo'] ?? ''));
// La minuta trae «APELLIDOS NOMBRES» o «NOMBRES APELLIDOS»; se parte a la
// mitad como sugerencia y el revisor ajusta.
$mitad = (int) ceil(count(explode(' ', $nombreCompleto)) / 2);
$nombresSugeridos = implode(' ', array_slice(explode(' ', $nombreCompleto), 0, $mitad));
$apellidosSugeridos = implode(' ', array_slice(explode(' ', $nombreCompleto), $mitad));
$aplicada = (string) $extraccion['estado'] === 'APLICADA';
?>
<h1 class="text-2xl">Extracción #<?= $id ?> — revisar y crear el contrato</h1>
<p class="mt-1 texto-suave">Estado: <strong><?= $e((string) $extraccion['estado']) ?></strong>
    <?= $extraccion['proveedor'] !== null ? '· extraído con ' . $e($extraccion['proveedor'] . ' ' . $extraccion['modelo']) : '' ?></p>

<?php if ($mensaje !== null) { ?><div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div><?php } ?>
<?php if ($error !== null) { ?><div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div><?php } ?>

<?php foreach ($alertas as $alerta) { ?>
    <div class="alerta alerta-error mt-2" role="alert">⚠ <?= $e($alerta) ?></div>
<?php } ?>

<?php if ($extraccion['error'] !== null) { ?>
    <div class="alerta alerta-error mt-2" role="alert"><?= $e((string) $extraccion['error']) ?></div>
<?php } ?>

<div class="mt-3 flex flex-wrap gap-3">
    <?php if (!$aplicada && $iaDisponible) { ?>
        <form method="post" action="/admin/cuentas-cobro/minutas/<?= $id ?>/procesar">
            <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
            <button class="boton-secundario" type="submit">Reintentar extracción con IA</button>
        </form>
    <?php } ?>
    <?php if (!$aplicada && $ocrDisponible) { ?>
        <form method="post" action="/admin/cuentas-cobro/minutas/<?= $id ?>/ocr">
            <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
            <button class="boton-secundario" type="submit">Rescatar texto con OCR</button>
        </form>
    <?php } ?>
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    <section>
        <h2 class="text-lg font-semibold texto-titular">La minuta (<?= (int) $paginas ?> páginas)</h2>
        <p class="text-sm texto-suave">Clic en una página para verla completa en otra pestaña.</p>
        <div class="mt-2 space-y-3" style="max-height: 75rem; overflow-y: auto;">
            <?php for ($n = 1; $n <= $paginas; $n++) { ?>
                <a href="/admin/cuentas-cobro/minutas/<?= $id ?>/imagen/<?= $n ?>" target="_blank" rel="noopener">
                    <img class="w-full rounded border borde-suave" loading="lazy"
                         src="/admin/cuentas-cobro/minutas/<?= $id ?>/imagen/<?= $n ?>"
                         alt="Página <?= $n ?> de la minuta">
                </a>
            <?php } ?>
        </div>
    </section>

    <section>
        <?php if ($aplicada) { ?>
            <div class="alerta alerta-exito" role="status">
                Esta extracción ya se aplicó al contrato
                <a class="enlace font-semibold" href="/admin/cuentas-cobro/contratos/<?= (int) $extraccion['contrato_id'] ?>">ver ficha</a>.
            </div>
        <?php } else { ?>
        <form class="tarjeta" method="post" action="/admin/cuentas-cobro/minutas/<?= $id ?>/aplicar">
            <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">

            <h2 class="text-lg font-semibold texto-titular">Contratista</h2>
            <div class="mt-2 grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="etiqueta" for="x-cedula">Cédula</label>
                    <input class="campo" id="x-cedula" type="text" name="contratista_documento" required
                           inputmode="numeric" value="<?= $e((string) ($contratista['numero_documento'] ?? '')) ?>">
                </div>
                <div>
                    <label class="etiqueta" for="x-nombres">Nombres</label>
                    <input class="campo" id="x-nombres" type="text" name="contratista_nombres" maxlength="120"
                           value="<?= $e($nombresSugeridos) ?>">
                </div>
                <div>
                    <label class="etiqueta" for="x-apellidos">Apellidos</label>
                    <input class="campo" id="x-apellidos" type="text" name="contratista_apellidos" maxlength="120"
                           value="<?= $e($apellidosSugeridos) ?>">
                </div>
            </div>

            <h2 class="mt-5 text-lg font-semibold texto-titular">Contrato</h2>
            <div class="mt-2 grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="etiqueta" for="x-numero">Número</label>
                    <input class="campo" id="x-numero" type="text" name="numero" required maxlength="20"
                           value="<?= $e($v('numero_contrato')) ?>">
                </div>
                <div>
                    <label class="etiqueta" for="x-fecha">Fecha de suscripción</label>
                    <input class="campo" id="x-fecha" type="date" name="fecha_suscripcion" required
                           value="<?= $e($v('fecha_suscripcion')) ?>">
                </div>
                <div>
                    <label class="etiqueta" for="x-valor">Valor total (pesos)</label>
                    <input class="campo" id="x-valor" type="number" name="valor_total" required min="1"
                           value="<?= $e((string) (int) ($r['valor_total'] ?? 0) ?: '') ?>">
                </div>
                <div>
                    <label class="etiqueta" for="x-tipo">Tipo de contrato</label>
                    <input class="campo" id="x-tipo" type="text" name="tipo_contrato" maxlength="120"
                           value="<?= $e($v('tipo_contrato')) ?>">
                </div>
                <div>
                    <label class="etiqueta" for="x-plazo">Plazo (texto)</label>
                    <input class="campo" id="x-plazo" type="text" name="plazo_texto" maxlength="120"
                           value="<?= $e($v('plazo_texto') !== '' ? $v('plazo_texto') : $v('plazo_recuadro')) ?>">
                </div>
                <div>
                    <label class="etiqueta" for="x-supervisor">Supervisor</label>
                    <select class="campo" id="x-supervisor" name="supervisor_id">
                        <option value="0">Pendiente de asignar</option>
                        <?php foreach ($supervisores as $sup) { ?>
                            <option value="<?= (int) $sup['id'] ?>"><?= $e((string) $sup['nombre_completo']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div>
                    <label class="etiqueta" for="x-cargo-sup">Cargo del supervisor (minuta)</label>
                    <input class="campo" id="x-cargo-sup" type="text" name="cargo_supervisor_minuta" maxlength="160"
                           value="<?= $e($v('cargo_supervisor')) ?>">
                </div>
                <div>
                    <label class="etiqueta" for="x-meses">Plazo: meses</label>
                    <input class="campo" id="x-meses" type="number" name="plazo_meses" min="0" max="255" value="0">
                </div>
                <div>
                    <label class="etiqueta" for="x-dias">Plazo: días</label>
                    <input class="campo" id="x-dias" type="number" name="plazo_dias" min="0" max="999" value="0">
                </div>
                <label class="flex items-end gap-2 pb-2 text-sm">
                    <input type="checkbox" name="garantias" value="1" <?= ($r['garantias'] ?? false) === true ? 'checked' : '' ?>>
                    Con garantías
                </label>
                <div class="sm:col-span-3">
                    <label class="etiqueta" for="x-objeto">Objeto</label>
                    <textarea class="campo" id="x-objeto" name="objeto" rows="4" required><?= $e($v('objeto')) ?></textarea>
                </div>
                <div class="sm:col-span-3">
                    <label class="etiqueta" for="x-letras">Valor en letras (vacío = lo escribe el sistema)</label>
                    <input class="campo" id="x-letras" type="text" name="valor_total_letras" maxlength="400"
                           value="<?= $e($v('valor_total_letras')) ?>">
                </div>
            </div>

            <h2 class="mt-5 text-lg font-semibold texto-titular">Cronograma de pagos</h2>
            <div class="mt-2 overflow-x-auto">
                <table class="w-full text-sm" id="tabla-cuotas-minuta">
                    <thead>
                        <tr class="text-left texto-suave">
                            <th class="p-1">Descripción</th><th class="p-1">Tipo</th>
                            <th class="p-1">Mes</th><th class="p-1">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $filas = $cuotas !== [] ? $cuotas : [['descripcion' => '', 'tipo' => 'COMPLETA', 'mes' => '', 'valor' => '']]; ?>
                        <?php foreach ($filas as $cuota) { ?>
                            <tr>
                                <td class="p-1"><input class="campo" type="text" name="cuota_descripcion[]" maxlength="120"
                                       value="<?= $e((string) ($cuota['descripcion'] ?? '')) ?>" aria-label="Descripción de la cuota"></td>
                                <td class="p-1">
                                    <select class="campo" name="cuota_tipo[]" aria-label="Tipo de cuota">
                                        <option value="COMPLETA" <?= ($cuota['tipo'] ?? '') !== 'PARCIAL' ? 'selected' : '' ?>>Mes completo</option>
                                        <option value="PARCIAL" <?= ($cuota['tipo'] ?? '') === 'PARCIAL' ? 'selected' : '' ?>>Parcial</option>
                                    </select>
                                </td>
                                <td class="p-1"><input class="campo" type="text" name="cuota_mes[]" maxlength="7"
                                       value="<?= $e((string) ($cuota['mes'] ?? '')) ?>" placeholder="2026-08" aria-label="Mes"></td>
                                <td class="p-1"><input class="campo" type="number" name="cuota_valor[]" min="0"
                                       value="<?= $e((string) (int) ($cuota['valor'] ?? 0) ?: '') ?>" aria-label="Valor"></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <button class="boton-secundario mt-2" type="button" id="agregar-cuota-minuta">Agregar cuota</button>

            <h2 class="mt-5 text-lg font-semibold texto-titular">CDP</h2>
            <div class="mt-2 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left texto-suave">
                            <th class="p-1">Número</th><th class="p-1">Fecha</th>
                            <th class="p-1">Código rubro</th><th class="p-1">Nombre rubro</th><th class="p-1">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $filasCdp = $cdps !== [] ? $cdps : [['numero' => '', 'fecha' => '', 'codigo_rubro' => '', 'nombre_rubro' => '', 'valor' => '']]; ?>
                        <?php foreach ($filasCdp as $cdp) { ?>
                            <tr>
                                <td class="p-1"><input class="campo" type="text" name="cdp_numero[]" maxlength="30"
                                       value="<?= $e((string) ($cdp['numero'] ?? '')) ?>" aria-label="Número del CDP"></td>
                                <td class="p-1"><input class="campo" type="date" name="cdp_fecha[]"
                                       value="<?= $e((string) ($cdp['fecha'] ?? '')) ?>" aria-label="Fecha del CDP"></td>
                                <td class="p-1"><input class="campo" type="text" name="cdp_codigo_rubro[]" maxlength="40"
                                       value="<?= $e((string) ($cdp['codigo_rubro'] ?? '')) ?>" aria-label="Código del rubro"></td>
                                <td class="p-1"><input class="campo" type="text" name="cdp_nombre_rubro[]" maxlength="200"
                                       value="<?= $e((string) ($cdp['nombre_rubro'] ?? '')) ?>" aria-label="Nombre del rubro"></td>
                                <td class="p-1"><input class="campo" type="number" name="cdp_valor[]" min="0"
                                       value="<?= $e((string) (int) ($cdp['valor'] ?? 0) ?: '') ?>" aria-label="Valor del CDP"></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <p class="ayuda text-sm texto-suave">Si el CDP no trae valor, se registra con el valor del contrato; el RP se registra después en la ficha (A3).</p>

            <h2 class="mt-5 text-lg font-semibold texto-titular">Obligaciones</h2>
            <label class="etiqueta" for="x-obligaciones">Específicas (una por línea, en orden)</label>
            <textarea class="campo" id="x-obligaciones" name="obligaciones" rows="10"><?= $e($obligaciones) ?></textarea>
            <label class="etiqueta" for="x-sgic">Del SGIC (una por línea)</label>
            <textarea class="campo" id="x-sgic" name="obligaciones_sgic" rows="5"><?= $e($sgic) ?></textarea>
            <label class="etiqueta" for="x-forma-pago">Cláusula «Forma de pago» (referencia)</label>
            <textarea class="campo" id="x-forma-pago" name="forma_pago_texto" rows="4"><?= $e($v('forma_pago_texto')) ?></textarea>

            <div class="mt-5 flex flex-wrap gap-3">
                <button class="boton-principal" type="submit"
                        onclick="return confirm('Se creará el contrato EN REVISIÓN con estos datos. ¿Continuar?');">
                    Crear contrato en revisión
                </button>
                <a class="boton-secundario" href="/admin/cuentas-cobro/minutas">Volver</a>
            </div>
        </form>
        <?php } ?>
    </section>
</div>

<script>
(function () {
    var boton = document.getElementById('agregar-cuota-minuta');

    if (!boton) { return; }

    boton.addEventListener('click', function () {
        var cuerpo = document.querySelector('#tabla-cuotas-minuta tbody');
        var fila = cuerpo.lastElementChild.cloneNode(true);
        fila.querySelectorAll('input').forEach(function (campo) { campo.value = ''; });
        fila.querySelector('select').selectedIndex = 0;
        cuerpo.appendChild(fila);
    });
})();
</script>

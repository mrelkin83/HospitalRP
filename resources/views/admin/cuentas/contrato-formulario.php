<?php
/**
 * A1 — Alta y edición manual de un contrato (solo EN_REVISION).
 * Espera: $contrato (?fila), $obligaciones, $cuotas, $contratistas,
 * $supervisores, $error, $tokenCsrf.
 */
$v = static fn (string $campo, string $porDefecto = ''): string => (string) ($contrato[$campo] ?? $porDefecto);
$pesosEnteros = static fn (string $decimal): string => explode('.', $decimal, 2)[0];
$especificas = implode("\n", array_column(array_filter($obligaciones, static fn (array $o): bool => $o['grupo'] === 'ESPECIFICA'), 'texto'));
$sgic = implode("\n", array_column(array_filter($obligaciones, static fn (array $o): bool => $o['grupo'] === 'SGIC'), 'texto'));
$accion = $contrato === null ? '/admin/cuentas-cobro/contratos' : '/admin/cuentas-cobro/contratos/' . (int) $contrato['id'];
?>
<h1 class="text-2xl"><?= $contrato === null ? 'Nuevo contrato' : 'Editar contrato ' . $e($v('numero')) ?></h1>
<p class="mt-2 texto-suave">
    Copie los datos tal como vienen en la minuta. El sistema calcula el valor
    en letras y, con el acta de inicio, las fechas de cada cuota.
</p>

<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>

<form class="tarjeta mt-4" method="post" action="<?= $e($accion) ?>">
    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">

    <h2 class="text-lg font-semibold texto-titular">Información general</h2>
    <div class="mt-3 grid gap-4 sm:grid-cols-3">
        <div>
            <label class="etiqueta" for="c-numero">Número del contrato</label>
            <input class="campo" id="c-numero" type="text" name="numero" required maxlength="20"
                   placeholder="1407-2026" value="<?= $e($v('numero')) ?>">
        </div>
        <div>
            <label class="etiqueta" for="c-fecha">Fecha de suscripción</label>
            <input class="campo" id="c-fecha" type="date" name="fecha_suscripcion" required
                   value="<?= $e($v('fecha_suscripcion')) ?>">
        </div>
        <div>
            <label class="etiqueta" for="c-ciudad">Ciudad</label>
            <input class="campo" id="c-ciudad" type="text" name="ciudad_suscripcion" maxlength="80"
                   value="<?= $e($v('ciudad_suscripcion', 'Valledupar')) ?>">
        </div>
        <div>
            <label class="etiqueta" for="c-contratista">Contratista</label>
            <select class="campo" id="c-contratista" name="contratista_id" required>
                <option value="">Seleccione…</option>
                <?php foreach ($contratistas as $persona) { ?>
                    <option value="<?= (int) $persona['id'] ?>" <?= (int) $v('contratista_id', '0') === (int) $persona['id'] ? 'selected' : '' ?>>
                        <?= $e($persona['nombre_completo'] . ' — ' . $persona['numero_documento']) ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div>
            <label class="etiqueta" for="c-supervisor">Supervisor</label>
            <select class="campo" id="c-supervisor" name="supervisor_id">
                <option value="0">Pendiente de asignar</option>
                <?php foreach ($supervisores as $sup) { ?>
                    <option value="<?= (int) $sup['id'] ?>" <?= (int) $v('supervisor_id', '0') === (int) $sup['id'] ? 'selected' : '' ?>>
                        <?= $e((string) $sup['nombre_completo']) ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div>
            <label class="etiqueta" for="c-cargo-sup">Cargo del supervisor en la minuta</label>
            <input class="campo" id="c-cargo-sup" type="text" name="cargo_supervisor_minuta" maxlength="160"
                   value="<?= $e($v('cargo_supervisor_minuta')) ?>">
        </div>
        <div>
            <label class="etiqueta" for="c-tipo">Tipo de contrato</label>
            <input class="campo" id="c-tipo" type="text" name="tipo_contrato" maxlength="120"
                   value="<?= $e($v('tipo_contrato', 'PRESTACIÓN DE SERVICIOS')) ?>">
        </div>
        <div>
            <label class="etiqueta" for="c-valor">Valor total (pesos, sin puntos)</label>
            <input class="campo" id="c-valor" type="number" name="valor_total" required min="1"
                   value="<?= $e($pesosEnteros($v('valor_total', '0'))) ?>">
        </div>
        <div>
            <label class="etiqueta" for="c-seccion">Sección o ubicación</label>
            <input class="campo" id="c-seccion" type="text" name="seccion_ubicacion" maxlength="160"
                   placeholder="AUTORIZADORA URGENCIAS" value="<?= $e($v('seccion_ubicacion')) ?>">
        </div>
        <div class="sm:col-span-3">
            <label class="etiqueta" for="c-objeto">Objeto</label>
            <textarea class="campo" id="c-objeto" name="objeto" rows="3" required><?= $e($v('objeto')) ?></textarea>
        </div>
        <div class="sm:col-span-3">
            <label class="etiqueta" for="c-letras">Valor en letras (déjelo vacío para que lo escriba el sistema)</label>
            <input class="campo" id="c-letras" type="text" name="valor_total_letras" maxlength="400"
                   value="<?= $e($v('valor_total_letras')) ?>">
        </div>
    </div>

    <h2 class="mt-6 text-lg font-semibold texto-titular">Plazo y periodos</h2>
    <div class="mt-3 grid gap-4 sm:grid-cols-4">
        <div>
            <label class="etiqueta" for="c-plazo-texto">Plazo (texto de la minuta)</label>
            <input class="campo" id="c-plazo-texto" type="text" name="plazo_texto" maxlength="120"
                   placeholder="CINCO (5) MESES" value="<?= $e($v('plazo_texto')) ?>">
        </div>
        <div>
            <label class="etiqueta" for="c-plazo-meses">Meses</label>
            <input class="campo" id="c-plazo-meses" type="number" name="plazo_meses" min="0" max="255"
                   value="<?= $e($v('plazo_meses', '0')) ?>">
        </div>
        <div>
            <label class="etiqueta" for="c-plazo-dias">Días</label>
            <input class="campo" id="c-plazo-dias" type="number" name="plazo_dias" min="0" max="999"
                   value="<?= $e($v('plazo_dias', '0')) ?>">
        </div>
        <div>
            <label class="etiqueta" for="c-modalidad">Corte de periodos</label>
            <select class="campo" id="c-modalidad" name="modalidad_periodos">
                <option value="CALENDARIO" <?= $v('modalidad_periodos', 'CALENDARIO') === 'CALENDARIO' ? 'selected' : '' ?>>Mes calendario (del 1 al último día)</option>
                <option value="ANIVERSARIO" <?= $v('modalidad_periodos') === 'ANIVERSARIO' ? 'selected' : '' ?>>Aniversario (del 28 al 27, por ejemplo)</option>
            </select>
        </div>
        <label class="mt-1 flex items-center gap-2 text-sm sm:col-span-2">
            <input type="checkbox" name="garantias" value="1" <?= $v('garantias', '0') === '1' ? 'checked' : '' ?>>
            El contrato exige garantías
        </label>
    </div>

    <h2 class="mt-6 text-lg font-semibold texto-titular">Cronograma de pagos</h2>
    <p class="mt-1 text-sm texto-suave">
        Una fila por cuota, en orden. La suma debe dar exactamente el valor
        total; si el primer pago cubre solo unos días del mes, márquelo como
        «parcial». El mes solo hace falta cuando el corte es calendario y hay
        saltos; si va seguido, el sistema lo deduce.
    </p>
    <div class="mt-3 overflow-x-auto">
        <table class="w-full text-sm" id="tabla-cuotas">
            <thead>
                <tr class="text-left texto-suave">
                    <th class="p-1">Descripción</th>
                    <th class="p-1">Tipo</th>
                    <th class="p-1">Mes (AAAA-MM)</th>
                    <th class="p-1">Valor (pesos)</th>
                </tr>
            </thead>
            <tbody>
                <?php $filas = $cuotas === [] ? [['descripcion' => '', 'tipo' => 'COMPLETA', 'mes' => '', 'valor' => '']] : $cuotas; ?>
                <?php foreach ($filas as $fila) { ?>
                    <tr>
                        <td class="p-1"><input class="campo" type="text" name="cuota_descripcion[]" maxlength="120"
                               placeholder="Agosto de 2026" value="<?= $e((string) ($fila['descripcion'] ?? '')) ?>"
                               aria-label="Descripción de la cuota"></td>
                        <td class="p-1">
                            <select class="campo" name="cuota_tipo[]" aria-label="Tipo de cuota">
                                <option value="COMPLETA" <?= ($fila['tipo'] ?? '') !== 'PARCIAL' ? 'selected' : '' ?>>Mes completo</option>
                                <option value="PARCIAL" <?= ($fila['tipo'] ?? '') === 'PARCIAL' ? 'selected' : '' ?>>Parcial (días)</option>
                            </select>
                        </td>
                        <td class="p-1"><input class="campo" type="text" name="cuota_mes[]" maxlength="7" pattern="\d{4}-\d{2}"
                               placeholder="2026-08" value="<?= $e((string) ($fila['mes'] ?? '')) ?>" aria-label="Mes de la cuota"></td>
                        <td class="p-1"><input class="campo" type="number" name="cuota_valor[]" min="0"
                               value="<?= $e($fila['valor'] !== '' ? explode('.', (string) $fila['valor'], 2)[0] : '') ?>"
                               aria-label="Valor de la cuota"></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <button class="boton-secundario mt-2" type="button" id="agregar-cuota">Agregar cuota</button>

    <h2 class="mt-6 text-lg font-semibold texto-titular">Obligaciones</h2>
    <div class="mt-3 grid gap-4">
        <div>
            <label class="etiqueta" for="c-obligaciones">Obligaciones específicas (una por línea, en el orden de la minuta)</label>
            <textarea class="campo" id="c-obligaciones" name="obligaciones" rows="8"><?= $e($especificas) ?></textarea>
        </div>
        <div>
            <label class="etiqueta" for="c-obligaciones-sgic">Obligaciones del SGIC (una por línea; no se reportan mes a mes)</label>
            <textarea class="campo" id="c-obligaciones-sgic" name="obligaciones_sgic" rows="4"><?= $e($sgic) ?></textarea>
        </div>
        <div>
            <label class="etiqueta" for="c-forma-pago">Cláusula «Forma de pago» textual (referencia)</label>
            <textarea class="campo" id="c-forma-pago" name="forma_pago_texto" rows="3"><?= $e($v('forma_pago_texto')) ?></textarea>
        </div>
        <div>
            <label class="etiqueta" for="c-requisitos">Requisitos de pago (literales de la cláusula 3)</label>
            <textarea class="campo" id="c-requisitos" name="requisitos_pago_texto" rows="3"><?= $e($v('requisitos_pago_texto')) ?></textarea>
        </div>
    </div>

    <div class="mt-6 flex gap-3">
        <button class="boton-principal" type="submit">Guardar en revisión</button>
        <a class="boton-secundario" href="/admin/cuentas-cobro/contratos">Volver</a>
    </div>
</form>

<script>
// Filas de cuotas sin recargar: se clona la última y se limpia.
document.getElementById('agregar-cuota').addEventListener('click', function () {
    var cuerpo = document.querySelector('#tabla-cuotas tbody');
    var fila = cuerpo.lastElementChild.cloneNode(true);
    fila.querySelectorAll('input').forEach(function (campo) { campo.value = ''; });
    fila.querySelector('select').selectedIndex = 0;
    cuerpo.appendChild(fila);
});
</script>

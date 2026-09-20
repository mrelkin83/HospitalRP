<?php
/**
 * A6/A7 — Parámetros anuales, clases ARL y plantillas SGC.
 * Espera: $anuales, $clasesArl, $plantillas, $mensaje, $error, $tokenCsrf.
 */
$moneda = static fn (string $v): string => '$ ' . number_format((int) explode('.', $v, 2)[0], 0, ',', '.');
?>
<h1 class="text-2xl">Parámetros de cuentas de cobro</h1>
<p class="mt-2 texto-suave">
    Cada enero cambia el SMLV por decreto: se registra aquí y todo el módulo
    calcula con el año nuevo, sin tocar código.
</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>

<section class="tarjeta mt-4">
    <h2 class="text-lg font-semibold texto-titular">Parámetros por año</h2>
    <div class="mt-3 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left texto-suave">
                    <th class="p-2">Año</th><th class="p-2">SMLV</th><th class="p-2">% IBC</th>
                    <th class="p-2">Salud</th><th class="p-2">Pensión</th><th class="p-2">Redondeo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($anuales as $fila) { ?>
                    <tr class="border-t borde-suave">
                        <td class="p-2 font-semibold"><?= (int) $fila['anio'] ?></td>
                        <td class="p-2"><?= $e($moneda((string) $fila['smlv'])) ?></td>
                        <td class="p-2"><?= $e((string) $fila['porcentaje_ibc']) ?></td>
                        <td class="p-2"><?= $e((string) $fila['tarifa_salud']) ?></td>
                        <td class="p-2"><?= $e((string) $fila['tarifa_pension']) ?></td>
                        <td class="p-2">al múltiplo de <?= (int) $fila['redondeo_aportes'] ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <details class="mt-3">
        <summary class="cursor-pointer font-semibold texto-titular">Registrar o corregir un año</summary>
        <form class="mt-3 grid gap-3 sm:grid-cols-3" method="post" action="/admin/cuentas-cobro/parametros/anio">
            <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
            <div>
                <label class="etiqueta" for="par-anio">Año</label>
                <input class="campo" id="par-anio" type="number" name="anio" required min="2020" max="2100"
                       value="<?= (int) date('Y') + 1 ?>">
            </div>
            <div>
                <label class="etiqueta" for="par-smlv">SMLV (pesos, sin puntos)</label>
                <input class="campo" id="par-smlv" type="number" name="smlv" required min="1">
            </div>
            <div>
                <label class="etiqueta" for="par-redondeo">Redondeo de aportes</label>
                <input class="campo" id="par-redondeo" type="number" name="redondeo_aportes" value="100" min="1">
            </div>
            <div>
                <label class="etiqueta" for="par-ibc">Porcentaje IBC</label>
                <input class="campo" id="par-ibc" type="text" name="porcentaje_ibc" value="0.4000" pattern="0\.\d{1,4}">
            </div>
            <div>
                <label class="etiqueta" for="par-salud">Tarifa salud</label>
                <input class="campo" id="par-salud" type="text" name="tarifa_salud" value="0.1250" pattern="0\.\d{1,4}">
            </div>
            <div>
                <label class="etiqueta" for="par-pension">Tarifa pensión</label>
                <input class="campo" id="par-pension" type="text" name="tarifa_pension" value="0.1600" pattern="0\.\d{1,4}">
            </div>
            <div class="sm:col-span-3">
                <button class="boton-principal" type="submit">Guardar año</button>
            </div>
        </form>
    </details>
</section>

<section class="tarjeta mt-4">
    <h2 class="text-lg font-semibold texto-titular">Clases de riesgo ARL</h2>
    <p class="mt-1 text-sm texto-suave">Tarifas de ley, de consulta. La clase de cada contratista se fija en su ficha.</p>
    <div class="mt-3 overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left texto-suave"><th class="p-2">Clase</th><th class="p-2">Tarifa</th><th class="p-2">Descripción</th></tr></thead>
            <tbody>
                <?php foreach ($clasesArl as $clase) { ?>
                    <tr class="border-t borde-suave">
                        <td class="p-2 font-semibold"><?= (int) $clase['clase'] ?></td>
                        <td class="p-2"><?= $e((string) $clase['tarifa']) ?></td>
                        <td class="p-2"><?= $e((string) $clase['descripcion']) ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<section class="tarjeta mt-4">
    <h2 class="text-lg font-semibold texto-titular">Plantillas de documentos</h2>
    <p class="mt-1 text-sm texto-suave">
        El encabezado de cada PDF lleva CÓDIGO / VERSIÓN / FECHA del sistema de
        gestión de calidad. El código del informe de actividades lo definirá el
        Interventor; mientras tanto puede quedar vacío.
    </p>
    <div class="mt-3 grid gap-4 lg:grid-cols-3">
        <?php foreach ($plantillas as $plantilla) { $clave = (string) $plantilla['clave']; ?>
            <form class="rounded-lg border borde-suave p-3" method="post"
                  action="/admin/cuentas-cobro/parametros/plantillas/<?= $e($clave) ?>">
                <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                <h3 class="font-semibold texto-titular"><?= $e((string) $plantilla['nombre']) ?></h3>
                <div class="mt-2 grid gap-2">
                    <div>
                        <label class="etiqueta" for="pl-codigo-<?= $e($clave) ?>">Código</label>
                        <input class="campo" id="pl-codigo-<?= $e($clave) ?>" type="text" name="codigo_sgc" maxlength="30"
                               value="<?= $e((string) ($plantilla['codigo_sgc'] ?? '')) ?>" placeholder="GF-FR-002">
                    </div>
                    <div>
                        <label class="etiqueta" for="pl-version-<?= $e($clave) ?>">Versión</label>
                        <input class="campo" id="pl-version-<?= $e($clave) ?>" type="text" name="version_sgc" maxlength="10"
                               value="<?= $e((string) ($plantilla['version_sgc'] ?? '')) ?>" placeholder="001">
                    </div>
                    <div>
                        <label class="etiqueta" for="pl-fecha-<?= $e($clave) ?>">Fecha</label>
                        <input class="campo" id="pl-fecha-<?= $e($clave) ?>" type="date" name="fecha_sgc"
                               value="<?= $e((string) ($plantilla['fecha_sgc'] ?? '')) ?>">
                    </div>
                    <div>
                        <label class="etiqueta" for="pl-proceso-<?= $e($clave) ?>">Proceso</label>
                        <input class="campo" id="pl-proceso-<?= $e($clave) ?>" type="text" name="proceso" maxlength="80"
                               value="<?= $e((string) ($plantilla['proceso'] ?? '')) ?>">
                    </div>
                    <button class="boton-principal" type="submit">Guardar</button>
                </div>
            </form>
        <?php } ?>
    </div>
</section>

<?php
/**
 * A4 — Contratistas.
 * Espera: $contratistas, $mensaje, $error, $tokenCsrf.
 */
$formulario = static function (array $c, string $accion, string $sufijo) use ($e, $tokenCsrf): string {
    ob_start(); ?>
    <form class="mt-3 grid gap-3 sm:grid-cols-3" method="post" action="<?= $e($accion) ?>">
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
        <div>
            <label class="etiqueta" for="ct-tipo-<?= $e($sufijo) ?>">Tipo de documento</label>
            <select class="campo" id="ct-tipo-<?= $e($sufijo) ?>" name="tipo_documento">
                <?php foreach (['CC' => 'Cédula', 'CE' => 'Cédula de extranjería', 'PPT' => 'PPT', 'NIT' => 'NIT'] as $valor => $etiqueta) { ?>
                    <option value="<?= $valor ?>" <?= ($c['tipo_documento'] ?? 'CC') === $valor ? 'selected' : '' ?>><?= $etiqueta ?></option>
                <?php } ?>
            </select>
        </div>
        <div>
            <label class="etiqueta" for="ct-doc-<?= $e($sufijo) ?>">Número de documento</label>
            <input class="campo" id="ct-doc-<?= $e($sufijo) ?>" type="text" name="numero_documento" required
                   inputmode="numeric" pattern="\d{5,20}" value="<?= $e((string) ($c['numero_documento'] ?? '')) ?>">
        </div>
        <div>
            <label class="etiqueta" for="ct-lugar-<?= $e($sufijo) ?>">Lugar de expedición</label>
            <input class="campo" id="ct-lugar-<?= $e($sufijo) ?>" type="text" name="lugar_expedicion" maxlength="80"
                   value="<?= $e((string) ($c['lugar_expedicion'] ?? '')) ?>" placeholder="Valledupar">
        </div>
        <div>
            <label class="etiqueta" for="ct-nombres-<?= $e($sufijo) ?>">Nombres</label>
            <input class="campo" id="ct-nombres-<?= $e($sufijo) ?>" type="text" name="nombres" required maxlength="120"
                   value="<?= $e((string) ($c['nombres'] ?? '')) ?>">
        </div>
        <div>
            <label class="etiqueta" for="ct-apellidos-<?= $e($sufijo) ?>">Apellidos</label>
            <input class="campo" id="ct-apellidos-<?= $e($sufijo) ?>" type="text" name="apellidos" required maxlength="120"
                   value="<?= $e((string) ($c['apellidos'] ?? '')) ?>">
        </div>
        <div>
            <label class="etiqueta" for="ct-arl-<?= $e($sufijo) ?>">Clase de riesgo ARL</label>
            <select class="campo" id="ct-arl-<?= $e($sufijo) ?>" name="clase_riesgo_arl">
                <option value="0">Sin definir</option>
                <?php for ($clase = 1; $clase <= 5; $clase++) { ?>
                    <option value="<?= $clase ?>" <?= (int) ($c['clase_riesgo_arl'] ?? 0) === $clase ? 'selected' : '' ?>>Clase <?= $clase ?></option>
                <?php } ?>
            </select>
        </div>
        <div>
            <label class="etiqueta" for="ct-celular-<?= $e($sufijo) ?>">Celular</label>
            <input class="campo" id="ct-celular-<?= $e($sufijo) ?>" type="text" name="celular" maxlength="30"
                   value="<?= $e((string) ($c['celular'] ?? '')) ?>">
        </div>
        <div>
            <label class="etiqueta" for="ct-correo-<?= $e($sufijo) ?>">Correo</label>
            <input class="campo" id="ct-correo-<?= $e($sufijo) ?>" type="email" name="correo" maxlength="150"
                   value="<?= $e((string) ($c['correo'] ?? '')) ?>">
        </div>
        <div>
            <label class="etiqueta" for="ct-telefono-<?= $e($sufijo) ?>">Teléfono fijo</label>
            <input class="campo" id="ct-telefono-<?= $e($sufijo) ?>" type="text" name="telefono" maxlength="30"
                   value="<?= $e((string) ($c['telefono'] ?? '')) ?>">
        </div>
        <div class="sm:col-span-3">
            <label class="etiqueta" for="ct-direccion-<?= $e($sufijo) ?>">Dirección</label>
            <input class="campo" id="ct-direccion-<?= $e($sufijo) ?>" type="text" name="direccion" maxlength="200"
                   value="<?= $e((string) ($c['direccion'] ?? '')) ?>">
        </div>
        <div>
            <label class="etiqueta" for="ct-banco-<?= $e($sufijo) ?>">Banco</label>
            <input class="campo" id="ct-banco-<?= $e($sufijo) ?>" type="text" name="banco" maxlength="100"
                   value="<?= $e((string) ($c['banco'] ?? '')) ?>">
        </div>
        <div>
            <label class="etiqueta" for="ct-tipo-cuenta-<?= $e($sufijo) ?>">Tipo de cuenta</label>
            <select class="campo" id="ct-tipo-cuenta-<?= $e($sufijo) ?>" name="tipo_cuenta">
                <option value="">Sin registrar</option>
                <option value="AHORROS" <?= ($c['tipo_cuenta'] ?? '') === 'AHORROS' ? 'selected' : '' ?>>Ahorros</option>
                <option value="CORRIENTE" <?= ($c['tipo_cuenta'] ?? '') === 'CORRIENTE' ? 'selected' : '' ?>>Corriente</option>
            </select>
        </div>
        <div>
            <label class="etiqueta" for="ct-cuenta-<?= $e($sufijo) ?>">Número de cuenta</label>
            <input class="campo" id="ct-cuenta-<?= $e($sufijo) ?>" type="text" name="numero_cuenta" maxlength="40"
                   value="<?= $e((string) ($c['numero_cuenta'] ?? '')) ?>">
        </div>
        <div class="sm:col-span-3">
            <button class="boton-principal" type="submit">Guardar</button>
        </div>
    </form>
    <?php return (string) ob_get_clean();
};
?>
<h1 class="text-2xl">Contratistas</h1>
<p class="mt-2 texto-suave">
    La ficha se crea con lo que trae la minuta; el contacto, el banco y la
    firma los completa el propio contratista en su primer ingreso al portal.
</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>

<details class="tarjeta mt-4">
    <summary class="cursor-pointer font-semibold texto-titular">Nuevo contratista</summary>
    <?= $formulario([], '/admin/cuentas-cobro/contratistas', 'nuevo') ?>
</details>

<div class="tarjeta mt-4 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left texto-suave">
                <th class="p-2">Documento</th><th class="p-2">Nombre</th><th class="p-2">Celular</th>
                <th class="p-2">Correo</th><th class="p-2">Contratos</th><th class="p-2">Datos completos</th>
                <th class="p-2">Estado</th><th class="p-2"></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($contratistas === []) { ?>
                <tr><td class="p-4 texto-suave" colspan="8">Todavía no hay contratistas registrados.</td></tr>
            <?php } ?>
            <?php foreach ($contratistas as $contratista) { $cid = (int) $contratista['id']; ?>
                <tr class="border-t borde-suave">
                    <td class="p-2 whitespace-nowrap"><?= $e($contratista['tipo_documento'] . ' ' . $contratista['numero_documento']) ?></td>
                    <td class="p-2"><?= $e((string) $contratista['nombre_completo']) ?></td>
                    <td class="p-2"><?= $e((string) ($contratista['celular'] ?? '')) ?></td>
                    <td class="p-2"><?= $e((string) ($contratista['correo'] ?? '')) ?></td>
                    <td class="p-2"><?= (int) $contratista['contratos'] ?></td>
                    <td class="p-2"><?= ((int) $contratista['datos_completos'] === 1) ? 'Sí' : 'Pendientes' ?></td>
                    <td class="p-2"><?= ((int) $contratista['activo'] === 1) ? 'Activo' : 'Inactivo' ?></td>
                    <td class="p-2">
                        <form method="post" action="/admin/cuentas-cobro/contratistas/<?= $cid ?>/activo">
                            <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                            <button class="enlace text-sm" type="submit">
                                <?= ((int) $contratista['activo'] === 1) ? 'Desactivar' : 'Reactivar' ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <tr class="border-t borde-suave">
                    <td class="p-2 pb-4" colspan="8">
                        <details>
                            <summary class="cursor-pointer text-sm texto-suave">Editar ficha</summary>
                            <?= $formulario($contratista, '/admin/cuentas-cobro/contratistas/' . $cid, (string) $cid) ?>
                        </details>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

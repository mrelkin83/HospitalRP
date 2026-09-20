<?php
/**
 * A1 — Contratos de prestación de servicios.
 * Espera: $contratos, $estadoFiltro, $busqueda, $mensaje, $error.
 */
$moneda = static fn (string $v): string => '$ ' . number_format((int) explode('.', $v, 2)[0], 0, ',', '.');
$estados = [
    '' => 'Todos',
    'EN_REVISION' => 'En revisión',
    'ACTIVO' => 'Activos',
    'SUSPENDIDO' => 'Suspendidos',
    'TERMINADO' => 'Terminados',
    'LIQUIDADO' => 'Liquidados',
    'ANULADO' => 'Anulados',
];
?>
<h1 class="text-2xl">Contratos</h1>
<p class="mt-2 texto-suave">
    Cada contrato se carga una sola vez; de aquí salen los tres documentos de
    cada cuenta de cobro sin volver a transcribir nada.
</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>

<div class="mt-4 flex flex-wrap items-end gap-3">
    <form class="flex flex-wrap items-end gap-3" method="get" action="/admin/cuentas-cobro/contratos">
        <div>
            <label class="etiqueta" for="filtro-estado">Estado</label>
            <select class="campo" id="filtro-estado" name="estado">
                <?php foreach ($estados as $valor => $etiqueta) { ?>
                    <option value="<?= $e((string) $valor) ?>" <?= $estadoFiltro === $valor ? 'selected' : '' ?>><?= $e($etiqueta) ?></option>
                <?php } ?>
            </select>
        </div>
        <div>
            <label class="etiqueta" for="filtro-q">Buscar</label>
            <input class="campo" id="filtro-q" type="search" name="q" value="<?= $e($busqueda) ?>"
                   placeholder="Número, contratista o cédula">
        </div>
        <button class="boton-secundario" type="submit">Filtrar</button>
    </form>
    <a class="boton-principal ml-auto" href="/admin/cuentas-cobro/contratos/nuevo">Nuevo contrato</a>
</div>

<div class="tarjeta mt-4 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left texto-suave">
                <th class="p-2">Contrato</th>
                <th class="p-2">Contratista</th>
                <th class="p-2">Valor</th>
                <th class="p-2">Suscrito</th>
                <th class="p-2">Acta inicio</th>
                <th class="p-2">Supervisor</th>
                <th class="p-2">Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($contratos === []) { ?>
                <tr><td class="p-4 texto-suave" colspan="7">Todavía no hay contratos. Cree el primero con «Nuevo contrato».</td></tr>
            <?php } ?>
            <?php foreach ($contratos as $contrato) { ?>
                <tr class="border-t borde-suave">
                    <td class="p-2">
                        <a class="font-semibold enlace" href="/admin/cuentas-cobro/contratos/<?= (int) $contrato['id'] ?>">
                            <?= $e((string) $contrato['numero']) ?>
                        </a>
                    </td>
                    <td class="p-2">
                        <?= $e((string) $contrato['contratista_nombre']) ?>
                        <span class="texto-suave">· <?= $e((string) $contrato['numero_documento']) ?></span>
                    </td>
                    <td class="p-2 whitespace-nowrap"><?= $e($moneda((string) $contrato['valor_total'])) ?></td>
                    <td class="p-2 whitespace-nowrap"><?= $e((string) $contrato['fecha_suscripcion']) ?></td>
                    <td class="p-2 whitespace-nowrap"><?= $e((string) ($contrato['fecha_acta_inicio'] ?? 'Pendiente')) ?></td>
                    <td class="p-2"><?= $e((string) ($contrato['supervisor_nombre'] ?? 'Sin asignar')) ?></td>
                    <td class="p-2"><?= $e((string) $contrato['estado']) ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<?php
/**
 * A8 — Reportes del módulo, cada bloque con su botón de CSV.
 * Espera: $tiempos, $estados, $sinActa, $vencidas.
 */
$moneda = static fn (mixed $v): string => $v === null ? '' : '$ ' . number_format((int) explode('.', (string) $v, 2)[0], 0, ',', '.');
?>
<h1 class="text-2xl">Reportes de cuentas de cobro</h1>
<p class="mt-2 texto-suave">Lo que Gerencia pregunta, listo para exportar.</p>

<section class="tarjeta mt-4">
    <div class="flex items-center justify-between gap-3">
        <h2 class="text-lg font-semibold texto-titular">Tiempo de revisión por supervisor</h2>
        <a class="boton-secundario" href="/admin/cuentas-cobro/reportes?csv=tiempos">Exportar CSV</a>
    </div>
    <div class="mt-3 overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left texto-suave">
                <th class="p-2">Supervisor</th><th class="p-2">Aprobadas</th>
                <th class="p-2">Promedio (días)</th><th class="p-2">Máximo (días)</th>
            </tr></thead>
            <tbody>
                <?php if ($tiempos === []) { ?><tr><td class="p-3 texto-suave" colspan="4">Aún no hay cuentas aprobadas.</td></tr><?php } ?>
                <?php foreach ($tiempos as $fila) { ?>
                    <tr class="border-t borde-suave">
                        <td class="p-2"><?= $e($fila['supervisor'] . ' — ' . $fila['cargo']) ?></td>
                        <td class="p-2"><?= (int) $fila['cuentas_aprobadas'] ?></td>
                        <td class="p-2"><?= $e((string) $fila['dias_promedio']) ?></td>
                        <td class="p-2"><?= $e((string) $fila['dias_maximo']) ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<section class="tarjeta mt-4">
    <div class="flex items-center justify-between gap-3">
        <h2 class="text-lg font-semibold texto-titular">Cuentas por estado y mes</h2>
        <a class="boton-secundario" href="/admin/cuentas-cobro/reportes?csv=estados">Exportar CSV</a>
    </div>
    <div class="mt-3 overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left texto-suave">
                <th class="p-2">Mes</th><th class="p-2">Estado</th><th class="p-2">Cuentas</th><th class="p-2">Valor</th>
            </tr></thead>
            <tbody>
                <?php if ($estados === []) { ?><tr><td class="p-3 texto-suave" colspan="4">Sin cuentas registradas.</td></tr><?php } ?>
                <?php foreach ($estados as $fila) { ?>
                    <tr class="border-t borde-suave">
                        <td class="p-2"><?= $e((string) $fila['mes']) ?></td>
                        <td class="p-2"><?= $e((string) $fila['estado']) ?></td>
                        <td class="p-2"><?= (int) $fila['cuentas'] ?></td>
                        <td class="p-2 whitespace-nowrap"><?= $e($moneda($fila['valor_total'])) ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<section class="tarjeta mt-4">
    <div class="flex items-center justify-between gap-3">
        <h2 class="text-lg font-semibold texto-titular">Contratos activos sin acta de inicio</h2>
        <a class="boton-secundario" href="/admin/cuentas-cobro/reportes?csv=sin-acta">Exportar CSV</a>
    </div>
    <div class="mt-3 overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left texto-suave">
                <th class="p-2">Contrato</th><th class="p-2">Suscrito</th><th class="p-2">Contratista</th><th class="p-2">Supervisor</th>
            </tr></thead>
            <tbody>
                <?php if ($sinActa === []) { ?><tr><td class="p-3 texto-suave" colspan="4">Todos los contratos activos tienen acta.</td></tr><?php } ?>
                <?php foreach ($sinActa as $fila) { ?>
                    <tr class="border-t borde-suave">
                        <td class="p-2 font-semibold"><?= $e((string) $fila['numero']) ?></td>
                        <td class="p-2"><?= $e((string) $fila['fecha_suscripcion']) ?></td>
                        <td class="p-2"><?= $e((string) $fila['contratista']) ?></td>
                        <td class="p-2"><?= $e((string) ($fila['supervisor'] ?? 'Sin asignar')) ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<section class="tarjeta mt-4">
    <div class="flex items-center justify-between gap-3">
        <h2 class="text-lg font-semibold texto-titular">Cuotas vencidas sin cuenta presentada</h2>
        <a class="boton-secundario" href="/admin/cuentas-cobro/reportes?csv=vencidas">Exportar CSV</a>
    </div>
    <div class="mt-3 overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left texto-suave">
                <th class="p-2">Contrato</th><th class="p-2">Contratista</th><th class="p-2">Cuota</th>
                <th class="p-2">Periodo</th><th class="p-2">Valor</th><th class="p-2">Días vencida</th>
            </tr></thead>
            <tbody>
                <?php if ($vencidas === []) { ?><tr><td class="p-3 texto-suave" colspan="6">Ninguna cuota vencida sin cuenta. Buen síntoma.</td></tr><?php } ?>
                <?php foreach ($vencidas as $fila) { ?>
                    <tr class="border-t borde-suave">
                        <td class="p-2 font-semibold"><?= $e((string) $fila['contrato']) ?></td>
                        <td class="p-2"><?= $e((string) $fila['contratista']) ?></td>
                        <td class="p-2"><?= (int) $fila['numero_cuota'] ?></td>
                        <td class="p-2 whitespace-nowrap"><?= $e($fila['periodo_desde'] . ' a ' . $fila['periodo_hasta']) ?></td>
                        <td class="p-2 whitespace-nowrap"><?= $e($moneda($fila['valor'])) ?></td>
                        <td class="p-2"><?= (int) $fila['dias_vencida'] ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

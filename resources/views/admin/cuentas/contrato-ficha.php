<?php
/**
 * A1 — Ficha del contrato: datos, imputaciones, cronograma, obligaciones y
 * las acciones de la fase 1 (activar, acta de inicio, CDP/RP, anular).
 * Espera: $contrato, $imputaciones, $obligaciones, $pagos, $diferencia,
 * $mensaje, $error, $tokenCsrf.
 */
$moneda = static fn (string $v): string => '$ ' . number_format((int) explode('.', $v, 2)[0], 0, ',', '.');
$id = (int) $contrato['id'];
$enRevision = $contrato['estado'] === 'EN_REVISION';
?>
<h1 class="text-2xl">Contrato <?= $e((string) $contrato['numero']) ?></h1>
<p class="mt-1 texto-suave">
    <?= $e((string) $contrato['contratista_nombre']) ?> ·
    <?= $e((string) $contrato['tipo_documento']) ?> <?= $e((string) $contrato['numero_documento']) ?> ·
    Estado: <strong><?= $e((string) $contrato['estado']) ?></strong>
</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>
<?php if ($diferencia !== 0) { ?>
    <div class="alerta alerta-error mt-4" role="alert">
        El cronograma no suma el valor del contrato: diferencia de <?= $e($moneda((string) abs($diferencia) . '.00')) ?>.
        Mientras no cuadre, el contrato no se puede activar.
    </div>
<?php } ?>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    <section class="tarjeta">
        <h2 class="text-lg font-semibold texto-titular">Datos del contrato</h2>
        <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
            <dt class="texto-suave">Objeto</dt><dd class="col-span-2 -mt-1"><?= $e((string) $contrato['objeto']) ?></dd>
            <dt class="texto-suave">Valor total</dt><dd><?= $e($moneda((string) $contrato['valor_total'])) ?></dd>
            <dt class="texto-suave">Suscrito</dt><dd><?= $e((string) $contrato['fecha_suscripcion']) ?></dd>
            <dt class="texto-suave">Plazo</dt><dd><?= $e((string) $contrato['plazo_texto']) ?></dd>
            <dt class="texto-suave">Periodos</dt><dd><?= $e((string) $contrato['modalidad_periodos']) ?></dd>
            <dt class="texto-suave">Acta de inicio</dt><dd><?= $e((string) ($contrato['fecha_acta_inicio'] ?? 'Pendiente')) ?></dd>
            <dt class="texto-suave">Terminación</dt><dd><?= $e((string) ($contrato['fecha_terminacion'] ?? 'Se calcula con el acta')) ?></dd>
            <dt class="texto-suave">Supervisor</dt><dd><?= $e((string) ($contrato['supervisor_nombre'] ?? 'Sin asignar')) ?></dd>
            <dt class="texto-suave">En letras</dt><dd class="col-span-2 -mt-1"><?= $e((string) $contrato['valor_total_letras']) ?></dd>
        </dl>
        <?php if ($enRevision) { ?>
            <div class="mt-4 flex flex-wrap gap-3">
                <a class="boton-secundario" href="/admin/cuentas-cobro/contratos/<?= $id ?>/editar">Editar</a>
                <form method="post" action="/admin/cuentas-cobro/contratos/<?= $id ?>/activar"
                      onsubmit="return confirm('¿Activar el contrato? Desde ese momento el contratista puede presentar cuentas.');">
                    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                    <button class="boton-principal" type="submit">Activar</button>
                </form>
            </div>
        <?php } ?>
    </section>

    <section class="tarjeta">
        <h2 class="text-lg font-semibold texto-titular">Acta de inicio</h2>
        <p class="mt-1 text-sm texto-suave">
            Con el acta, el sistema fija las fechas de cada cuota y la
            terminación (§8.1 del módulo).
        </p>
        <form class="mt-3 flex flex-wrap items-end gap-3" method="post"
              action="/admin/cuentas-cobro/contratos/<?= $id ?>/acta">
            <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
            <div>
                <label class="etiqueta" for="acta-fecha">Fecha del acta</label>
                <input class="campo" id="acta-fecha" type="date" name="fecha_acta_inicio" required
                       value="<?= $e((string) ($contrato['fecha_acta_inicio'] ?? '')) ?>">
            </div>
            <button class="boton-principal" type="submit">Registrar y calcular periodos</button>
        </form>

        <h2 class="mt-6 text-lg font-semibold texto-titular">Anular</h2>
        <form class="mt-2 flex flex-wrap items-end gap-3" method="post"
              action="/admin/cuentas-cobro/contratos/<?= $id ?>/anular"
              onsubmit="return confirm('¿Anular este contrato? La acción queda en la auditoría.');">
            <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
            <div class="grow">
                <label class="etiqueta" for="anular-motivo">Motivo</label>
                <input class="campo" id="anular-motivo" type="text" name="motivo" maxlength="300" required>
            </div>
            <button class="boton-secundario" type="submit">Anular contrato</button>
        </form>
    </section>
</div>

<section class="tarjeta mt-4">
    <h2 class="text-lg font-semibold texto-titular">Cronograma de pagos</h2>
    <div class="mt-3 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left texto-suave">
                    <th class="p-2">N°</th><th class="p-2">Descripción</th><th class="p-2">Tipo</th>
                    <th class="p-2">Desde</th><th class="p-2">Hasta</th><th class="p-2">Días</th><th class="p-2">Valor</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pagos as $pago) { ?>
                    <tr class="border-t borde-suave">
                        <td class="p-2"><?= (int) $pago['numero_cuota'] ?></td>
                        <td class="p-2"><?= $e((string) $pago['descripcion']) ?></td>
                        <td class="p-2"><?= $pago['tipo'] === 'PARCIAL' ? 'Parcial' : 'Completa' ?></td>
                        <td class="p-2 whitespace-nowrap"><?= $e((string) ($pago['periodo_desde'] ?? '—')) ?></td>
                        <td class="p-2 whitespace-nowrap"><?= $e((string) ($pago['periodo_hasta'] ?? '—')) ?></td>
                        <td class="p-2"><?= $pago['dias'] === null ? '—' : (int) $pago['dias'] ?></td>
                        <td class="p-2 whitespace-nowrap"><?= $e($moneda((string) $pago['valor'])) ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<section class="tarjeta mt-4">
    <h2 class="text-lg font-semibold texto-titular">Imputación presupuestal (CDP y RP)</h2>
    <div class="mt-3 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left texto-suave">
                    <th class="p-2">Tipo</th><th class="p-2">Número</th><th class="p-2">Fecha</th>
                    <th class="p-2">Rubro</th><th class="p-2">Recurso</th><th class="p-2">Valor</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($imputaciones === []) { ?>
                    <tr><td class="p-3 texto-suave" colspan="6">Sin CDP ni RP registrados. El CDP viene en la minuta; el RP se expide después de la firma.</td></tr>
                <?php } ?>
                <?php foreach ($imputaciones as $imputacion) { ?>
                    <tr class="border-t borde-suave">
                        <td class="p-2 font-semibold"><?= $e((string) $imputacion['tipo']) ?></td>
                        <td class="p-2"><?= $e((string) $imputacion['numero']) ?></td>
                        <td class="p-2 whitespace-nowrap"><?= $e((string) $imputacion['fecha']) ?></td>
                        <td class="p-2"><?= $e($imputacion['codigo_rubro'] . ' ' . $imputacion['nombre_rubro']) ?></td>
                        <td class="p-2"><?= $e((string) ($imputacion['recurso'] ?? '')) ?></td>
                        <td class="p-2 whitespace-nowrap"><?= $e($moneda((string) $imputacion['valor'])) ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <details class="mt-3">
        <summary class="cursor-pointer font-semibold texto-titular">Registrar CDP o RP</summary>
        <form class="mt-3 grid gap-3 sm:grid-cols-3" method="post"
              action="/admin/cuentas-cobro/contratos/<?= $id ?>/imputaciones">
            <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
            <div>
                <label class="etiqueta" for="imp-tipo">Tipo</label>
                <select class="campo" id="imp-tipo" name="tipo">
                    <option value="CDP">CDP</option>
                    <option value="RP">RP</option>
                </select>
            </div>
            <div>
                <label class="etiqueta" for="imp-numero">Número</label>
                <input class="campo" id="imp-numero" type="text" name="numero" required maxlength="30" placeholder="1611">
            </div>
            <div>
                <label class="etiqueta" for="imp-fecha">Fecha</label>
                <input class="campo" id="imp-fecha" type="date" name="fecha" required>
            </div>
            <div>
                <label class="etiqueta" for="imp-rubro">Código del rubro</label>
                <input class="campo" id="imp-rubro" type="text" name="codigo_rubro" required maxlength="40"
                       placeholder="2.1.2.02.02.008-83118">
            </div>
            <div class="sm:col-span-2">
                <label class="etiqueta" for="imp-nombre">Nombre del rubro</label>
                <input class="campo" id="imp-nombre" type="text" name="nombre_rubro" required maxlength="200">
            </div>
            <div class="sm:col-span-2">
                <label class="etiqueta" for="imp-recurso">Recurso</label>
                <input class="campo" id="imp-recurso" type="text" name="recurso" maxlength="120"
                       placeholder="1.2.3.2.09 VENTA DE BIENES Y SERVICIOS">
            </div>
            <div>
                <label class="etiqueta" for="imp-valor">Valor (pesos)</label>
                <input class="campo" id="imp-valor" type="number" name="valor" required min="1">
            </div>
            <?php $adiciones = array_filter($novedades ?? [], static fn (array $n): bool => $n['tipo'] === 'ADICION'); ?>
            <?php if ($adiciones !== []) { ?>
                <div>
                    <label class="etiqueta" for="imp-novedad">Pertenece a la adición</label>
                    <select class="campo" id="imp-novedad" name="novedad_id">
                        <option value="0">Al contrato inicial</option>
                        <?php foreach ($adiciones as $adicion) { ?>
                            <option value="<?= (int) $adicion['id'] ?>">
                                Adición del <?= $e((string) $adicion['fecha']) ?> (<?= $e($moneda((string) $adicion['valor'])) ?>)
                            </option>
                        <?php } ?>
                    </select>
                </div>
            <?php } ?>
            <div class="sm:col-span-3">
                <button class="boton-principal" type="submit">Registrar</button>
            </div>
        </form>
    </details>
</section>

<section class="tarjeta mt-4">
    <h2 class="text-lg font-semibold texto-titular">Obligaciones</h2>
    <ol class="mt-3 list-decimal space-y-2 pl-6 text-sm">
        <?php foreach ($obligaciones as $obligacion) { ?>
            <?php if ($obligacion['grupo'] !== 'ESPECIFICA') { continue; } ?>
            <li><?= $e((string) $obligacion['texto']) ?></li>
        <?php } ?>
    </ol>
    <?php $delSgic = array_filter($obligaciones, static fn (array $o): bool => $o['grupo'] === 'SGIC'); ?>
    <?php if ($delSgic !== []) { ?>
        <details class="mt-3">
            <summary class="cursor-pointer font-semibold texto-titular">Obligaciones del SGIC (<?= count($delSgic) ?>)</summary>
            <ol class="mt-2 list-decimal space-y-2 pl-6 text-sm">
                <?php foreach ($delSgic as $obligacion) { ?>
                    <li><?= $e((string) $obligacion['texto']) ?></li>
                <?php } ?>
            </ol>
        </details>
    <?php } ?>
</section>

<section class="tarjeta mt-4">
    <h2 class="text-lg font-semibold texto-titular">Novedades del contrato</h2>
    <div class="mt-3 overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left texto-suave">
                <th class="p-2">Tipo</th><th class="p-2">Fecha</th><th class="p-2">Detalle</th><th class="p-2">Registró</th>
            </tr></thead>
            <tbody>
                <?php if (($novedades ?? []) === []) { ?>
                    <tr><td class="p-3 texto-suave" colspan="4">Sin novedades registradas.</td></tr>
                <?php } ?>
                <?php foreach ($novedades ?? [] as $novedad) { ?>
                    <tr class="border-t borde-suave">
                        <td class="p-2 font-semibold"><?= $e((string) $novedad['tipo']) ?></td>
                        <td class="p-2 whitespace-nowrap"><?= $e($novedad['fecha'] . ($novedad['fecha_fin'] !== null ? ' a ' . $novedad['fecha_fin'] : '')) ?></td>
                        <td class="p-2">
                            <?php if ($novedad['valor'] !== null) { ?><?= $e($moneda((string) $novedad['valor'])) ?> · <?php } ?>
                            <?php if ((int) ($novedad['plazo_meses'] ?? 0) > 0 || (int) ($novedad['plazo_dias'] ?? 0) > 0) { ?>
                                <?= (int) $novedad['plazo_meses'] ?> meses, <?= (int) $novedad['plazo_dias'] ?> días ·
                            <?php } ?>
                            <?= $e((string) ($novedad['descripcion'] ?? '')) ?>
                        </td>
                        <td class="p-2"><?= $e((string) ($novedad['registrado_por'] ?? '')) ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <details class="mt-3">
        <summary class="cursor-pointer font-semibold texto-titular">Registrar novedad</summary>
        <form class="mt-3 grid gap-3 sm:grid-cols-3" method="post"
              action="/admin/cuentas-cobro/contratos/<?= $id ?>/novedades"
              onsubmit="return confirm('Las novedades recalculan el cronograma futuro. ¿Registrar?');">
            <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
            <div>
                <label class="etiqueta" for="nov-tipo">Tipo</label>
                <select class="campo" id="nov-tipo" name="tipo">
                    <option value="ADICION">Adición (valor y plazo)</option>
                    <option value="PRORROGA">Prórroga (solo plazo)</option>
                    <option value="SUSPENSION">Suspensión</option>
                    <option value="REINICIO">Reinicio</option>
                    <option value="TERMINACION_ANTICIPADA">Terminación anticipada</option>
                </select>
            </div>
            <div>
                <label class="etiqueta" for="nov-fecha">Fecha del acto</label>
                <input class="campo" id="nov-fecha" type="date" name="fecha" value="<?= date('Y-m-d') ?>">
            </div>
            <div>
                <label class="etiqueta" for="nov-valor">Valor (solo adición)</label>
                <input class="campo" id="nov-valor" type="number" name="valor" min="0">
            </div>
            <div>
                <label class="etiqueta" for="nov-meses">Plazo meses</label>
                <input class="campo" id="nov-meses" type="number" name="plazo_meses" min="0" value="0">
            </div>
            <div>
                <label class="etiqueta" for="nov-dias">Plazo días</label>
                <input class="campo" id="nov-dias" type="number" name="plazo_dias" min="0" value="0">
            </div>
            <div></div>
            <div>
                <label class="etiqueta" for="nov-desde">Suspensión: desde</label>
                <input class="campo" id="nov-desde" type="date" name="fecha_desde">
            </div>
            <div>
                <label class="etiqueta" for="nov-hasta">Suspensión: reanuda el</label>
                <input class="campo" id="nov-hasta" type="date" name="fecha_hasta">
            </div>
            <div class="sm:col-span-3">
                <label class="etiqueta" for="nov-descripcion">Descripción</label>
                <input class="campo" id="nov-descripcion" type="text" name="descripcion" maxlength="400">
            </div>
            <div class="sm:col-span-3">
                <button class="boton-principal" type="submit">Registrar novedad</button>
            </div>
        </form>
    </details>
</section>

<section class="tarjeta mt-4">
    <h2 class="text-lg font-semibold texto-titular">Cuentas de este contrato</h2>
    <div class="mt-3 overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left texto-suave">
                <th class="p-2">N°</th><th class="p-2">Radicado</th><th class="p-2">Periodo</th>
                <th class="p-2">Valor</th><th class="p-2">Estado</th><th class="p-2"></th>
            </tr></thead>
            <tbody>
                <?php if (($cuentasContrato ?? []) === []) { ?>
                    <tr><td class="p-3 texto-suave" colspan="6">El contratista aún no presenta cuentas.</td></tr>
                <?php } ?>
                <?php foreach ($cuentasContrato ?? [] as $cuenta) { ?>
                    <tr class="border-t borde-suave">
                        <td class="p-2"><?= (int) $cuenta['consecutivo'] ?></td>
                        <td class="p-2"><?= $e((string) ($cuenta['radicado'] ?? 'Borrador')) ?></td>
                        <td class="p-2 whitespace-nowrap"><?= $e($cuenta['periodo_desde'] . ' a ' . $cuenta['periodo_hasta']) ?></td>
                        <td class="p-2 whitespace-nowrap"><?= $e($moneda((string) $cuenta['valor'])) ?></td>
                        <td class="p-2"><?= $e((string) $cuenta['estado']) ?></td>
                        <td class="p-2">
                            <?php if ($cuenta['estado'] !== 'ANULADA') { ?>
                                <form class="flex flex-wrap items-end gap-2" method="post"
                                      action="/admin/cuentas-cobro/cuentas/<?= (int) $cuenta['id'] ?>/anular"
                                      onsubmit="return confirm('¿Anular esta cuenta? No cuenta para consecutivos ni acumulados.');">
                                    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                                    <input class="campo" type="text" name="motivo" maxlength="300" required
                                           placeholder="Motivo de la anulación" aria-label="Motivo de la anulación">
                                    <button class="enlace text-sm" type="submit">Anular</button>
                                </form>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

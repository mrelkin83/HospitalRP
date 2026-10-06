<?php
/**
 * /transparencia/ejecucion-contractual
 * Espera: $contratos (list<array<string,mixed>>), $resumen (array).
 *
 * ITA 5.3, seis literales por contrato: fecha de inicio y finalización (a),
 * valor (b), porcentaje de ejecución (c), recursos desembolsados (d),
 * pendientes de ejecutar (e) y cantidad de otrosíes y adiciones (f).
 *
 * No aparece el nombre del contratista: el criterio no lo pide, y la identidad
 * y los honorarios se publican una sola vez, en el directorio de servidores
 * públicos (criterio 3.5.j).
 */
$pesos = static fn (float|string|null $v): string => '$' . number_format((float) $v, 0, ',', '.');
?>
<div class="mx-auto max-w-6xl px-4 py-10">
    <nav aria-label="Miga de pan" class="text-sm texto-suave">
        <a href="/">Inicio</a> › <a href="/transparencia">Transparencia</a>
    </nav>

    <h1 class="mt-4">Ejecución de contratos</h1>
    <p class="mt-3 texto-suave max-w-prose">
        Estado de ejecución de los contratos de la entidad. Los procesos
        contractuales completos, con sus documentos, se publican en
        <a href="https://www.colombiacompra.gov.co/secop/secop-ii" target="_blank" rel="noopener">SECOP II</a>.
    </p>

    <?php if ($contratos === []) { ?>
        <p class="mt-8 texto-suave">No hay contratos con ejecución publicable en este momento.</p>
    <?php } else { ?>
        <dl class="mt-6 grid gap-4 sm:grid-cols-4">
            <div class="tarjeta">
                <dt class="text-sm texto-suave">Contratos</dt>
                <dd class="text-xl font-bold"><?= (int) $resumen['contratos'] ?></dd>
            </div>
            <div class="tarjeta">
                <dt class="text-sm texto-suave">Valor total</dt>
                <dd class="text-xl font-bold"><?= $e($pesos($resumen['valor'])) ?></dd>
            </div>
            <div class="tarjeta">
                <dt class="text-sm texto-suave">Desembolsado</dt>
                <dd class="text-xl font-bold"><?= $e($pesos($resumen['pagado'])) ?></dd>
            </div>
            <div class="tarjeta">
                <dt class="text-sm texto-suave">Pendiente de ejecutar</dt>
                <dd class="text-xl font-bold"><?= $e($pesos($resumen['pendiente'])) ?></dd>
            </div>
        </dl>

        <p class="mt-6">
            <a class="boton boton-secundario" href="/transparencia/ejecucion-contractual.csv">
                Descargar en CSV
            </a>
        </p>

        <?php /* Escritorio: tabla. El desborde se encierra en su contenedor
                 para que el cuerpo de la página no ruede en horizontal. */ ?>
        <div class="mt-6 hidden overflow-x-auto md:block">
            <table class="w-full border-collapse text-sm">
                <caption class="sr-only">Estado de ejecución de los contratos</caption>
                <thead>
                    <tr>
                        <th scope="col" class="border-b-2 px-3 py-2 text-left">Contrato</th>
                        <th scope="col" class="border-b-2 px-3 py-2 text-left">Inicio</th>
                        <th scope="col" class="border-b-2 px-3 py-2 text-left">Finalización</th>
                        <th scope="col" class="border-b-2 px-3 py-2 text-right">Valor</th>
                        <th scope="col" class="border-b-2 px-3 py-2 text-right">Ejecución</th>
                        <th scope="col" class="border-b-2 px-3 py-2 text-right">Desembolsado</th>
                        <th scope="col" class="border-b-2 px-3 py-2 text-right">Pendiente</th>
                        <th scope="col" class="border-b-2 px-3 py-2 text-right">Adiciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contratos as $c) { ?>
                        <tr>
                            <th scope="row" class="border-b px-3 py-2 text-left font-semibold">
                                <?= $e((string) $c['numero']) ?>
                                <span class="block font-normal texto-suave"><?= $e((string) $c['tipo_contrato']) ?></span>
                            </th>
                            <td class="border-b px-3 py-2"><?= $e((string) ($c['fecha_acta_inicio'] ?? '—')) ?></td>
                            <td class="border-b px-3 py-2"><?= $e((string) ($c['fecha_terminacion'] ?? '—')) ?></td>
                            <td class="border-b px-3 py-2 text-right"><?= $e($pesos($c['valor_total'])) ?></td>
                            <td class="border-b px-3 py-2 text-right"><?= $e(number_format((float) $c['porcentaje'], 1, ',', '.')) ?> %</td>
                            <td class="border-b px-3 py-2 text-right"><?= $e($pesos($c['pagado'])) ?></td>
                            <td class="border-b px-3 py-2 text-right"><?= $e($pesos($c['pendiente'])) ?></td>
                            <td class="border-b px-3 py-2 text-right">
                                <?= (int) $c['novedades'] ?>
                                <?php if ((float) $c['valor_adiciones'] > 0) { ?>
                                    <span class="block texto-suave"><?= $e($pesos($c['valor_adiciones'])) ?></span>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <?php /* Móvil: una ficha por contrato (CLAUDE.md §6). */ ?>
        <ul class="mt-6 flex flex-col gap-4 md:hidden">
            <?php foreach ($contratos as $c) { ?>
                <li>
                    <article class="tarjeta">
                        <h2 class="text-base">Contrato <?= $e((string) $c['numero']) ?></h2>
                        <p class="text-sm texto-suave"><?= $e((string) $c['tipo_contrato']) ?></p>
                        <dl class="mt-3 flex flex-col gap-1 text-sm">
                            <div><dt class="inline font-semibold">Inicio:</dt> <dd class="inline"><?= $e((string) ($c['fecha_acta_inicio'] ?? '—')) ?></dd></div>
                            <div><dt class="inline font-semibold">Finalización:</dt> <dd class="inline"><?= $e((string) ($c['fecha_terminacion'] ?? '—')) ?></dd></div>
                            <div><dt class="inline font-semibold">Valor:</dt> <dd class="inline"><?= $e($pesos($c['valor_total'])) ?></dd></div>
                            <div><dt class="inline font-semibold">Ejecución:</dt> <dd class="inline"><?= $e(number_format((float) $c['porcentaje'], 1, ',', '.')) ?> %</dd></div>
                            <div><dt class="inline font-semibold">Desembolsado:</dt> <dd class="inline"><?= $e($pesos($c['pagado'])) ?></dd></div>
                            <div><dt class="inline font-semibold">Pendiente:</dt> <dd class="inline"><?= $e($pesos($c['pendiente'])) ?></dd></div>
                            <div><dt class="inline font-semibold">Otrosíes y adiciones:</dt> <dd class="inline"><?= (int) $c['novedades'] ?><?php if ((float) $c['valor_adiciones'] > 0) { ?> por <?= $e($pesos($c['valor_adiciones'])) ?><?php } ?></dd></div>
                        </dl>
                    </article>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>

    <p class="mt-8 text-sm texto-suave">
        Publicado conforme al numeral 5.3 del Anexo 2 de la Resolución 1519 de 2020.
        La identidad y los honorarios de los contratistas se publican en el
        <a href="/servidores-publicos">directorio de servidores públicos</a>.
    </p>
</div>

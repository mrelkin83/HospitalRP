<?php
/**
 * /estadisticas — información estadística de la entidad.
 * Espera: $grupos (array<string, array{titulo: string, indicadores: list<array>}>).
 *
 * Cifras agregadas que publica el hospital. Aquí no hay, ni va a haber, dato
 * de un paciente concreto (CLAUDE.md §2.6).
 */

use App\Core\Support\Fechas;

/** Cifra en formato colombiano: 1.234,5 — y sin decimales cuando es entera. */
$cifra = static fn (float $valor): string => $valor === (float) (int) $valor
    ? number_format($valor, 0, ',', '.')
    : number_format($valor, 1, ',', '.');

/** Porcentaje cuantizado a pasos de 5 para la clase .barra-N (ver app.css). */
$paso = static fn (float $parte, float $total): int => $total <= 0.0
    ? 0
    : (int) (round(min(100.0, max(0.0, $parte / $total * 100)) / 5) * 5);
?>
<div class="mx-auto max-w-7xl px-4 py-10">

    <div class="relative overflow-hidden rounded-[2.5rem] cabecera-seccion p-10 text-white shadow-2xl border border-white/10">
        <span class="mancha h-64 w-64 -right-16 -top-16 bg-[color:var(--color-marca-cian)] opacity-30 blur-3xl" aria-hidden="true"></span>
        <div class="relative z-10 max-w-3xl">
            <span class="text-xs font-bold uppercase tracking-widest text-white bg-white/10 px-3 py-1 rounded-full border border-white/10">Datos abiertos</span>
            <h1 class="text-white mt-4 text-4xl lg:text-5xl font-extrabold tracking-tight">Información estadística</h1>
            <p class="mt-4 text-lg text-primario-100 leading-relaxed">
                Cómo va la contratación, cómo está la ocupación hospitalaria y qué
                dicen las estadísticas vitales. Cada cifra indica el periodo al que
                corresponde y de dónde sale.
            </p>
        </div>
    </div>

    <?php if ($grupos === []) { ?>

        <p class="mt-8 tarjeta texto-suave">
            Todavía no hay indicadores publicados. Mientras tanto puedes consultar
            la <a href="/transparencia">sección de transparencia</a>, donde están
            los informes de gestión y la contratación.
        </p>

    <?php } else { ?>

        <nav class="mt-8 flex flex-wrap gap-2" aria-label="Grupos de indicadores">
            <?php foreach ($grupos as $codigo => $grupo) { ?>
                <a class="boton boton-secundario text-sm py-2 px-4 rounded-xl" href="#<?= $e($codigo) ?>"><?= $e($grupo['titulo']) ?></a>
            <?php } ?>
        </nav>

        <?php foreach ($grupos as $codigo => $grupo) { ?>
            <section class="mt-12 scroll-mt-24" id="<?= $e($codigo) ?>">
                <h2 class="pestana text-xl sm:text-2xl"><?= $e($grupo['titulo']) ?></h2>

                <ul class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <?php foreach ($grupo['indicadores'] as $indicador) {
                        $valor = (float) $indicador['valor'];
                        $meta = $indicador['meta'] === null ? null : (float) $indicador['meta'];
                        $unidad = (string) ($indicador['unidad'] ?? '');
                        $serie = $indicador['serie'];
                        $previo = count($serie) >= 2 ? $serie[count($serie) - 2] : null;
                        ?>
                        <li class="tarjeta bg-[color:var(--superficie)] p-6 shadow-lg border-primario-100/50 dark:border-primario-700/50 flex flex-col">
                            <h3 class="text-sm font-semibold texto-suave leading-snug"><?= $e((string) $indicador['nombre']) ?></h3>

                            <p class="mt-3 flex items-baseline gap-2">
                                <span class="text-4xl font-extrabold texto-titular tabular-nums"><?= $e($cifra($valor)) ?></span>
                                <?php if ($unidad !== '') { ?>
                                    <span class="text-sm texto-suave"><?= $e($unidad) ?></span>
                                <?php } ?>
                            </p>

                            <p class="mt-1 text-xs texto-suave">
                                Periodo: <?= $e(Fechas::mesYAnio((string) $indicador['periodo'])) ?>
                            </p>

                            <?php if ($meta !== null && $meta > 0.0) { ?>
                                <?php /* La barra es orientativa —el ancho va en pasos de 5%
                                         porque la CSP del sitio público prohíbe el estilo
                                         inline—; la cifra exacta ya está arriba en texto. */ ?>
                                <div class="mt-4 barra-fondo" role="presentation">
                                    <span class="barra barra-<?= $paso($valor, $meta) ?>"></span>
                                </div>
                                <p class="mt-2 text-xs texto-suave">
                                    Meta del periodo: <?= $e($cifra($meta)) ?><?= $unidad !== '' ? ' ' . $e($unidad) : '' ?>
                                </p>
                            <?php } ?>

                            <?php if ($previo !== null) {
                                $diferencia = $valor - $previo['valor'];
                                ?>
                                <p class="mt-3 text-xs texto-suave">
                                    <?php if ($diferencia > 0.0) { ?>
                                        &uarr; <?= $e($cifra($diferencia)) ?> más
                                    <?php } elseif ($diferencia < 0.0) { ?>
                                        &darr; <?= $e($cifra(abs($diferencia))) ?> menos
                                    <?php } else { ?>
                                        Sin cambio
                                    <?php } ?>
                                    frente a <?= $e(Fechas::mesYAnio($previo['periodo'])) ?>
                                </p>
                            <?php } ?>

                            <?php if (count($serie) >= 2) { ?>
                                <?php /* La serie va en tabla y no en gráfico: se lee igual con
                                         lector de pantalla, no gasta JavaScript y deja copiar
                                         el dato. */ ?>
                                <details class="mt-4">
                                    <summary class="text-xs cursor-pointer">Ver la serie</summary>
                                    <table class="mt-2 w-full text-xs">
                                        <caption class="sr-only">Serie histórica de <?= $e((string) $indicador['nombre']) ?></caption>
                                        <thead>
                                            <tr class="texto-suave text-left">
                                                <th scope="col" class="font-medium py-1">Periodo</th>
                                                <th scope="col" class="font-medium py-1 text-right">Valor</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_reverse($serie) as $punto) { ?>
                                                <tr class="border-t border-primario-100/50 dark:border-primario-700/50">
                                                    <td class="py-1"><?= $e(Fechas::mesYAnio($punto['periodo'])) ?></td>
                                                    <td class="py-1 text-right tabular-nums"><?= $e($cifra($punto['valor'])) ?></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </details>
                            <?php } ?>

                            <?php if (($indicador['nota'] ?? '') !== '') { ?>
                                <p class="mt-3 text-xs texto-suave"><?= $e((string) $indicador['nota']) ?></p>
                            <?php } ?>

                            <?php if (($indicador['fuente'] ?? '') !== '') { ?>
                                <p class="mt-auto pt-4 text-[0.7rem] texto-suave">
                                    Fuente: <?= $e((string) $indicador['fuente']) ?>
                                </p>
                            <?php } ?>
                        </li>
                    <?php } ?>
                </ul>
            </section>
        <?php } ?>

    <?php } ?>

    <p class="mt-12 text-sm texto-suave max-w-prose">
        ¿Necesitas una cifra que no está publicada? Puedes pedirla como solicitud
        de información en <a href="/atencion-ciudadania/tramites">nuestros trámites</a>: el hospital
        debe responderte en los términos de la Ley 1712 de 2014.
    </p>
</div>

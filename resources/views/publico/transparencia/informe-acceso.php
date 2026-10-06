<?php
/**
 * /transparencia/informe-de-acceso-a-la-informacion
 * Espera: $trimestres (list<array<string,mixed>>), $causales (list<array>).
 *
 * ITA 6.10: solicitudes recibidas (a), trasladadas a otra entidad (b), tiempo
 * de respuesta (c) y solicitudes con acceso negado (d).
 *
 * Son cifras agregadas. Las solicitudes llevan nombre, documento y correo de
 * quien las radica, y nada de eso aparece aquí.
 */
?>
<div class="mx-auto max-w-5xl px-4 py-10">
    <nav aria-label="Miga de pan" class="text-sm texto-suave">
        <a href="/">Inicio</a> › <a href="/transparencia">Transparencia</a>
    </nav>

    <h1 class="mt-4">Informe de acceso a la información, quejas y reclamos</h1>
    <p class="mt-3 texto-suave max-w-prose">
        Cuántas solicitudes recibimos cada trimestre, en cuánto tiempo
        respondimos, cuántas trasladamos a otra entidad y en cuántas se negó el
        acceso a la información. Se calcula sobre las solicitudes radicadas, así
        que está siempre al día.
    </p>

    <?php if ($trimestres === []) { ?>
        <p class="mt-8 texto-suave">Todavía no hay solicitudes radicadas en el sistema.</p>
    <?php } else { ?>
        <p class="mt-6">
            <a class="boton boton-secundario" href="/transparencia/informe-de-acceso-a-la-informacion.csv">
                Descargar en CSV
            </a>
        </p>

        <div class="mt-6 hidden overflow-x-auto md:block">
            <table class="w-full border-collapse text-sm">
                <caption class="sr-only">Solicitudes de acceso a la información por trimestre</caption>
                <thead>
                    <tr>
                        <th scope="col" class="border-b-2 px-3 py-2 text-left">Periodo</th>
                        <th scope="col" class="border-b-2 px-3 py-2 text-right">Recibidas</th>
                        <th scope="col" class="border-b-2 px-3 py-2 text-right">Respondidas</th>
                        <th scope="col" class="border-b-2 px-3 py-2 text-right">Trasladadas</th>
                        <th scope="col" class="border-b-2 px-3 py-2 text-right">Acceso negado</th>
                        <th scope="col" class="border-b-2 px-3 py-2 text-right">Tiempo promedio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($trimestres as $t) { ?>
                        <tr>
                            <th scope="row" class="border-b px-3 py-2 text-left font-semibold"><?= $e((string) $t['periodo']) ?></th>
                            <td class="border-b px-3 py-2 text-right"><?= (int) $t['recibidas'] ?></td>
                            <td class="border-b px-3 py-2 text-right"><?= (int) $t['respondidas'] ?></td>
                            <td class="border-b px-3 py-2 text-right"><?= (int) $t['trasladadas'] ?></td>
                            <td class="border-b px-3 py-2 text-right"><?= (int) $t['negadas'] ?></td>
                            <td class="border-b px-3 py-2 text-right">
                                <?= $t['dias_promedio'] !== null
                                    ? $e(number_format((float) $t['dias_promedio'], 1, ',', '.')) . ' días'
                                    : 'Sin respuestas aún' ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <?php /* Móvil: una ficha por trimestre (CLAUDE.md §6). */ ?>
        <ul class="mt-6 flex flex-col gap-4 md:hidden">
            <?php foreach ($trimestres as $t) { ?>
                <li>
                    <article class="tarjeta">
                        <h2 class="text-base"><?= $e((string) $t['periodo']) ?></h2>
                        <dl class="mt-2 flex flex-col gap-1 text-sm">
                            <div><dt class="inline font-semibold">Recibidas:</dt> <dd class="inline"><?= (int) $t['recibidas'] ?></dd></div>
                            <div><dt class="inline font-semibold">Respondidas:</dt> <dd class="inline"><?= (int) $t['respondidas'] ?></dd></div>
                            <div><dt class="inline font-semibold">Trasladadas a otra entidad:</dt> <dd class="inline"><?= (int) $t['trasladadas'] ?></dd></div>
                            <div><dt class="inline font-semibold">Acceso negado:</dt> <dd class="inline"><?= (int) $t['negadas'] ?></dd></div>
                            <div>
                                <dt class="inline font-semibold">Tiempo promedio:</dt>
                                <dd class="inline"><?= $t['dias_promedio'] !== null
                                    ? $e(number_format((float) $t['dias_promedio'], 1, ',', '.')) . ' días'
                                    : 'Sin respuestas aún' ?></dd>
                            </div>
                        </dl>
                    </article>
                </li>
            <?php } ?>
        </ul>

        <?php if ($causales !== []) { ?>
            <section class="mt-10">
                <h2 class="text-base">Por qué se negó el acceso</h2>
                <p class="mt-2 text-sm texto-suave max-w-prose">
                    La Ley 1712 permite negar el acceso solo en los casos de sus
                    artículos 18 y 19, y siempre con motivación escrita.
                </p>
                <ul class="mt-3 flex flex-col gap-1 text-sm">
                    <?php foreach ($causales as $c) { ?>
                        <li><strong><?= (int) $c['total'] ?></strong> — <?= $e($c['causal']) ?></li>
                    <?php } ?>
                </ul>
            </section>
        <?php } ?>
    <?php } ?>

    <p class="mt-8 text-sm texto-suave">
        Publicado conforme al artículo 11, literal h, de la Ley 1712 de 2014 y
        al numeral 6.10 del Anexo 2 de la Resolución 1519 de 2020.
    </p>
</div>

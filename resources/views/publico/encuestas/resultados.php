<?php
/**
 * /encuestas/{slug}/resultados — cómo va la votación.
 * Espera: $encuesta, $resultados, $total (int), $abierta (bool).
 *
 * Las barras van en pasos de 5 % con las clases `.barra-N` de app.css: la CSP
 * del sitio público prohíbe el `style="width:…"`. El porcentaje exacto está
 * escrito al lado en texto, que es lo que lee tanto la persona como el lector
 * de pantalla; la barra solo da la proporción de un vistazo.
 */
$paso = static fn (int $porcentaje): int => (int) (round(max(0, min(100, $porcentaje)) / 5) * 5);
?>
<div class="mx-auto max-w-4xl px-4 py-10">
    <nav aria-label="Miga de pan" class="text-sm texto-suave">
        <a href="/">Inicio</a> › <a href="/encuestas">Encuestas</a>
    </nav>

    <h1 class="mt-4"><?= $e((string) $encuesta['titulo']) ?></h1>

    <p class="mt-3 texto-suave">
        <?= $total === 1 ? '1 persona ha respondido' : $e(number_format($total, 0, ',', '.')) . ' personas han respondido' ?>.
        <?= $abierta ? 'La encuesta sigue abierta.' : 'La encuesta ya está cerrada.' ?>
    </p>

    <?php if ($abierta) { ?>
        <p class="mt-4">
            <a class="boton boton-primario no-underline" href="/encuestas/<?= $e((string) $encuesta['slug']) ?>">Responder la encuesta</a>
        </p>
    <?php } ?>

    <?php if ($total === 0) { ?>
        <p class="mt-8 tarjeta texto-suave">
            Todavía nadie ha respondido. Sé la primera persona en opinar.
        </p>
    <?php } ?>

    <?php /* Las preguntas se listan siempre, con o sin votos: quien entra a
             mirar los resultados quiere saber QUÉ se preguntó, y una página
             que solo dice «nadie ha respondido» no informa de nada. */ ?>
        <div class="mt-8 flex flex-col gap-8">
            <?php foreach ($resultados as $pregunta) { ?>
                <section class="tarjeta p-6">
                    <h2 class="text-base font-bold texto-titular"><?= $e($pregunta['enunciado']) ?></h2>

                    <?php if ($pregunta['tipo'] === 'texto') { ?>
                        <?php /* El texto libre no se publica: puede traer el nombre o el
                                 diagnóstico de quien escribe. Se lee en el panel, por
                                 quien tiene permiso. */ ?>
                        <p class="mt-3 text-sm texto-suave">
                            <?= $pregunta['respuestas'] === 1 ? '1 respuesta escrita' : $e((string) $pregunta['respuestas']) . ' respuestas escritas' ?>.
                            Las respuestas abiertas no se publican porque pueden contener datos personales.
                        </p>
                    <?php } elseif ($pregunta['opciones'] === []) { ?>
                        <p class="mt-3 text-sm texto-suave">Sin respuestas todavía.</p>
                    <?php } else { ?>
                        <ul class="mt-4 flex flex-col gap-4">
                            <?php foreach ($pregunta['opciones'] as $opcion) { ?>
                                <li>
                                    <div class="flex justify-between gap-4 text-sm mb-1">
                                        <span class="texto-titular font-medium"><?= $e($opcion['texto']) ?></span>
                                        <span class="texto-suave tabular-nums whitespace-nowrap">
                                            <?= $e((string) $opcion['porcentaje']) ?> % ·
                                            <?= $opcion['votos'] === 1 ? '1 voto' : $e((string) $opcion['votos']) . ' votos' ?>
                                        </span>
                                    </div>
                                    <div class="barra-fondo" role="presentation">
                                        <span class="barra barra-<?= $paso($opcion['porcentaje']) ?>"></span>
                                    </div>
                                </li>
                            <?php } ?>
                        </ul>

                        <p class="mt-3 text-xs texto-suave">
                            Sobre <?= $e((string) $pregunta['respuestas']) ?>
                            <?= $pregunta['respuestas'] === 1 ? 'respuesta' : 'respuestas' ?> a esta pregunta.
                        </p>
                    <?php } ?>
                </section>
            <?php } ?>
        </div>

    <p class="mt-10">
        <a class="boton boton-secundario no-underline" href="/encuestas">Ver otras encuestas</a>
    </p>
</div>

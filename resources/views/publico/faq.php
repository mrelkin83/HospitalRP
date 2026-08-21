<?php
/**
 * /atencion-ciudadania/preguntas-frecuentes.
 * Espera: $porCategoria (array<string, list<array{pregunta, respuesta}>>).
 * Acordeón con <details>: cero JS y navegable por teclado de serie.
 */
?>
<div class="mx-auto max-w-6xl px-4 py-10">
    <h1>Preguntas frecuentes</h1>
    <p class="mt-3 texto-suave max-w-prose">
        Si no encuentras tu pregunta, escríbenos por el
        <a href="/atencion-ciudadania/pqrsdf">formulario de PQRSDF</a> o llama a
        nuestras <a href="/contacto">líneas de atención</a>.
    </p>

    <?php if ($porCategoria === []) { ?>
        <p class="mt-8 texto-suave">Aún no hay preguntas publicadas.</p>
    <?php } ?>

    <?php foreach ($porCategoria as $categoria => $preguntas) { ?>
        <section class="mt-8 max-w-3xl">
            <h2 class="text-lg font-semibold texto-titular"><?= $e($categoria) ?></h2>
            <div class="mt-3 space-y-3">
                <?php foreach ($preguntas as $fila) { ?>
                    <details class="tarjeta group">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium texto-titular">
                            <?= $e((string) $fila['pregunta']) ?>
                            <svg class="h-5 w-5 shrink-0 transition-transform group-open:rotate-180" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                 stroke-linejoin="round" aria-hidden="true">
                                <path d="m6 9 6 6 6-6"/>
                            </svg>
                        </summary>
                        <p class="mt-3 texto-suave"><?= nl2br($e((string) $fila['respuesta'])) ?></p>
                    </details>
                <?php } ?>
            </div>
        </section>
    <?php } ?>
</div>

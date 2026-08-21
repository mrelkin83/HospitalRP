<?php
/**
 * /transparencia/datos-abiertos — Espera: $datasets (list<dataset con recursos>).
 */
?>
<div class="mx-auto max-w-6xl px-4 py-10">
    <nav aria-label="Miga de pan" class="text-sm texto-suave">
        <a href="/">Inicio</a> › <a href="/transparencia">Transparencia</a>
    </nav>

    <h1 class="mt-4">Datos abiertos</h1>
    <p class="mt-3 texto-suave max-w-prose">
        Conjuntos de datos publicados en formatos abiertos y reutilizables
        (Ley 1712 de 2014). Puedes descargarlos y usarlos libremente citando
        la fuente.
    </p>

    <?php if ($datasets === []) { ?>
        <p class="mt-8 texto-suave">Pronto publicaremos los primeros conjuntos de datos.</p>
    <?php } else { ?>
        <ul class="mt-8 flex flex-col gap-4">
            <?php foreach ($datasets as $dataset) { ?>
                <li>
                    <article class="tarjeta">
                        <h2 class="text-base"><?= $e((string) $dataset['titulo']) ?></h2>
                        <p class="mt-2 text-sm"><?= $e((string) $dataset['descripcion']) ?></p>
                        <p class="mt-2 text-sm texto-suave">
                            <?php if (($dataset['dependencia_nombre'] ?? null) !== null) { ?>
                                <?= $e((string) $dataset['dependencia_nombre']) ?> ·
                            <?php } ?>
                            Actualización <?= $e((string) $dataset['periodicidad']) ?>
                            · <?= $e((string) $dataset['licencia']) ?>
                        </p>
                        <?php if ($dataset['recursos'] !== []) { ?>
                            <p class="mt-3 flex flex-wrap gap-2">
                                <?php foreach ($dataset['recursos'] as $recurso) { ?>
                                    <a class="boton boton-secundario" href="/transparencia/datos/<?= (int) $recurso['id'] ?>/descargar">
                                        Descargar <?= strtoupper((string) $recurso['formato']) ?>
                                    </a>
                                <?php } ?>
                            </p>
                        <?php } ?>
                    </article>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>
</div>

<?php
/**
 * /encuestas — encuestas de satisfacción abiertas.
 * Espera: $encuestas.
 */
?>
<div class="mx-auto max-w-3xl px-4 py-10">
    <h1>Encuestas de satisfacción</h1>
    <p class="mt-3 texto-suave max-w-prose">
        Tu opinión nos ayuda a mejorar la atención. Las respuestas son
        <strong>anónimas</strong>: no te pedimos nombre, documento ni teléfono.
    </p>

    <?php if ($encuestas === []) { ?>
        <p class="mt-8 tarjeta texto-suave">
            No hay encuestas abiertas en este momento. Si quieres presentar una
            queja o una sugerencia, puedes
            <a href="/atencion-ciudadania/pqrsdf">radicar una PQRSDF</a>.
        </p>
    <?php } else { ?>
        <ul class="mt-8 flex flex-col gap-4">
            <?php foreach ($encuestas as $en) { ?>
                <li class="tarjeta">
                    <h2 class="text-lg">
                        <a href="/encuestas/<?= $e((string) $en['slug']) ?>"><?= $e((string) $en['titulo']) ?></a>
                    </h2>
                    <?php if (!empty($en['descripcion'])) { ?>
                        <p class="mt-2 texto-suave"><?= $e((string) $en['descripcion']) ?></p>
                    <?php } ?>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>
</div>

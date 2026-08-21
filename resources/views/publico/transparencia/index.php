<?php
/**
 * /transparencia — menú completo del Anexo 2.
 * Espera: $arbol (list<seccion con hijas e items publicados>).
 */
?>
<div class="mx-auto max-w-6xl px-4 py-10">
    <h1>Transparencia y acceso a la información pública</h1>
    <p class="mt-3 texto-suave max-w-prose">
        Información publicada en cumplimiento de la Ley 1712 de 2014 y la
        Resolución 1519 de 2020.
    </p>

    <div class="mt-8 flex flex-col gap-8">
        <?php foreach ($arbol as $seccion) { ?>
            <section class="tarjeta" aria-labelledby="tp-<?= $seccion['id'] ?>" id="seccion-<?= $e((string) $seccion['codigo']) ?>">
                <h2 id="tp-<?= $seccion['id'] ?>" class="text-lg">
                    <?= $e((string) $seccion['codigo']) ?>. <?= $e((string) $seccion['nombre']) ?>
                </h2>

                <?php foreach ($seccion['hijas'] as $hija) { ?>
                    <div class="mt-4 border-t borde-suave pt-4">
                        <h3 class="text-base texto-titular">
                            <?= $e((string) $hija['codigo']) ?> <?= $e((string) $hija['nombre']) ?>
                        </h3>

                        <?php if ($hija['items'] === []) { ?>
                            <p class="mt-2 text-sm texto-suave">Sección en actualización.</p>
                        <?php } else { ?>
                            <ul class="mt-2 flex flex-col gap-1.5">
                                <?php foreach ($hija['items'] as $item) { ?>
                                    <li>
                                        <?php if ($item['documento_id'] !== null && $item['documento_slug'] !== null) { ?>
                                            <a href="/documentos/<?= (int) $item['documento_id'] ?>-<?= $e((string) $item['documento_slug']) ?>">
                                                <?= $e((string) $item['titulo']) ?>
                                            </a>
                                        <?php } else { ?>
                                            <a href="<?= $e((string) $item['url_externa']) ?>" target="_blank" rel="noopener">
                                                <?= $e((string) $item['titulo']) ?>
                                                <span class="text-sm texto-suave">(enlace externo)</span>
                                            </a>
                                        <?php } ?>
                                        <?php if (($item['descripcion'] ?? null) !== null) { ?>
                                            <p class="text-sm texto-suave"><?= $e((string) $item['descripcion']) ?></p>
                                        <?php } ?>
                                    </li>
                                <?php } ?>
                            </ul>
                        <?php } ?>
                    </div>
                <?php } ?>
            </section>
        <?php } ?>
    </div>
</div>

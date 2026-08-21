<?php
/**
 * /buscar — buscador global.
 * Espera: $termino (string), $resultado (?array{termino, total, grupos}).
 */
?>
<div class="mx-auto max-w-6xl px-4 py-10">
    <h1>Buscar en el sitio</h1>
    <p class="mt-3 texto-suave max-w-prose">
        Trámites, servicios, documentos, noticias y todo lo publicado por el hospital.
    </p>

    <form class="mt-6 flex max-w-2xl gap-3" method="get" action="/buscar" role="search">
        <div class="grow">
            <label class="sr-only" for="q">Qué estás buscando</label>
            <input class="campo" type="search" id="q" name="q" value="<?= $e($termino) ?>"
                   placeholder="Por ejemplo: certificado laboral, citas, urgencias…"
                   minlength="3" required autofocus>
        </div>
        <button type="submit" class="boton boton-primario shrink-0">Buscar</button>
    </form>

    <?php if ($resultado !== null) { ?>
        <?php if ($resultado['termino'] !== '' && mb_strlen($resultado['termino']) < 3) { ?>
            <p class="mt-8 texto-suave">Escribe al menos tres letras para buscar.</p>
        <?php } elseif ($resultado['total'] === 0) { ?>
            <div class="tarjeta mt-8 max-w-2xl">
                <h2 class="text-lg">Sin resultados para «<?= $e($resultado['termino']) ?>»</h2>
                <p class="mt-2 texto-suave">
                    Registramos tu búsqueda para mejorar el sitio. Mientras tanto puedes:
                </p>
                <ul class="mt-3 space-y-2 texto-suave list-disc pl-5">
                    <li><a href="/atencion-ciudadania/tramites">Ver los trámites y solicitudes en línea</a></li>
                    <li><a href="/documentos">Buscar en los documentos públicos</a></li>
                    <li><a href="/contacto">Comunicarte con nuestras líneas de atención</a></li>
                </ul>
            </div>
        <?php } else { ?>
            <p class="mt-8 texto-suave" role="status">
                <?= $resultado['total'] === 1 ? '1 resultado' : $e((string) $resultado['total']) . ' resultados' ?>
                para «<?= $e($resultado['termino']) ?>»
            </p>

            <?php foreach ($resultado['grupos'] as $grupo) { ?>
                <section class="mt-8">
                    <h2 class="text-lg font-semibold texto-titular"><?= $e($grupo['etiqueta']) ?></h2>
                    <ul class="mt-3 space-y-3">
                        <?php foreach ($grupo['resultados'] as $fila) { ?>
                            <li class="tarjeta">
                                <a class="font-medium" href="<?= $e((string) $fila['url']) ?>">
                                    <?= $e((string) $fila['titulo']) ?>
                                </a>
                                <?php if (($fila['resumen'] ?? null) !== null && $fila['resumen'] !== '') { ?>
                                    <p class="mt-1 text-sm texto-suave">
                                        <?= $e(mb_substr((string) $fila['resumen'], 0, 220)) ?><?= mb_strlen((string) $fila['resumen']) > 220 ? '…' : '' ?>
                                    </p>
                                <?php } ?>
                            </li>
                        <?php } ?>
                    </ul>
                </section>
            <?php } ?>
        <?php } ?>
    <?php } ?>
</div>

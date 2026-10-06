<?php
/**
 * /transparencia/instrumentos-de-gestion
 * Espera: $tipos (list<TipoInstrumento>), $conteo (array<string,int>), $portalUrl (string).
 *
 * Índice de los tres instrumentos de la Ley 1712 art. 6 lit. d (ITA 9.1).
 * Un instrumento sin filas publicadas se anuncia «En actualización» en vez de
 * esconderse: ocultarlo fingiría un cumplimiento que no existe, igual que hace
 * el índice del menú de Transparencia.
 */
?>
<div class="mx-auto max-w-5xl px-4 py-10">
    <nav aria-label="Miga de pan" class="text-sm texto-suave">
        <a href="/">Inicio</a> › <a href="/transparencia">Transparencia</a>
        › <a href="/transparencia/datos-abiertos">Datos abiertos</a>
    </nav>

    <h1 class="mt-4">Instrumentos de gestión de la información</h1>
    <p class="mt-3 texto-suave max-w-prose">
        La Ley 1712 de 2014 obliga a toda entidad pública a mantener tres
        instrumentos que describen qué información produce, cuál reserva y
        dónde se consulta. Cada uno se publica como tabla y se descarga en
        formato abierto.
    </p>

    <ul class="mt-8 flex flex-col gap-4">
        <?php foreach ($tipos as $tipo) {
            $filas = $conteo[$tipo->value] ?? 0; ?>
            <li>
                <article class="tarjeta">
                    <h2 class="text-base">
                        <a href="/transparencia/instrumentos-de-gestion/<?= $e($tipo->slug()) ?>">
                            <?= $e($tipo->nombre()) ?>
                        </a>
                    </h2>
                    <p class="mt-2 text-sm"><?= $e($tipo->descripcion()) ?></p>
                    <p class="mt-2 text-sm texto-suave">
                        Ítem <?= $e($tipo->codigoTransparencia()) ?> del menú de Transparencia ·
                        <?php if ($filas === 0) { ?>
                            En actualización
                        <?php } else { ?>
                            <?= $filas ?> <?= $filas === 1 ? 'registro' : 'registros' ?>
                        <?php } ?>
                    </p>
                    <?php if ($filas > 0) { ?>
                        <p class="mt-3 flex flex-wrap gap-2">
                            <a class="boton boton-secundario"
                               href="/transparencia/instrumentos-de-gestion/<?= $e($tipo->slug()) ?>">
                                Ver la tabla
                            </a>
                            <a class="boton boton-secundario"
                               href="/transparencia/instrumentos-de-gestion/<?= $e($tipo->slug()) ?>.csv">
                                Descargar CSV
                            </a>
                        </p>
                    <?php } ?>
                </article>
            </li>
        <?php } ?>
    </ul>

    <?php if ($portalUrl !== '') { ?>
        <p class="mt-8 text-sm texto-suave">
            Los conjuntos de datos de la entidad también se consultan en el
            <a href="<?= $e($portalUrl) ?>" target="_blank" rel="noopener">Portal de Datos Abiertos del Estado colombiano</a>.
        </p>
    <?php } ?>
</div>

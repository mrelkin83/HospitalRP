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

    <?php /* ITA 9.2: vista de los datos de la entidad en el portal nacional.
             Si todavía no hay perfil publicado, no se inventa el enlace. */ ?>
    <?php if ($portalUrl !== '') { ?>
        <section class="mt-10">
            <h2 class="text-base">Portal de Datos Abiertos del Estado colombiano</h2>
            <p class="mt-2 text-sm texto-suave max-w-prose">
                Los conjuntos de datos de la E.S.E. también se consultan y
                descargan desde el portal nacional <strong>datos.gov.co</strong>,
                donde quedan disponibles con su ficha técnica y su API.
            </p>
            <p class="mt-3">
                <a class="boton boton-secundario" href="<?= $e($portalUrl) ?>" target="_blank" rel="noopener">
                    Ver nuestros datos en datos.gov.co
                </a>
            </p>
        </section>
    <?php } ?>

    <?php /* ITA 9.1: los tres instrumentos de gestión de la información. */ ?>
    <section class="mt-10">
        <h2 class="text-base">Instrumentos de gestión de la información</h2>
        <p class="mt-2 text-sm texto-suave max-w-prose">
            Qué información produce la entidad, cuál reserva y dónde se
            consulta (Ley 1712 de 2014, artículo 6, literal d).
        </p>
        <p class="mt-3">
            <a class="boton boton-secundario" href="/transparencia/instrumentos-de-gestion">
                Consultar los instrumentos
                (<?= array_sum($conteoInstrumentos) ?> registros)
            </a>
        </p>
    </section>
</div>

<?php
/**
 * /atencion-ciudadania/tramites — Espera: $tipos (list<TipoTramite>).
 */
?>
<div class="mx-auto max-w-6xl px-4 py-10">
    <nav aria-label="Miga de pan" class="text-sm texto-suave">
        <a href="/">Inicio</a> › <a href="/atencion-ciudadania">Atención a la Ciudadanía</a>
    </nav>

    <h1 class="mt-4">Trámites y servicios</h1>
    <p class="mt-3 texto-suave max-w-prose">
        Radica tu solicitud en línea, sin filas y sin cuenta de usuario.
        Al terminar recibirás un número de radicado para hacerle seguimiento.
    </p>

    <ul class="mt-8 grid gap-4 sm:grid-cols-2">
        <?php foreach ($tipos as $tipo) { ?>
            <li>
                <article class="tarjeta h-full flex flex-col">
                    <h2 class="text-base"><?= $e($tipo->nombre) ?></h2>
                    <?php if ($tipo->descripcion !== null) { ?>
                        <p class="mt-2 text-sm texto-suave"><?= $e($tipo->descripcion) ?></p>
                    <?php } ?>
                    <p class="mt-2 text-sm texto-suave">
                        Plazo de respuesta: <?= $tipo->diasTermino ?> días <?= $tipo->diasHabiles ? 'hábiles' : 'calendario' ?>.
                        <?php /* Costos asociados (ITA 7.1): también en el
                                 listado, para que se vean sin entrar a cada
                                 trámite. */ ?>
                        <?= $tipo->gratuito ? 'Gratuito.' : 'Tiene costo.' ?>
                    </p>
                    <p class="mt-auto pt-4">
                        <a class="boton boton-primario" href="/atencion-ciudadania/tramites/<?= $e($tipo->slug) ?>">Radicar</a>
                    </p>
                </article>
            </li>
        <?php } ?>
    </ul>

    <p class="mt-8">
        ¿Ya radicaste? <a href="/atencion-ciudadania/pqrsdf/consultar">Consulta el estado de tu trámite</a>.
    </p>
</div>

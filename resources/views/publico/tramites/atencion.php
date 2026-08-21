<?php
/**
 * /atencion-ciudadania — índice del menú obligatorio (Res. 1519).
 * Espera: $tipos (list<TipoTramite>).
 */
?>
<div class="mx-auto max-w-6xl px-4 py-10">
    <h1>Atención y Servicios a la Ciudadanía</h1>

    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <a class="tarjeta no-underline hover:border-primario-500 transition-colors" href="/atencion-ciudadania/tramites">
            <h2 class="text-base texto-titular">Trámites en línea</h2>
            <p class="mt-2 text-sm texto-suave">Radica PQRSDF, citas, historia clínica, certificados y habeas data.</p>
        </a>
        <a class="tarjeta no-underline hover:border-primario-500 transition-colors" href="/atencion-ciudadania/pqrsdf/consultar">
            <h2 class="text-base texto-titular">Consulta tu radicado</h2>
            <p class="mt-2 text-sm texto-suave">Sigue el estado de tu solicitud con el radicado y tu documento.</p>
        </a>
        <a class="tarjeta no-underline hover:border-primario-500 transition-colors" href="/contacto">
            <h2 class="text-base texto-titular">Canales de atención</h2>
            <p class="mt-2 text-sm texto-suave">Teléfonos, correos, sedes y horarios.</p>
        </a>
    </div>

    <h2 class="mt-10 text-lg">Trámites disponibles</h2>
    <ul class="mt-4 grid gap-3 sm:grid-cols-2">
        <?php foreach ($tipos as $tipo) { ?>
            <li>
                <a class="tarjeta block no-underline hover:border-primario-500 transition-colors"
                   href="/atencion-ciudadania/tramites/<?= $e($tipo->slug) ?>">
                    <span class="font-semibold texto-titular"><?= $e($tipo->nombre) ?></span>
                    <?php if ($tipo->descripcion !== null) { ?>
                        <span class="mt-1 block text-sm texto-suave"><?= $e($tipo->descripcion) ?></span>
                    <?php } ?>
                </a>
            </li>
        <?php } ?>
    </ul>
</div>

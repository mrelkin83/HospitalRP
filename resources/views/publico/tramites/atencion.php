<?php
/**
 * /atencion-ciudadania — índice del menú obligatorio (Res. 1519).
 * Espera: $tipos (list<TipoTramite>).
 */
?>
<div class="mx-auto max-w-6xl px-4 py-10">
    <!-- Cabecera Premium de Atención -->
    <div class="relative overflow-hidden rounded-[2.5rem] bg-gradient-to-br from-primario-900 via-primario-800 to-primario-950 p-10 text-white mb-10 shadow-2xl border border-white/10">
        <span class="mancha h-64 w-64 -right-16 -top-16 bg-[color:var(--color-marca-cian)] opacity-30 blur-3xl" aria-hidden="true"></span>
        <div class="relative z-10 max-w-2xl">
            <span class="text-xs font-bold uppercase tracking-widest text-[color:var(--color-marca-cian)] bg-white/10 px-3 py-1 rounded-full border border-white/10">Atención Ciudadana</span>
            <h1 class="text-white mt-4 text-4xl lg:text-5xl font-extrabold tracking-tight">Servicio a la Ciudadanía</h1>
            <p class="mt-4 text-lg text-primario-100 leading-relaxed">
                Facilitamos tu interacción con el hospital. Consulta canales de atención, radica trámites, PQRSDF y haz seguimiento en línea con total transparencia.
            </p>
        </div>
    </div>

    <!-- Bento Grid Principal de Accesos -->
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 mb-12">
        <a class="tarjeta group no-underline hover:-translate-y-2 hover:shadow-2xl transition-all duration-300 border-primario-100/50 dark:border-primario-700/50 bg-[color:var(--superficie)] p-6" href="/atencion-ciudadania/tramites">
            <span class="grid h-12 w-12 place-items-center rounded-2xl bg-primario-50 text-primario-600 dark:bg-primario-800 dark:text-primario-200 group-hover:scale-110 transition-transform mb-4">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
                </svg>
            </span>
            <h2 class="text-xl font-extrabold tracking-tight text-primario-900 dark:text-white group-hover:text-primario-600 transition-colors">Trámites en línea</h2>
            <p class="mt-2 text-sm texto-suave leading-relaxed">Radica PQRSDF, citas, historia clínica, certificados y habeas data.</p>
        </a>

        <a class="tarjeta group no-underline hover:-translate-y-2 hover:shadow-2xl transition-all duration-300 border-primario-100/50 dark:border-primario-700/50 bg-[color:var(--superficie)] p-6" href="/atencion-ciudadania/pqrsdf/consultar">
            <span class="grid h-12 w-12 place-items-center rounded-2xl bg-primario-50 text-primario-600 dark:bg-primario-800 dark:text-primario-200 group-hover:scale-110 transition-transform mb-4">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
            </span>
            <h2 class="text-xl font-extrabold tracking-tight text-primario-900 dark:text-white group-hover:text-primario-600 transition-colors">Consulta tu radicado</h2>
            <p class="mt-2 text-sm texto-suave leading-relaxed">Sigue el estado de tu solicitud con el radicado y tu documento.</p>
        </a>

        <a class="tarjeta group no-underline hover:-translate-y-2 hover:shadow-2xl transition-all duration-300 border-primario-100/50 dark:border-primario-700/50 bg-[color:var(--superficie)] p-6" href="/contacto">
            <span class="grid h-12 w-12 place-items-center rounded-2xl bg-primario-50 text-primario-600 dark:bg-primario-800 dark:text-primario-200 group-hover:scale-110 transition-transform mb-4">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
            </span>
            <h2 class="text-xl font-extrabold tracking-tight text-primario-900 dark:text-white group-hover:text-primario-600 transition-colors">Canales de atención</h2>
            <p class="mt-2 text-sm texto-suave leading-relaxed">Teléfonos, correos, sedes y horarios de ventanilla única.</p>
        </a>
    </div>

    <!-- Trámites Disponibles -->
    <h2 class="text-2xl font-extrabold tracking-tight text-primario-900 dark:text-white mb-6">Trámites y Solicitudes Disponibles</h2>
    <ul class="grid gap-6 sm:grid-cols-2">
        <?php foreach ($tipos as $tipo) { ?>
            <li class="transform transition duration-300 hover:-translate-y-1.5">
                <a class="tarjeta group block no-underline border-primario-100/50 dark:border-primario-700/50 bg-[color:var(--superficie)] p-6"
                   href="/atencion-ciudadania/tramites/<?= $e($tipo->slug) ?>">
                    <div class="flex items-center gap-3 border-b border-primario-100/50 dark:border-primario-800 pb-3 mb-3">
                        <span class="grid h-8 w-8 place-items-center rounded-lg bg-primario-50 text-primario-600 dark:bg-primario-800 dark:text-primario-200 group-hover:scale-110 transition-transform" aria-hidden="true">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        </span>
                        <h3 class="font-extrabold text-lg text-primario-900 dark:text-white group-hover:text-primario-600 transition-colors"><?= $e($tipo->nombre) ?></h3>
                    </div>
                    <?php if ($tipo->descripcion !== null) { ?>
                        <span class="block text-sm texto-suave leading-relaxed"><?= $e($tipo->descripcion) ?></span>
                    <?php } ?>
                </a>
            </li>
        <?php } ?>
    </ul>
</div>

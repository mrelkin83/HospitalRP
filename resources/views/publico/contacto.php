<?php
/**
 * /contacto — Espera: $config (ConfigDinamica), $sedes (list<Sede>).
 */
$callCenter = $config->texto('contacto.call_center');
$pbx = $config->texto('contacto.pbx');
?>
<div class="mx-auto max-w-6xl px-4 py-10">
    <!-- Cabecera Premium con Cristal y Gradiente -->
    <div class="relative overflow-hidden rounded-[2.5rem] cabecera-seccion p-10 text-white mb-10 shadow-2xl border border-white/10">
        <span class="mancha h-64 w-64 -right-16 -top-16 bg-[color:var(--color-marca-cian)] opacity-30 blur-3xl" aria-hidden="true"></span>
        <div class="relative z-10 max-w-2xl">
            <span class="text-xs font-bold uppercase tracking-widest text-white bg-white/10 px-3 py-1 rounded-full border border-white/10">Canales de Atención</span>
            <h1 class="text-white mt-4 text-4xl lg:text-5xl font-extrabold tracking-tight">Contacto y Soporte</h1>
            <p class="mt-4 text-lg text-primario-100 leading-relaxed">
                Estamos a tu disposición. Comunícate con nuestros diferentes canales de atención y solicita servicios de forma ágil y segura.
            </p>
        </div>
    </div>

    <!-- Bento Grid de Contactos -->
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <div class="tarjeta flex flex-col justify-between group border-primario-100/50 dark:border-primario-700/50 bg-[color:var(--fondo)]/80 backdrop-blur-sm">
            <div>
                <span class="grid h-12 w-12 place-items-center rounded-2xl bg-primario-50 text-primario-600 dark:bg-primario-800 dark:text-primario-200 group-hover:scale-110 transition-transform mb-4">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                </span>
                <h2 class="text-xl font-bold texto-titular">Citas Médicas</h2>
                <p class="mt-2 text-sm texto-suave">Agenda, cancela o reasigna citas con nuestro call center especializado.</p>
                <p class="mt-4 text-lg font-extrabold text-primario-600 dark:text-primario-300">
                    <a class="no-underline hover:underline" href="tel:<?= $e(preg_replace('/\D+/', '', $callCenter)) ?>"><?= $e($callCenter) ?></a>
                </p>
            </div>
            <div class="mt-6 border-t border-primario-100 dark:border-primario-800 pt-3">
                <a class="text-xs font-semibold text-primario-600 hover:underline break-all" href="mailto:<?= $e($config->texto('contacto.correo_citas')) ?>">
                    <?= $e($config->texto('contacto.correo_citas')) ?>
                </a>
            </div>
        </div>

        <div class="tarjeta flex flex-col justify-between group border-primario-100/50 dark:border-primario-700/50 bg-[color:var(--fondo)]/80 backdrop-blur-sm">
            <div>
                <span class="grid h-12 w-12 place-items-center rounded-2xl bg-primario-50 text-primario-600 dark:bg-primario-800 dark:text-primario-200 group-hover:scale-110 transition-transform mb-4">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                </span>
                <h2 class="text-xl font-bold texto-titular">Línea PBX</h2>
                <p class="mt-2 text-sm texto-suave">Conmutador general para comunicarte con cualquier área del hospital.</p>
                <p class="mt-4 text-lg font-extrabold text-primario-600 dark:text-primario-300">
                    <a class="no-underline hover:underline" href="tel:<?= $e(preg_replace('/\D+/', '', $pbx)) ?>"><?= $e($pbx) ?></a>
                </p>
            </div>
            <div class="mt-6 border-t border-primario-100 dark:border-primario-800 pt-3">
                <span class="text-xs text-primario-500 font-bold uppercase tracking-wider">Atención 24 Horas</span>
            </div>
        </div>

        <div class="tarjeta flex flex-col justify-between group border-primario-100/50 dark:border-primario-700/50 bg-[color:var(--fondo)]/80 backdrop-blur-sm">
            <div>
                <span class="grid h-12 w-12 place-items-center rounded-2xl bg-primario-50 text-primario-600 dark:bg-primario-800 dark:text-primario-200 group-hover:scale-110 transition-transform mb-4">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                </span>
                <h2 class="text-xl font-bold texto-titular">Correo Institucional</h2>
                <p class="mt-2 text-sm texto-suave">Escríbenos para solicitudes administrativas o de carácter general.</p>
                <p class="mt-4 text-sm font-extrabold text-primario-600 dark:text-primario-300 break-words">
                    <a class="no-underline hover:underline" href="mailto:<?= $e($config->texto('contacto.correo')) ?>"><?= $e($config->texto('contacto.correo')) ?></a>
                </p>
            </div>
            <div class="mt-6 border-t border-primario-100 dark:border-primario-800 pt-3">
                <span class="text-xs text-primario-500 font-bold uppercase tracking-wider">Tratamiento de Datos</span>
            </div>
        </div>

        <div class="tarjeta flex flex-col justify-between group border-primario-100/50 dark:border-primario-700/50 bg-[color:var(--fondo)]/80 backdrop-blur-sm">
            <div>
                <span class="grid h-12 w-12 place-items-center rounded-2xl bg-primario-50 text-primario-600 dark:bg-primario-800 dark:text-primario-200 group-hover:scale-110 transition-transform mb-4">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                        <polyline points="10 9 9 9 8 9"/>
                    </svg>
                </span>
                <h2 class="text-xl font-bold texto-titular">PQRSDF</h2>
                <p class="mt-2 text-sm texto-suave">Peticiones, quejas, reclamos, sugerencias, denuncias o felicitaciones.</p>
                <p class="mt-4">
                    <a class="boton boton-primario text-sm py-2 px-4 rounded-xl flex justify-center w-full shadow-md" href="/atencion-ciudadania/pqrsdf">Radicar PQRSDF</a>
                </p>
            </div>
            <div class="mt-6 border-t border-primario-100 dark:border-primario-800 pt-3">
                <span class="text-xs text-primario-500 font-bold uppercase tracking-wider">Atención Ciudadana</span>
            </div>
        </div>
    </div>

    <!-- Panel de Líneas Éticas y Administración -->
    <div class="mt-10 panel-vidrio rounded-[2rem] p-8 border-white/20">
        <div class="grid gap-8 md:grid-cols-2">
            <div>
                <h3 class="text-xl font-bold flex items-center gap-2 mb-4">
                    <svg class="h-6 w-6 text-primario-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    Atención Administrativa
                </h3>
                <ul class="space-y-3 text-sm leading-relaxed">
                    <li><strong class="texto-titular">Horarios:</strong> <span class="texto-suave"><?= $e($config->texto('contacto.horario')) ?></span></li>
                    <li><strong class="texto-titular">Ventanilla Única de Correspondencia:</strong> <a class="font-semibold text-primario-600 dark:text-primario-400 hover:underline" href="mailto:<?= $e($config->texto('contacto.correo_ventanilla')) ?>"><?= $e($config->texto('contacto.correo_ventanilla')) ?></a></li>
                    <li><strong class="texto-titular">Notificaciones Judiciales:</strong> <a class="font-semibold text-primario-600 dark:text-primario-400 hover:underline break-all" href="mailto:<?= $e($config->texto('contacto.correo_notificaciones')) ?>"><?= $e($config->texto('contacto.correo_notificaciones')) ?></a></li>
                </ul>
            </div>
            <div>
                <h3 class="text-xl font-bold flex items-center gap-2 mb-4">
                    <svg class="h-6 w-6 text-error-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Transparencia y Línea Ética
                </h3>
                <p class="text-sm texto-suave leading-relaxed mb-4">
                    Canal seguro y confidencial para denunciar actos de fraude o corrupción en la entidad.
                </p>
                <ul class="space-y-3 text-sm leading-relaxed">
                    <li><strong class="texto-titular">Línea Telefónica Ética:</strong> <a class="font-semibold text-primario-600 dark:text-primario-400 hover:underline" href="tel:<?= $e(preg_replace('/\D+/', '', $config->texto('contacto.linea_etica_telefono'))) ?>"><?= $e($config->texto('contacto.linea_etica_telefono')) ?></a></li>
                    <li><strong class="texto-titular">Correo de Denuncias:</strong> <a class="font-semibold text-primario-600 dark:text-primario-400 hover:underline" href="mailto:<?= $e($config->texto('contacto.linea_etica_correo')) ?>"><?= $e($config->texto('contacto.linea_etica_correo')) ?></a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Listado de Sedes -->
    <h2 class="pestana text-xl sm:text-2xl mt-12"><?= count($sedes) === 1 ? 'Nuestra Sede' : 'Nuestras Sedes' ?></h2>
    <ul class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($sedes as $sede) { ?>
            <li class="transform transition duration-300 hover:-translate-y-2">
                <?= $vistas->render('publico/parciales/tarjeta-sede', ['sede' => $sede]) ?>
            </li>
        <?php } ?>
    </ul>
</div>

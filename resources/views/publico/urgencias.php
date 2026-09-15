<?php
/**
 * /urgencias — la página que se abre con afán: teléfono primero.
 * Espera: $config (ConfigDinamica), $sedes (list<Sede> con urgencias).
 */
$telefono = $config->texto('contacto.emergencias');
$soloTelefono = preg_replace('/\D+/', '', $telefono);
?>
<?php
/**
 * /urgencias — la página que se abre con afán: teléfono primero.
 * Espera: $config (ConfigDinamica), $sedes (list<Sede> con urgencias).
 */
$telefono = $config->texto('contacto.emergencias');
$soloTelefono = preg_replace('/\D+/', '', $telefono);
?>
<div class="mx-auto max-w-6xl px-4 py-10">
    <!-- Cabecera Crítica de Urgencias con Pulso de Neón -->
    <div class="relative overflow-hidden rounded-[2.5rem] bg-gradient-to-br from-error-950 via-error-900 to-red-950 p-10 text-white mb-10 shadow-2xl border border-error-500/20">
        <span class="mancha h-64 w-64 -right-16 -top-16 bg-error-600 opacity-40 blur-3xl animate-pulse" aria-hidden="true"></span>
        <div class="relative z-10 grid md:grid-cols-3 gap-8 items-center">
            <div class="md:col-span-2">
                <span class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-red-200 bg-error-600/30 px-3 py-1 rounded-full border border-error-500/30 mb-4">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
                    </span>
                    Atención Médica Inmediata 24/7
                </span>
                <h1 class="text-white text-4xl lg:text-5xl font-extrabold tracking-tight">Servicio de Urgencias</h1>
                <p class="mt-4 text-lg text-red-100/90 leading-relaxed">
                    Si tu vida o la de un familiar está en peligro, comunícate inmediatamente o asiste a la sede de urgencias más cercana. Atendemos sin citas ni autorizaciones previas.
                </p>
            </div>
            <div class="flex flex-col gap-3 justify-center">
                <a class="boton bg-white text-error-800 hover:bg-red-50 text-xl font-extrabold py-4 px-6 rounded-2xl shadow-xl flex items-center justify-center w-full transition-transform hover:-translate-y-1" href="tel:<?= $e($soloTelefono) ?>">
                    <svg class="h-6 w-6 mr-2 animate-bounce" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    <?= $e($telefono) ?>
                </a>
                <span class="text-center text-xs text-red-200">Línea directa de emergencias médicas</span>
            </div>
        </div>
    </div>

    <!-- Secciones Bento de Triage y Educación -->
    <div class="grid gap-8 lg:grid-cols-3 mb-12">
        <div class="lg:col-span-2 panel-vidrio rounded-[2.5rem] p-8 border-white/20">
            <h2 class="text-2xl font-extrabold tracking-tight texto-titular mb-6">¿Qué es una Urgencia Vital? (Triage I y II)</h2>
            <div class="grid sm:grid-cols-2 gap-6 text-sm">
                <div class="bg-error-100/10 dark:bg-error-950/15 border border-error-500/20 rounded-2xl p-5">
                    <h3 class="text-error-600 dark:text-error-400 font-extrabold text-base mb-2">Síntomas Críticos</h3>
                    <ul class="space-y-2 text-sm text-primario-900 dark:text-primario-100 leading-relaxed list-disc list-inside">
                        <li>Dolor agudo en el pecho o brazo izquierdo.</li>
                        <li>Dificultad severa para respirar o ahogo.</li>
                        <li>Pérdida súbita del conocimiento.</li>
                        <li>Pérdida de fuerza o habla repentina.</li>
                    </ul>
                </div>
                <div class="bg-error-100/10 dark:bg-error-950/15 border border-error-500/20 rounded-2xl p-5">
                    <h3 class="text-error-600 dark:text-error-400 font-extrabold text-base mb-2">Traumas y Accidentes</h3>
                    <ul class="space-y-2 text-sm text-primario-900 dark:text-primario-100 leading-relaxed list-disc list-inside">
                        <li>Sangrado abundante o hemorragias activas.</li>
                        <li>Fracturas expuestas o traumas craneales.</li>
                        <li>Intoxicaciones por químicos o medicamentos.</li>
                        <li>Quemaduras graves de segundo o tercer grado.</li>
                    </ul>
                </div>
            </div>
            <div class="mt-6 p-4 bg-white/5 border border-white/10 rounded-2xl text-xs text-primario-500 flex items-center gap-3">
                <svg class="h-6 w-6 text-primario-500 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                <span>El sistema Triage clasifica la prioridad de atención médica según la gravedad del paciente, no por el orden de llegada.</span>
            </div>
        </div>

        <div class="panel-vidrio rounded-[2.5rem] p-8 border-white/20 flex flex-col justify-between">
            <div>
                <h2 class="text-2xl font-extrabold tracking-tight texto-titular mb-4">¿No es Urgente?</h2>
                <p class="text-sm texto-suave leading-relaxed mb-4">
                    Si presentas dolores de cabeza leves, gripas comunes, programaciones de cirugías o controles rutinarios, por favor agenda una consulta externa.
                </p>
                <p class="text-sm texto-suave leading-relaxed">
                    Al utilizar la consulta prioritaria, ayudas a descongestionar el servicio para salvar vidas.
                </p>
            </div>
            <div class="mt-6 pt-6 border-t border-primario-100 dark:border-primario-800">
                <a class="boton boton-primario w-full rounded-xl py-3 justify-center shadow-lg" href="/citas">Solicitar Cita Médica</a>
            </div>
        </div>
    </div>

    <!-- Sedes de Urgencias -->
    <h2 class="text-2xl font-extrabold tracking-tight text-primario-900 dark:text-white mb-6">Sedes con Urgencias 24 Horas</h2>
    <?php if ($sedes === []) { ?>
        <p class="texto-suave bg-white dark:bg-primario-900 border border-primario-100 dark:border-primario-800 p-6 rounded-2xl">
            Pronto publicaremos el catálogo interactivo de sedes con servicio activo de urgencias.
        </p>
    <?php } else { ?>
        <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($sedes as $sede) { ?>
                <li class="transform transition duration-300 hover:-translate-y-2">
                    <?= $vistas->render('publico/parciales/tarjeta-sede', ['sede' => $sede]) ?>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>
</div>

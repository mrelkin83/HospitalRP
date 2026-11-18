<?php
/**
 * Portal de Empleados: comunicados y accesos para el talento humano.
 * Espera: $campanas (list<Campana>), $imagenes (array<int, Archivo>),
 *         $titulo (string), $descripcion (string).
 *
 * Todo lo que hay aquí es público. No sustituye a una intranet ni pide
 * credenciales: el hospital mandó retirar la intranet el 2026-09-14, y la
 * información de talento humano —convocatorias, manual de funciones, escalas
 * salariales— es de publicación obligatoria por la Ley 1712.
 */

// Accesos que ya existen en el sitio; ninguno se inventa aquí.
$accesosTalentoHumano = [
    ['Convocatorias', 'Vacantes y procesos de selección abiertos', '/convocatorias'],
    ['Servidores públicos y contratistas', 'Directorio, perfiles y contratación vigente', '/servidores-publicos'],
    ['Talento humano en Transparencia', 'Manual de funciones, escalas salariales y evaluación', '/transparencia'],
    ['Documentos institucionales', 'Circulares, resoluciones, manuales y formatos', '/documentos'],
];
?>
<div class="mx-auto max-w-6xl px-4 py-10">
    <div class="relative overflow-hidden rounded-[2.5rem] cabecera-seccion p-10 text-white mb-10 shadow-2xl border border-white/10">
        <span class="mancha h-64 w-64 -right-16 -top-16 bg-aviso-400 opacity-25 blur-3xl" aria-hidden="true"></span>
        <div class="relative z-10 max-w-2xl">
            <span class="text-xs font-bold uppercase tracking-widest text-aviso-100 bg-white/10 px-3 py-1 rounded-full border border-white/10">Portal de Empleados</span>
            <h1 class="text-white mt-4 text-4xl lg:text-5xl font-extrabold tracking-tight"><?= $e($titulo) ?></h1>
            <p class="mt-4 text-lg text-primario-100 leading-relaxed"><?= $e($descripcion) ?></p>
        </div>
    </div>

    <h2 class="pestana text-xl sm:text-2xl mb-5">Accesos del talento humano</h2>
    <ul class="grid gap-5 sm:grid-cols-2 mb-12">
        <?php foreach ($accesosTalentoHumano as [$nombre, $detalle, $url]) { ?>
            <li>
                <a class="tarjeta-acceso group flex h-full flex-col gap-2 rounded-3xl p-6 no-underline transition-all duration-300 hover:-translate-y-1 border border-primario-200/50 dark:border-primario-700/50 bg-[color:var(--fondo)]/80" href="<?= $e($url) ?>">
                    <span class="block font-bold text-lg texto-titular group-hover:text-primario-600 dark:group-hover:text-primario-400 transition-colors"><?= $e($nombre) ?></span>
                    <span class="block text-sm texto-suave leading-snug"><?= $e($detalle) ?></span>
                </a>
            </li>
        <?php } ?>
    </ul>

    <h2 class="pestana text-xl sm:text-2xl mb-5">Comunicados al personal</h2>

    <?php if ($campanas === []) { ?>
        <div class="tarjeta p-8 text-center">
            <p class="texto-suave">No hay comunicados vigentes dirigidos al personal de la entidad.</p>
            <p class="mt-4">
                <a class="boton boton-primario no-underline" href="/convocatorias">Consultar las convocatorias abiertas</a>
            </p>
        </div>
    <?php } else { ?>
        <ul class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($campanas as $campana) { ?>
                <li>
                    <?= $vistas->render('publico/campanas/_tarjeta', [
                        'campana' => $campana,
                        'imagenes' => $imagenes,
                        'esPersonal' => true,
                    ]) ?>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>
</div>

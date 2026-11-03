<?php
/**
 * Campañas de salud dirigidas a la comunidad.
 * Espera: $campanas (list<Campana>), $imagenes (array<int, Archivo>),
 *         $titulo (string), $descripcion (string).
 */
?>
<div class="mx-auto max-w-6xl px-4 py-10">
    <div class="relative overflow-hidden rounded-[2.5rem] cabecera-seccion p-10 text-white mb-10 shadow-2xl border border-white/10">
        <span class="mancha h-64 w-64 -right-16 -top-16 bg-[color:var(--color-marca-cian)] opacity-30 blur-3xl" aria-hidden="true"></span>
        <div class="relative z-10 max-w-2xl">
            <span class="text-xs font-bold uppercase tracking-widest text-white bg-white/10 px-3 py-1 rounded-full border border-white/10">Salud y Prevención</span>
            <h1 class="text-white mt-4 text-4xl lg:text-5xl font-extrabold tracking-tight"><?= $e($titulo) ?></h1>
            <p class="mt-4 text-lg text-primario-100 leading-relaxed"><?= $e($descripcion) ?></p>
        </div>
    </div>

    <?php if ($campanas === []) { ?>
        <?php /* Vacío honesto: no hay campaña vigente. Antes que inventar una,
                 se ofrece lo que sí existe. */ ?>
        <div class="tarjeta p-8 text-center">
            <p class="texto-suave">En este momento no hay campañas de salud vigentes.</p>
            <p class="mt-4">
                <a class="boton boton-primario no-underline" href="/noticias">Ver las noticias del hospital</a>
            </p>
        </div>
    <?php } else { ?>
        <ul class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($campanas as $campana) { ?>
                <li>
                    <?= $vistas->render('publico/campanas/_tarjeta', [
                        'campana' => $campana,
                        'imagenes' => $imagenes,
                        'esPersonal' => false,
                    ]) ?>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>
</div>

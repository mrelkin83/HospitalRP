<?php
/**
 * Portada del panel (Fase 0: cascarón honesto, sin módulos).
 * Espera: $usuarioNombre (string).
 */
?>
<h1 class="text-2xl">Hola, <?= $e($usuarioNombre) ?></h1>
<p class="mt-2 texto-suave">
    Este es el panel administrativo del sitio. Todavía no tiene módulos:
    llegarán fase por fase (contenido, trámites, documentos, transparencia).
</p>

<div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <section class="tarjeta">
        <h2 class="text-base">Fase 0 — Fundación</h2>
        <p class="mt-1 text-sm texto-suave">
            Autenticación, permisos, auditoría, archivos y sistema de diseño listos.
        </p>
        <p class="mt-3"><span class="insignia insignia-exito">En curso</span></p>
    </section>

    <section class="tarjeta">
        <h2 class="text-base">Siguiente: contenido</h2>
        <p class="mt-1 text-sm texto-suave">
            Páginas, noticias y avisos con aprobación, programación y versionado.
        </p>
        <p class="mt-3"><span class="insignia insignia-aviso">Pendiente</span></p>
    </section>

    <section class="tarjeta">
        <h2 class="text-base">Sistema de diseño</h2>
        <p class="mt-1 text-sm texto-suave">
            La muestra de componentes vive en <a href="/diseno">/diseno</a> (solo en desarrollo).
        </p>
    </section>
</div>

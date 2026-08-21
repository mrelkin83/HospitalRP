<?php
/**
 * Bandeja de noticias del panel.
 * Espera: $noticias (list<Noticia>), $mensaje (?string).
 */

use App\Modules\Contenido\Entity\EstadoAprobacion;

$insignia = static fn (EstadoAprobacion $estado): string => match ($estado) {
    EstadoAprobacion::Publicado => 'insignia-exito',
    EstadoAprobacion::Aprobado, EstadoAprobacion::EnRevision => 'insignia-aviso',
    EstadoAprobacion::Borrador => 'insignia-error',
};
?>
<div class="flex flex-wrap items-center gap-3">
    <h1 class="text-2xl">Noticias</h1>
    <a class="boton boton-primario ms-auto" href="/admin/noticias/nueva">Nueva noticia</a>
</div>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>

<?php if ($noticias === []) { ?>
    <div class="tarjeta mt-6 text-center">
        <h2 class="text-base">Todavía no hay noticias</h2>
        <p class="mt-1 texto-suave">Crea la primera con el botón «Nueva noticia».</p>
    </div>
<?php } else { ?>
    <ul class="mt-6 flex flex-col gap-3">
        <?php foreach ($noticias as $noticia) { ?>
            <li class="tarjeta flex flex-wrap items-center gap-x-4 gap-y-2">
                <div class="min-w-0 flex-1">
                    <a class="font-semibold texto-titular no-underline hover:underline"
                       href="/admin/noticias/<?= $noticia->id ?>"><?= $e($noticia->titulo) ?></a>
                    <p class="mt-0.5 text-sm texto-suave">
                        Actualizada el <?= $e((string) $noticia->actualizadoEn) ?>
                        <?php if ($noticia->publicarEn !== null) { ?>
                            · programada para <?= $e($noticia->publicarEn) ?>
                        <?php } ?>
                    </p>
                </div>
                <span class="insignia <?= $insignia($noticia->estado) ?>"><?= $e($noticia->estado->etiqueta()) ?></span>
                <?php if ($noticia->destacada) { ?>
                    <span class="insignia insignia-aviso">Destacada</span>
                <?php } ?>
            </li>
        <?php } ?>
    </ul>
<?php } ?>

<?php
/**
 * Bandeja de páginas institucionales.
 * Espera: $paginas (list<Pagina>), $mensaje (?string).
 */

use App\Modules\Contenido\Entity\EstadoAprobacion;

$insignia = static fn (EstadoAprobacion $estado): string => match ($estado) {
    EstadoAprobacion::Publicado => 'insignia-exito',
    EstadoAprobacion::Aprobado, EstadoAprobacion::EnRevision => 'insignia-aviso',
    EstadoAprobacion::Borrador => 'insignia-error',
};
?>
<div class="flex flex-wrap items-center gap-3">
    <h1 class="text-2xl">Páginas</h1>
    <a class="boton boton-primario ms-auto" href="/admin/paginas/nueva">Nueva página</a>
</div>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>

<?php if ($paginas === []) { ?>
    <div class="tarjeta mt-6 text-center">
        <h2 class="text-base">Todavía no hay páginas</h2>
        <p class="mt-1 texto-suave">Crea la primera con el botón «Nueva página».</p>
    </div>
<?php } else { ?>
    <ul class="mt-6 flex flex-col gap-3">
        <?php foreach ($paginas as $item) { ?>
            <li class="tarjeta flex flex-wrap items-center gap-x-4 gap-y-2">
                <div class="min-w-0 flex-1">
                    <a class="font-semibold texto-titular no-underline hover:underline"
                       href="/admin/paginas/<?= $item->id ?>"><?= $e($item->titulo) ?></a>
                    <p class="mt-0.5 text-sm texto-suave"><code><?= $e($item->ruta) ?></code></p>
                </div>
                <span class="insignia <?= $insignia($item->estado) ?>"><?= $e($item->estado->etiqueta()) ?></span>
                <?php if ($item->protegida) { ?>
                    <span class="insignia insignia-aviso">Obligatoria</span>
                <?php } ?>
            </li>
        <?php } ?>
    </ul>
<?php } ?>

<?php
/**
 * Bandeja de campañas.
 * Espera: $campanas (list<Campana>), $mensaje (?string), $tokenCsrf (string),
 *         $puedeEditar (bool).
 */

use App\Modules\Contenido\Entity\EstadoAprobacion;

$puedeEditar ??= true;

$insignia = static fn (EstadoAprobacion $estado): string => match ($estado) {
    EstadoAprobacion::Publicado => 'insignia-exito',
    EstadoAprobacion::Aprobado, EstadoAprobacion::EnRevision => 'insignia-aviso',
    EstadoAprobacion::Borrador => 'insignia-error',
};
?>
<div class="flex flex-wrap items-center gap-3">
    <h1 class="text-2xl">Campañas</h1>
    <a class="boton boton-primario ms-auto" href="/admin/campanas/nueva">Nueva campaña</a>
</div>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>

<?php if ($campanas === []) { ?>
    <div class="tarjeta mt-6 text-center">
        <h2 class="text-base">Todavía no hay campañas</h2>
        <p class="mt-1 texto-suave">Crea la primera con el botón «Nueva campaña».</p>
    </div>
<?php } else { ?>
    <ul class="mt-6 flex flex-col gap-3">
        <?php foreach ($campanas as $campana) { ?>
            <li class="tarjeta flex flex-wrap items-center gap-x-4 gap-y-2">
                <div class="min-w-0 flex-1">
                    <a class="font-semibold texto-titular no-underline hover:underline"
                       href="/admin/campanas/<?= $campana->id ?>"><?= $e($campana->titulo) ?></a>
                    <p class="mt-0.5 text-sm texto-suave">
                        <?php if ($campana->inicio !== null || $campana->fin !== null) { ?>
                            Vigencia: <?= $e($campana->inicio ?? '…') ?> — <?= $e($campana->fin ?? '…') ?>
                        <?php } else { ?>
                            Sin vigencia definida
                        <?php } ?>
                    </p>
                </div>
                <span class="insignia"><?= $e($campana->audiencia->pestana()) ?></span>
                <span class="insignia <?= $insignia($campana->estado) ?>"><?= $e($campana->estado->etiqueta()) ?></span>

                <?php if ($puedeEditar) { ?>
                    <span class="flex items-center gap-2">
                        <a class="boton boton-secundario text-sm py-1 px-3" href="/admin/campanas/<?= $campana->id ?>">Editar</a>
                        <?php /* Enviar a la papelera es borrado lógico: la fila queda
                                 con `eliminado_en` y se puede restaurar desde su
                                 versión. Por eso no se pide confirmación doble. */ ?>
                        <form method="post" action="/admin/campanas/<?= $campana->id ?>/eliminar">
                            <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                            <button type="submit" class="boton boton-peligro text-sm py-1 px-3">Papelera</button>
                        </form>
                    </span>
                <?php } ?>
            </li>
        <?php } ?>
    </ul>
<?php } ?>

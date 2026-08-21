<?php
/**
 * Bandeja de documentos.
 * Espera: $documentos (list<Documento>), $mensaje (?string).
 */

use App\Modules\Documentos\Entity\EstadoDocumento;

$insignia = static fn (EstadoDocumento $estado): string => match ($estado) {
    EstadoDocumento::Vigente => 'insignia-exito',
    EstadoDocumento::Borrador => 'insignia-aviso',
    EstadoDocumento::Derogado, EstadoDocumento::Historico => 'insignia-error',
};
?>
<div class="flex flex-wrap items-center gap-3">
    <h1 class="text-2xl">Documentos</h1>
    <a class="boton boton-primario ms-auto" href="/admin/documentos/nuevo">Nuevo documento</a>
</div>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>

<?php if ($documentos === []) { ?>
    <div class="tarjeta mt-6 text-center">
        <h2 class="text-base">Todavía no hay documentos</h2>
        <p class="mt-1 texto-suave">Crea el primero con el botón «Nuevo documento».</p>
    </div>
<?php } else { ?>
    <ul class="mt-6 flex flex-col gap-3">
        <?php foreach ($documentos as $doc) { ?>
            <li class="tarjeta flex flex-wrap items-center gap-x-4 gap-y-2">
                <div class="min-w-0 flex-1">
                    <a class="font-semibold texto-titular no-underline hover:underline"
                       href="/admin/documentos/<?= $doc->id ?>"><?= $e($doc->titulo) ?></a>
                    <p class="mt-0.5 text-sm texto-suave">
                        <?= $e($doc->fechaDocumento) ?>
                        <?php if ($doc->numeroActo !== null) { ?> · <?= $e($doc->numeroActo) ?><?php } ?>
                        <?php if ($doc->dependenciaNombre !== null) { ?> · <?= $e($doc->dependenciaNombre) ?><?php } ?>
                        · v<?= $doc->version ?>
                    </p>
                </div>
                <span class="insignia <?= $insignia($doc->estado) ?>"><?= $e($doc->estado->etiqueta()) ?></span>
                <?php if ($doc->publicado) { ?>
                    <span class="insignia insignia-exito">En el sitio</span>
                <?php } else { ?>
                    <span class="insignia insignia-aviso">No publicado</span>
                <?php } ?>
            </li>
        <?php } ?>
    </ul>
<?php } ?>

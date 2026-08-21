<?php
/**
 * Formulario de campaña (crear y editar).
 * Espera: $campana (?Campana), $enviado (?CampanaDatos), $errores, $mensaje,
 *         $historial, $tokenCsrf, $puedePublicar.
 */

use App\Modules\Contenido\Entity\EstadoAprobacion;

$valorTitulo = $enviado?->titulo ?? $campana?->titulo ?? '';
$valorResumen = $enviado?->resumen ?? $campana?->resumen ?? '';
$valorContenido = $enviado?->contenido ?? $campana?->contenido ?? '';
$valorPoblacion = $enviado?->poblacionObjetivo ?? $campana?->poblacionObjetivo ?? '';
$valorInicio = $enviado?->inicio ?? $campana?->inicio ?? '';
$valorFin = $enviado?->fin ?? $campana?->fin ?? '';

$accion = $campana === null ? '/admin/campanas' : '/admin/campanas/' . $campana->id;

$error = static function (string $campo) use ($errores, $e): string {
    if (!isset($errores[$campo])) {
        return '';
    }

    return '<p class="error-campo" id="error-' . $campo . '">' . $e($errores[$campo]) . '</p>';
};
?>
<div class="flex flex-wrap items-center gap-3">
    <h1 class="text-2xl"><?= $campana === null ? 'Nueva campaña' : 'Editar campaña' ?></h1>
    <?php if ($campana !== null) { ?>
        <span class="insignia <?= $campana->estado === EstadoAprobacion::Publicado ? 'insignia-exito' : 'insignia-aviso' ?>">
            <?= $e($campana->estado->etiqueta()) ?>
        </span>
    <?php } ?>
    <a class="ms-auto text-sm" href="/admin/campanas">Volver al listado</a>
</div>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>

<?php if ($errores !== []) { ?>
    <div class="alerta alerta-error mt-4" role="alert">
        Revisa los campos marcados: falta información o hay un dato inválido.
    </div>
<?php } ?>

<div class="mt-6 grid gap-8 lg:grid-cols-3">
    <form class="lg:col-span-2 flex flex-col gap-5" method="post"
          action="<?= $e($accion) ?>" enctype="multipart/form-data">
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">

        <div>
            <label class="etiqueta" for="titulo">Título</label>
            <input class="campo" type="text" id="titulo" name="titulo" required maxlength="255"
                   value="<?= $e($valorTitulo) ?>"
                   <?= isset($errores['titulo']) ? 'aria-invalid="true" aria-describedby="error-titulo"' : '' ?>>
            <?= $error('titulo') ?>
        </div>

        <div>
            <label class="etiqueta" for="resumen">Resumen (opcional)</label>
            <textarea class="campo" id="resumen" name="resumen" rows="2" maxlength="500"><?= $e($valorResumen) ?></textarea>
        </div>

        <div>
            <label class="etiqueta" for="contenido">Contenido</label>
            <textarea class="campo" id="contenido" name="contenido" rows="12" data-editor="contenido"
                      <?= isset($errores['contenido']) ? 'aria-invalid="true" aria-describedby="error-contenido"' : '' ?>><?= $e($valorContenido) ?></textarea>
            <?= $error('contenido') ?>
        </div>

        <div class="grid gap-5 sm:grid-cols-3">
            <div>
                <label class="etiqueta" for="poblacion_objetivo">Población objetivo</label>
                <input class="campo" type="text" id="poblacion_objetivo" name="poblacion_objetivo" maxlength="255"
                       value="<?= $e($valorPoblacion) ?>" placeholder="Niños menores de 5 años">
            </div>
            <div>
                <label class="etiqueta" for="inicio">Inicia</label>
                <input class="campo" type="date" id="inicio" name="inicio" value="<?= $e($valorInicio) ?>">
            </div>
            <div>
                <label class="etiqueta" for="fin">Termina</label>
                <input class="campo" type="date" id="fin" name="fin" value="<?= $e($valorFin) ?>">
                <?= $error('fin') ?>
            </div>
        </div>

        <fieldset class="tarjeta">
            <legend class="etiqueta px-1">Imagen (opcional)</legend>
            <?php if ($campana?->imagenId !== null) { ?>
                <p class="mb-3"><img class="max-h-40 rounded-md" src="/archivos/<?= $campana->imagenId ?>" alt="Imagen actual de la campaña"></p>
            <?php } ?>
            <div>
                <label class="etiqueta" for="imagen"><?= $campana?->imagenId !== null ? 'Reemplazar imagen' : 'Subir imagen' ?></label>
                <input class="campo" type="file" id="imagen" name="imagen" accept="image/jpeg,image/png,image/webp,image/avif">
                <?= $error('imagen') ?>
            </div>
            <div class="mt-3">
                <label class="etiqueta" for="imagen_alt">Descripción de la imagen (texto alternativo)</label>
                <input class="campo" type="text" id="imagen_alt" name="imagen_alt" maxlength="255"
                       <?= isset($errores['imagen_alt']) ? 'aria-invalid="true" aria-describedby="error-imagen_alt"' : '' ?>>
                <?= $error('imagen_alt') ?>
            </div>
        </fieldset>

        <div>
            <button type="submit" class="boton boton-primario">
                <?= $campana === null ? 'Guardar borrador' : 'Guardar cambios' ?>
            </button>
        </div>
    </form>

    <?php if ($campana !== null) { ?>
        <aside class="flex flex-col gap-6">
            <section class="tarjeta" aria-labelledby="t-estado">
                <h2 id="t-estado" class="text-base">Estado: <?= $e($campana->estado->etiqueta()) ?></h2>
                <div class="mt-3 flex flex-col gap-2">
                    <?php foreach (EstadoAprobacion::cases() as $destino) { ?>
                        <?php if (!$campana->estado->puedeCambiarA($destino)) { continue; } ?>
                        <?php $exigePermiso = in_array($destino, [EstadoAprobacion::Aprobado, EstadoAprobacion::Publicado], true); ?>
                        <form method="post" action="/admin/campanas/<?= $campana->id ?>/estado">
                            <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                            <input type="hidden" name="estado" value="<?= $e($destino->value) ?>">
                            <button type="submit"
                                    class="boton w-full <?= $destino === EstadoAprobacion::Publicado ? 'boton-primario' : 'boton-secundario' ?>"
                                    <?= $exigePermiso && !$puedePublicar ? 'disabled' : '' ?>>
                                <?= $e(match ($destino) {
                                    EstadoAprobacion::EnRevision => 'Enviar a revisión',
                                    EstadoAprobacion::Aprobado => 'Aprobar',
                                    EstadoAprobacion::Publicado => 'Publicar',
                                    EstadoAprobacion::Borrador => $campana->estado === EstadoAprobacion::Publicado ? 'Despublicar' : 'Devolver a borrador',
                                }) ?>
                            </button>
                        </form>
                    <?php } ?>
                </div>
            </section>

            <section class="tarjeta" aria-labelledby="t-historial">
                <h2 id="t-historial" class="text-base">Historial de versiones</h2>
                <?php if ($historial === []) { ?>
                    <p class="mt-2 text-sm texto-suave">Sin versiones todavía.</p>
                <?php } else { ?>
                    <ol class="mt-3 flex flex-col gap-2">
                        <?php foreach ($historial as $item) { ?>
                            <li class="flex items-center gap-2 text-sm">
                                <span class="min-w-0 flex-1">
                                    <strong>v<?= $item['version'] ?></strong>
                                    · <?= $e($item['creado_en']) ?>
                                    <?php if ($item['nota'] !== null) { ?>
                                        <span class="texto-suave">— <?= $e($item['nota']) ?></span>
                                    <?php } ?>
                                </span>
                                <?php if ($item !== $historial[0]) { ?>
                                    <form method="post" action="/admin/campanas/<?= $campana->id ?>/restaurar">
                                        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                                        <input type="hidden" name="version" value="<?= $item['version'] ?>">
                                        <button type="submit" class="boton boton-secundario">Restaurar</button>
                                    </form>
                                <?php } ?>
                            </li>
                        <?php } ?>
                    </ol>
                <?php } ?>
            </section>

            <section class="tarjeta" aria-labelledby="t-papelera">
                <h2 id="t-papelera" class="text-base">Papelera</h2>
                <form class="mt-3" method="post" action="/admin/campanas/<?= $campana->id ?>/eliminar">
                    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                    <button type="submit" class="boton boton-peligro w-full">Enviar a la papelera</button>
                </form>
            </section>
        </aside>
    <?php } ?>
</div>

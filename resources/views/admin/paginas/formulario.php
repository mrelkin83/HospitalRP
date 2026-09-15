<?php
/**
 * Formulario de página institucional (crear y editar).
 * Espera: $paginaEntidad (?Pagina), $enviado (?PaginaDatos),
 *         $errores (array<string,string>), $mensaje (?string), $historial,
 *         $tokenCsrf (string), $puedePublicar (bool).
 */

use App\Modules\Contenido\Entity\EstadoAprobacion;

$valorTitulo = $enviado?->titulo ?? $paginaEntidad?->titulo ?? '';
$valorRuta = $enviado?->ruta ?? $paginaEntidad?->ruta ?? '';
$valorResumen = $enviado?->resumen ?? $paginaEntidad?->resumen ?? '';
$valorContenido = $enviado?->contenido ?? $paginaEntidad?->contenido ?? '';
$valorNoindex = $enviado?->noindex ?? $paginaEntidad?->noindex ?? false;
$valorMetaTitulo = $enviado?->metaTitulo ?? $paginaEntidad?->metaTitulo ?? '';
$valorMetaDescripcion = $enviado?->metaDescripcion ?? $paginaEntidad?->metaDescripcion ?? '';
$aFechaLocal = static fn (?string $f): string => $f === null ? '' : str_replace(' ', 'T', substr($f, 0, 16));
$valorPublicarEn = $aFechaLocal($enviado?->publicarEn ?? $paginaEntidad?->publicarEn);
$valorDespublicarEn = $aFechaLocal($enviado?->despublicarEn ?? $paginaEntidad?->despublicarEn);

$accion = $paginaEntidad === null ? '/admin/paginas' : '/admin/paginas/' . $paginaEntidad->id;

$error = static function (string $campo) use ($errores, $e): string {
    if (!isset($errores[$campo])) {
        return '';
    }

    return '<p class="error-campo" id="error-' . $campo . '">' . $e($errores[$campo]) . '</p>';
};
?>
<div class="flex flex-wrap items-center gap-3">
    <h1 class="text-2xl"><?= $paginaEntidad === null ? 'Nueva página' : 'Editar página' ?></h1>
    <?php if ($paginaEntidad !== null) { ?>
        <span class="insignia <?= $paginaEntidad->estado === EstadoAprobacion::Publicado ? 'insignia-exito' : 'insignia-aviso' ?>">
            <?= $e($paginaEntidad->estado->etiqueta()) ?>
        </span>
        <?php if ($paginaEntidad->protegida) { ?>
            <span class="insignia insignia-aviso">Obligatoria por normativa</span>
        <?php } ?>
    <?php } ?>
    <a class="ms-auto text-sm" href="/admin/paginas">Volver al listado</a>
</div>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>

<?php if ($errores !== []) { ?>
    <?php /* El aviso NOMBRA lo que falta. Antes decía «revisa los campos
             marcados» y el funcionario los daba todos por llenos: el error
             solía estar en el texto alternativo de la imagen, dentro de un
             `fieldset` al que había que bajar. Un aviso que no dice qué pasa
             obliga a adivinar. */ ?>
    <div class="alerta alerta-error mt-4" role="alert">
        <p class="font-semibold">No se pudo guardar. Falta esto:</p>
        <ul class="mt-2 list-disc ps-5 flex flex-col gap-1">
            <?php foreach ($errores as $campo => $detalle) { ?>
                <li><a class="font-semibold" href="#<?= $e((string) $campo) ?>"><?= $e($detalle) ?></a></li>
            <?php } ?>
        </ul>
        <?php if (isset($errores['imagen_alt']) || isset($errores['imagen'])) { ?>
            <p class="mt-2 text-sm">
                Vuelve a elegir la imagen: por seguridad, el navegador no conserva
                el archivo cuando el formulario regresa con un error.
            </p>
        <?php } ?>
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
            <label class="etiqueta" for="ruta">Dirección (URL)</label>
            <input class="campo" type="text" id="ruta" name="ruta" required maxlength="255"
                   value="<?= $e($valorRuta) ?>" placeholder="/institucional/quienes-somos"
                   <?= isset($errores['ruta']) ? 'aria-invalid="true" aria-describedby="error-ruta"' : '' ?>>
            <p class="ayuda-campo">Minúsculas, números y guiones. Cambiarla rompe los enlaces ya compartidos.</p>
            <?= $error('ruta') ?>
        </div>

        <div>
            <label class="etiqueta" for="resumen">Resumen (opcional)</label>
            <textarea class="campo" id="resumen" name="resumen" rows="2" maxlength="500"><?= $e($valorResumen) ?></textarea>
        </div>

        <div>
            <label class="etiqueta" for="contenido">Contenido</label>
            <textarea class="campo" id="contenido" name="contenido" rows="14" data-editor="contenido"
                      <?= isset($errores['contenido']) ? 'aria-invalid="true" aria-describedby="error-contenido"' : '' ?>><?= $e($valorContenido) ?></textarea>
            <?= $error('contenido') ?>
        </div>

        <fieldset class="tarjeta">
            <legend class="etiqueta px-1">Imagen de encabezado (opcional)</legend>
            <?php if ($paginaEntidad?->imagenId !== null) { ?>
                <p class="mb-3"><img class="max-h-40 rounded-md" src="/archivos/<?= $paginaEntidad->imagenId ?>" alt="Imagen actual de la página"></p>
            <?php } ?>
            <div>
                <label class="etiqueta" for="imagen"><?= $paginaEntidad?->imagenId !== null ? 'Reemplazar imagen' : 'Subir imagen' ?></label>
                <input class="campo" type="file" id="imagen" name="imagen" accept="image/jpeg,image/png,image/webp,image/avif">
                <?= $error('imagen') ?>
            </div>
            <div class="mt-3">
                <label class="etiqueta" for="imagen_alt">Descripción de la imagen (texto alternativo)
                    <span class="texto-suave font-normal">— obligatorio si subes una imagen</span>
                </label>
                <input class="campo" type="text" id="imagen_alt" name="imagen_alt" maxlength="255"
                       <?= isset($errores['imagen_alt']) ? 'aria-invalid="true" aria-describedby="error-imagen_alt"' : '' ?>>
                <?= $error('imagen_alt') ?>
            </div>
        </fieldset>

        <fieldset class="tarjeta">
            <legend class="etiqueta px-1">Programación (opcional)</legend>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="etiqueta" for="publicar_en">Publicar automáticamente el</label>
                    <input class="campo" type="datetime-local" id="publicar_en" name="publicar_en" value="<?= $e($valorPublicarEn) ?>">
                </div>
                <div>
                    <label class="etiqueta" for="despublicar_en">Retirar automáticamente el</label>
                    <input class="campo" type="datetime-local" id="despublicar_en" name="despublicar_en" value="<?= $e($valorDespublicarEn) ?>">
                    <?= $error('despublicar_en') ?>
                </div>
            </div>
        </fieldset>

        <details class="tarjeta">
            <summary class="cursor-pointer font-semibold texto-titular">Buscadores (opcional)</summary>
            <div class="mt-4 flex flex-col gap-4">
                <div>
                    <label class="etiqueta" for="meta_titulo">Título para buscadores</label>
                    <input class="campo" type="text" id="meta_titulo" name="meta_titulo" maxlength="200" value="<?= $e($valorMetaTitulo) ?>">
                </div>
                <div>
                    <label class="etiqueta" for="meta_descripcion">Descripción para buscadores</label>
                    <textarea class="campo" id="meta_descripcion" name="meta_descripcion" rows="2" maxlength="320"><?= $e($valorMetaDescripcion) ?></textarea>
                </div>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="noindex" value="1" <?= $valorNoindex ? 'checked' : '' ?>>
                    <span>Pedir a los buscadores que no muestren esta página</span>
                </label>
            </div>
        </details>

        <div>
            <button type="submit" class="boton boton-primario">
                <?= $paginaEntidad === null ? 'Guardar borrador' : 'Guardar cambios' ?>
            </button>
        </div>
    </form>

    <?php if ($paginaEntidad !== null) { ?>
        <aside class="flex flex-col gap-6">
            <section class="tarjeta" aria-labelledby="t-estado">
                <h2 id="t-estado" class="text-base">Estado: <?= $e($paginaEntidad->estado->etiqueta()) ?></h2>
                <div class="mt-3 flex flex-col gap-2">
                    <?php foreach (EstadoAprobacion::cases() as $destino) { ?>
                        <?php if (!$paginaEntidad->estado->puedeCambiarA($destino)) { continue; } ?>
                        <?php $exigePermiso = in_array($destino, [EstadoAprobacion::Aprobado, EstadoAprobacion::Publicado], true); ?>
                        <form method="post" action="/admin/paginas/<?= $paginaEntidad->id ?>/estado">
                            <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                            <input type="hidden" name="estado" value="<?= $e($destino->value) ?>">
                            <button type="submit"
                                    class="boton w-full <?= $destino === EstadoAprobacion::Publicado ? 'boton-primario' : 'boton-secundario' ?>"
                                    <?= $exigePermiso && !$puedePublicar ? 'disabled' : '' ?>>
                                <?= $e(match ($destino) {
                                    EstadoAprobacion::EnRevision => 'Enviar a revisión',
                                    EstadoAprobacion::Aprobado => 'Aprobar',
                                    EstadoAprobacion::Publicado => 'Publicar',
                                    EstadoAprobacion::Borrador => $paginaEntidad->estado === EstadoAprobacion::Publicado ? 'Despublicar' : 'Devolver a borrador',
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
                                    <form method="post" action="/admin/paginas/<?= $paginaEntidad->id ?>/restaurar">
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

            <?php if (!$paginaEntidad->protegida) { ?>
                <section class="tarjeta" aria-labelledby="t-papelera">
                    <h2 id="t-papelera" class="text-base">Papelera</h2>
                    <p class="mt-2 text-sm texto-suave">La página sale del sitio, pero no se borra de la base.</p>
                    <form class="mt-3" method="post" action="/admin/paginas/<?= $paginaEntidad->id ?>/eliminar">
                        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                        <button type="submit" class="boton boton-peligro w-full">Enviar a la papelera</button>
                    </form>
                </section>
            <?php } ?>
        </aside>
    <?php } ?>
</div>

<?php
/**
 * Formulario de noticia: sirve para crear (noticia null) y editar.
 * Espera: $noticia (?Noticia), $enviado (?NoticiaDatos), $errores (array<string,string>),
 *         $mensaje (?string), $categorias (list<{id,nombre}>), $historial (list),
 *         $tokenCsrf (string), $puedePublicar (bool).
 *
 * $enviado (lo que el usuario acababa de escribir) manda sobre $noticia:
 * un error de validación no borra el trabajo de nadie.
 */

use App\Modules\Contenido\Entity\EstadoAprobacion;

$v = static fn (string $campo, ?string $deNoticia): string => match (true) {
    $enviado !== null => (string) ($enviado->{$campo} ?? ''),
    default => (string) ($deNoticia ?? ''),
};

$valorTitulo = $enviado?->titulo ?? $noticia?->titulo ?? '';
$valorResumen = $enviado?->resumen ?? $noticia?->resumen ?? '';
$valorContenido = $enviado?->contenido ?? $noticia?->contenido ?? '';
$valorCategoria = $enviado?->categoriaId ?? $noticia?->categoriaId;
$valorDestacada = $enviado?->destacada ?? $noticia?->destacada ?? false;
$valorMetaTitulo = $enviado?->metaTitulo ?? $noticia?->metaTitulo ?? '';
$valorMetaDescripcion = $enviado?->metaDescripcion ?? $noticia?->metaDescripcion ?? '';
$aFechaLocal = static fn (?string $f): string => $f === null ? '' : str_replace(' ', 'T', substr($f, 0, 16));
$valorPublicarEn = $aFechaLocal($enviado?->publicarEn ?? $noticia?->publicarEn);
$valorDespublicarEn = $aFechaLocal($enviado?->despublicarEn ?? $noticia?->despublicarEn);

$accion = $noticia === null ? '/admin/noticias' : '/admin/noticias/' . $noticia->id;

$error = static function (string $campo) use ($errores, $e): string {
    if (!isset($errores[$campo])) {
        return '';
    }

    return '<p class="error-campo" id="error-' . $campo . '">' . $e($errores[$campo]) . '</p>';
};
?>
<div class="flex flex-wrap items-center gap-3">
    <h1 class="text-2xl"><?= $noticia === null ? 'Nueva noticia' : 'Editar noticia' ?></h1>
    <?php if ($noticia !== null) { ?>
        <span class="insignia <?= $noticia->estado === EstadoAprobacion::Publicado ? 'insignia-exito' : 'insignia-aviso' ?>">
            <?= $e($noticia->estado->etiqueta()) ?>
        </span>
    <?php } ?>
    <a class="ms-auto text-sm" href="/admin/noticias">Volver al listado</a>
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
            <label class="etiqueta" for="resumen">Resumen</label>
            <textarea class="campo" id="resumen" name="resumen" rows="3" required maxlength="500"
                      <?= isset($errores['resumen']) ? 'aria-invalid="true" aria-describedby="error-resumen"' : '' ?>><?= $e($valorResumen) ?></textarea>
            <p class="ayuda-campo">Dos o tres frases. Aparece en los listados y en los resultados de búsqueda.</p>
            <?= $error('resumen') ?>
        </div>

        <div>
            <label class="etiqueta" for="contenido">Contenido</label>
            <textarea class="campo" id="contenido" name="contenido" rows="14"
                      <?= isset($errores['contenido']) ? 'aria-invalid="true" aria-describedby="error-contenido"' : '' ?>><?= $e($valorContenido) ?></textarea>
            <?= $error('contenido') ?>
        </div>

        <fieldset class="tarjeta">
            <legend class="etiqueta px-1">Imagen destacada</legend>
            <?php if ($noticia?->imagenId !== null) { ?>
                <p class="mb-3"><img class="max-h-40 rounded-md" src="/archivos/<?= $noticia->imagenId ?>" alt="Imagen destacada actual"></p>
            <?php } ?>
            <div>
                <label class="etiqueta" for="imagen"><?= $noticia?->imagenId !== null ? 'Reemplazar imagen' : 'Subir imagen' ?></label>
                <input class="campo" type="file" id="imagen" name="imagen" accept="image/jpeg,image/png,image/webp,image/avif">
                <p class="ayuda-campo">JPG, PNG, WebP o AVIF. Máximo 20 MB.</p>
                <?= $error('imagen') ?>
            </div>
            <div class="mt-3">
                <label class="etiqueta" for="imagen_alt">Descripción de la imagen (texto alternativo)</label>
                <input class="campo" type="text" id="imagen_alt" name="imagen_alt" maxlength="255"
                       <?= isset($errores['imagen_alt']) ? 'aria-invalid="true" aria-describedby="error-imagen_alt"' : '' ?>>
                <p class="ayuda-campo">Obligatoria si subes imagen. Ejemplo: «Enfermera vacunando a una niña en la sede La Nevada».</p>
                <?= $error('imagen_alt') ?>
            </div>
        </fieldset>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="etiqueta" for="categoria_id">Categoría</label>
                <select class="campo" id="categoria_id" name="categoria_id">
                    <option value="">Sin categoría</option>
                    <?php foreach ($categorias as $categoria) { ?>
                        <option value="<?= $categoria['id'] ?>" <?= $valorCategoria === $categoria['id'] ? 'selected' : '' ?>>
                            <?= $e($categoria['nombre']) ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="flex items-end pb-2">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="destacada" value="1" <?= $valorDestacada ? 'checked' : '' ?>>
                    <span>Destacar en la portada</span>
                </label>
            </div>
        </div>

        <fieldset class="tarjeta">
            <legend class="etiqueta px-1">Programación (opcional)</legend>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="etiqueta" for="publicar_en">Publicar automáticamente el</label>
                    <input class="campo" type="datetime-local" id="publicar_en" name="publicar_en" value="<?= $e($valorPublicarEn) ?>">
                    <p class="ayuda-campo">Necesita estar aprobada para publicarse sola.</p>
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
            </div>
        </details>

        <div>
            <button type="submit" class="boton boton-primario">
                <?= $noticia === null ? 'Guardar borrador' : 'Guardar cambios' ?>
            </button>
        </div>
    </form>

    <?php if ($noticia !== null) { ?>
        <aside class="flex flex-col gap-6">
            <section class="tarjeta" aria-labelledby="t-estado">
                <h2 id="t-estado" class="text-base">Estado: <?= $e($noticia->estado->etiqueta()) ?></h2>
                <div class="mt-3 flex flex-col gap-2">
                    <?php foreach (EstadoAprobacion::cases() as $destino) { ?>
                        <?php if (!$noticia->estado->puedeCambiarA($destino)) { continue; } ?>
                        <?php $exigePermiso = in_array($destino, [EstadoAprobacion::Aprobado, EstadoAprobacion::Publicado], true); ?>
                        <form method="post" action="/admin/noticias/<?= $noticia->id ?>/estado">
                            <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                            <input type="hidden" name="estado" value="<?= $e($destino->value) ?>">
                            <button type="submit"
                                    class="boton w-full <?= $destino === EstadoAprobacion::Publicado ? 'boton-primario' : 'boton-secundario' ?>"
                                    <?= $exigePermiso && !$puedePublicar ? 'disabled' : '' ?>>
                                <?= $e(match ($destino) {
                                    EstadoAprobacion::EnRevision => 'Enviar a revisión',
                                    EstadoAprobacion::Aprobado => 'Aprobar',
                                    EstadoAprobacion::Publicado => 'Publicar',
                                    EstadoAprobacion::Borrador => $noticia->estado === EstadoAprobacion::Publicado ? 'Despublicar' : 'Devolver a borrador',
                                }) ?>
                            </button>
                        </form>
                    <?php } ?>
                    <?php if (!$puedePublicar) { ?>
                        <p class="text-sm texto-suave">Aprobar y publicar requieren el permiso de publicación.</p>
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
                                    <form method="post" action="/admin/noticias/<?= $noticia->id ?>/restaurar">
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
                <p class="mt-2 text-sm texto-suave">La noticia sale del sitio y del listado, pero no se borra de la base.</p>
                <form class="mt-3" method="post" action="/admin/noticias/<?= $noticia->id ?>/eliminar">
                    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                    <button type="submit" class="boton boton-peligro w-full">Enviar a la papelera</button>
                </form>
            </section>
        </aside>
    <?php } ?>
</div>

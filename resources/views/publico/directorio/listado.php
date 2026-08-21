<?php
/**
 * /directorio — profesionales autorizados, con filtro por especialidad.
 * Espera: $profesionales (list<Profesional>), $especialidades (list<Especialidad>),
 *         $fotos (array<int, Archivo>).
 */
?>
<div class="mx-auto max-w-6xl px-4 py-10">
    <h1>Directorio médico</h1>
    <p class="mt-3 texto-suave max-w-prose">
        Profesionales que autorizaron la publicación de su perfil.
    </p>

    <?php if ($especialidades !== []) { ?>
        <div class="mt-6 max-w-xs">
            <label class="etiqueta" for="filtro-especialidad">Filtrar por especialidad</label>
            <select class="campo" id="filtro-especialidad" data-filtro="lista-directorio">
                <option value="">Todas las especialidades</option>
                <?php foreach ($especialidades as $especialidad) { ?>
                    <option value="<?= $especialidad->id ?>"><?= $e($especialidad->nombre) ?></option>
                <?php } ?>
            </select>
            <p class="mt-2 text-sm texto-suave" aria-live="polite" data-filtro-resultado="lista-directorio"></p>
        </div>
    <?php } ?>

    <?php if ($profesionales === []) { ?>
        <p class="mt-6 texto-suave">Pronto publicaremos el directorio de profesionales.</p>
    <?php } else { ?>
        <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" id="lista-directorio">
            <?php foreach ($profesionales as $profesional) { ?>
                <li data-valores="<?= implode(',', $profesional->especialidadIds) ?>">
                    <article class="tarjeta h-full flex gap-4">
                        <?php $foto = $profesional->fotoId !== null ? ($fotos[$profesional->fotoId] ?? null) : null; ?>
                        <?php if ($foto !== null) { ?>
                            <img class="h-16 w-16 rounded-full object-cover" src="/archivos/<?= $foto->id ?>"
                                 alt="" loading="lazy" width="64" height="64">
                        <?php } ?>
                        <div>
                            <h2 class="text-base">
                                <a class="texto-titular no-underline hover:underline" href="/directorio/<?= $e($profesional->slug) ?>">
                                    <?= $e($profesional->nombre) ?>
                                </a>
                            </h2>
                            <p class="mt-1 text-sm texto-suave"><?= $e(implode(' · ', $profesional->especialidadNombres)) ?></p>
                        </div>
                    </article>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>
</div>

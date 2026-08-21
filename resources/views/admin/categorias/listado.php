<?php
/**
 * Categorías de contenido.
 * Espera: $categorias (list<fila>), $mensaje (?string), $error (?string), $tokenCsrf.
 */
$etiquetaTipo = static fn (string $tipo): string => match ($tipo) {
    'noticia' => 'Noticias',
    'documento' => 'Documentos',
    'campana' => 'Campañas',
    'faq' => 'Preguntas frecuentes',
    default => $tipo,
};
?>
<h1 class="text-2xl">Categorías</h1>
<p class="mt-2 texto-suave">Organizan noticias, campañas, documentos y preguntas frecuentes.</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>

<form class="tarjeta mt-6 grid gap-4 sm:grid-cols-[2fr_1fr_auto_auto_auto] sm:items-end" method="post" action="/admin/categorias">
    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
    <div>
        <label class="etiqueta" for="nombre-nueva">Nombre</label>
        <input class="campo" id="nombre-nueva" type="text" name="nombre" required maxlength="120">
    </div>
    <div>
        <label class="etiqueta" for="tipo-nueva">Para</label>
        <select class="campo" id="tipo-nueva" name="tipo">
            <option value="noticia">Noticias</option>
            <option value="campana">Campañas</option>
            <option value="documento">Documentos</option>
            <option value="faq">Preguntas frecuentes</option>
        </select>
    </div>
    <div>
        <label class="etiqueta" for="orden-nueva">Orden</label>
        <input class="campo w-20" id="orden-nueva" type="number" name="orden" value="0">
    </div>
    <label class="flex items-center gap-2 pb-2">
        <input type="checkbox" name="activa" value="1" checked>
        Activa
    </label>
    <button type="submit" class="boton boton-primario">Crear</button>
</form>

<ul class="mt-6 flex flex-col gap-3">
    <?php foreach ($categorias as $categoria) { ?>
        <li class="tarjeta">
            <form class="grid gap-3 sm:grid-cols-[2fr_1fr_auto_auto_auto] sm:items-center" method="post" action="/admin/categorias/<?= (int) $categoria['id'] ?>">
                <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
                <input type="hidden" name="tipo" value="<?= $e((string) $categoria['tipo']) ?>">
                <div>
                    <label class="sr-only" for="nombre-<?= $categoria['id'] ?>">Nombre</label>
                    <input class="campo" id="nombre-<?= $categoria['id'] ?>" type="text" name="nombre" required maxlength="120"
                           value="<?= $e((string) $categoria['nombre']) ?>">
                </div>
                <span class="text-sm texto-suave"><?= $e($etiquetaTipo((string) $categoria['tipo'])) ?></span>
                <div>
                    <label class="sr-only" for="orden-<?= $categoria['id'] ?>">Orden</label>
                    <input class="campo w-20" id="orden-<?= $categoria['id'] ?>" type="number" name="orden" value="<?= (int) $categoria['orden'] ?>">
                </div>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="activa" value="1" <?= ((int) $categoria['activa']) === 1 ? 'checked' : '' ?>>
                    Activa
                </label>
                <button type="submit" class="boton boton-secundario">Guardar</button>
            </form>
        </li>
    <?php } ?>
</ul>

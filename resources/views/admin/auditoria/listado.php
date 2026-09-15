<?php
/**
 * Bandeja del registro de auditoría.
 * Espera: $resultado (registros, total, paginas, pagina), $filtros,
 * $acciones (list<string>), $entidades (list<string>), $etiquetas (map).
 */

use App\Core\Support\Fechas;

$enlacePagina = static function (int $pagina) use ($filtros): string {
    $parametros = array_filter($filtros, static fn (string $valor): bool => $valor !== '');
    $parametros['pagina'] = (string) $pagina;

    return '/admin/auditoria?' . http_build_query($parametros);
};
?>
<h1 class="text-2xl">Auditoría</h1>
<p class="mt-2 texto-suave">
    Qué se hizo en el sitio, quién lo hizo y desde dónde. Es un registro de solo
    lectura: ni el panel ni el administrador pueden borrar o editar una línea.
</p>

<form class="tarjeta mt-6" method="get" action="/admin/auditoria">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div>
            <label class="etiqueta" for="filtro-usuario">Usuario</label>
            <input class="campo" id="filtro-usuario" type="text" name="usuario" value="<?= $e($filtros['usuario']) ?>">
        </div>
        <div>
            <label class="etiqueta" for="filtro-accion">Acción</label>
            <select class="campo" id="filtro-accion" name="accion">
                <option value="">Todas</option>
                <?php foreach ($acciones as $accion) { ?>
                    <option value="<?= $e($accion) ?>" <?= $filtros['accion'] === $accion ? 'selected' : '' ?>>
                        <?= $e($etiquetas[$accion] ?? $accion) ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div>
            <label class="etiqueta" for="filtro-entidad">Sección</label>
            <select class="campo" id="filtro-entidad" name="entidad">
                <option value="">Todas</option>
                <?php foreach ($entidades as $entidad) { ?>
                    <option value="<?= $e($entidad) ?>" <?= $filtros['entidad'] === $entidad ? 'selected' : '' ?>><?= $e($entidad) ?></option>
                <?php } ?>
            </select>
        </div>
        <div>
            <label class="etiqueta" for="filtro-desde">Desde</label>
            <input class="campo" id="filtro-desde" type="date" name="desde" value="<?= $e($filtros['desde']) ?>">
        </div>
        <div>
            <label class="etiqueta" for="filtro-hasta">Hasta</label>
            <input class="campo" id="filtro-hasta" type="date" name="hasta" value="<?= $e($filtros['hasta']) ?>">
        </div>
    </div>
    <p class="mt-4 flex gap-2">
        <button type="submit" class="boton boton-primario">Filtrar</button>
        <a class="boton boton-secundario" href="/admin/auditoria">Quitar filtros</a>
    </p>
</form>

<p class="mt-6 text-sm texto-suave">
    <?= (int) $resultado['total'] ?> registro(s).
    Página <?= (int) $resultado['pagina'] ?> de <?= (int) $resultado['paginas'] ?>.
</p>

<div class="mt-4 overflow-x-auto">
    <table class="w-full text-sm">
        <caption class="sr-only">Registro de auditoría</caption>
        <thead>
            <tr class="text-start">
                <th class="p-2 text-start">Fecha</th>
                <th class="p-2 text-start">Usuario</th>
                <th class="p-2 text-start">Acción</th>
                <th class="p-2 text-start">Sección</th>
                <th class="p-2 text-start">Detalle</th>
                <th class="p-2 text-start">IP</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($resultado['registros'] as $registro) { ?>
                <tr class="border-t borde-suave">
                    <td class="p-2 whitespace-nowrap"><?= $e(Fechas::conHora((string) $registro['creado_en'])) ?></td>
                    <td class="p-2"><?= $e((string) ($registro['usuario_nombre'] ?? 'sistema')) ?></td>
                    <td class="p-2"><?= $e($etiquetas[(string) $registro['accion']] ?? (string) $registro['accion']) ?></td>
                    <td class="p-2"><?= $e((string) $registro['entidad']) ?><?= $registro['entidad_id'] !== null ? ' #' . (int) $registro['entidad_id'] : '' ?></td>
                    <td class="p-2"><?= $e((string) ($registro['descripcion'] ?? '')) ?></td>
                    <td class="p-2 texto-suave"><?= $e((string) ($registro['ip_texto'] ?? '')) ?></td>
                </tr>
            <?php } ?>
            <?php if ($resultado['registros'] === []) { ?>
                <tr><td class="p-4 texto-suave" colspan="6">No hay registros con esos filtros.</td></tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<?php if ((int) $resultado['paginas'] > 1) { ?>
    <nav class="mt-6 flex gap-2" aria-label="Paginación del registro">
        <?php if ((int) $resultado['pagina'] > 1) { ?>
            <a class="boton boton-secundario" href="<?= $e($enlacePagina((int) $resultado['pagina'] - 1)) ?>">Anterior</a>
        <?php } ?>
        <?php if ((int) $resultado['pagina'] < (int) $resultado['paginas']) { ?>
            <a class="boton boton-secundario" href="<?= $e($enlacePagina((int) $resultado['pagina'] + 1)) ?>">Siguiente</a>
        <?php } ?>
    </nav>
<?php } ?>

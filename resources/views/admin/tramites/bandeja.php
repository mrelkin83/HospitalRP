<?php
/**
 * Bandeja de trámites.
 * Espera: $tramites (list<fila>), $tipos (list<TipoTramite>), $filtros, $mensaje.
 */

use App\Core\Support\Fechas;

$hoy = date('Y-m-d');
$pronto = date('Y-m-d', strtotime('+3 days'));
?>
<h1 class="text-2xl">Bandeja de trámites</h1>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>

<form class="tarjeta mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end" method="get" action="/admin/tramites">
    <div>
        <label class="etiqueta" for="f-q">Buscar</label>
        <input class="campo" type="search" id="f-q" name="q" value="<?= $e((string) ($filtros['q'] ?? '')) ?>"
               placeholder="Radicado, asunto, nombre o documento">
    </div>
    <div>
        <label class="etiqueta" for="f-tipo">Tipo</label>
        <select class="campo" id="f-tipo" name="tipo">
            <option value="">Todos</option>
            <?php foreach ($tipos as $tipo) { ?>
                <option value="<?= $tipo->id ?>" <?= ($filtros['tipo'] ?? null) === $tipo->id ? 'selected' : '' ?>>
                    <?= $e($tipo->nombre) ?>
                </option>
            <?php } ?>
        </select>
    </div>
    <div>
        <label class="etiqueta" for="f-estado">Estado</label>
        <select class="campo" id="f-estado" name="estado">
            <option value="">Todos</option>
            <?php foreach (['recibido' => 'Recibido', 'en_gestion' => 'En gestión', 'respondido' => 'Respondido', 'cerrado' => 'Cerrado'] as $codigo => $nombre) { ?>
                <option value="<?= $codigo ?>" <?= ($filtros['estado'] ?? null) === $codigo ? 'selected' : '' ?>><?= $nombre ?></option>
            <?php } ?>
        </select>
    </div>
    <div>
        <label class="etiqueta" for="f-venc">Vencimiento</label>
        <select class="campo" id="f-venc" name="vencimiento">
            <option value="">Todos</option>
            <option value="vencidos" <?= ($filtros['vencimiento'] ?? null) === 'vencidos' ? 'selected' : '' ?>>Vencidos</option>
            <option value="proximos" <?= ($filtros['vencimiento'] ?? null) === 'proximos' ? 'selected' : '' ?>>Vencen en 3 días</option>
        </select>
    </div>
    <button type="submit" class="boton boton-primario">Filtrar</button>
</form>

<?php if ($tramites === []) { ?>
    <div class="tarjeta mt-6 text-center">
        <p class="texto-suave">No hay trámites con esos filtros.</p>
    </div>
<?php } else { ?>
    <ul class="mt-6 flex flex-col gap-3">
        <?php foreach ($tramites as $tramite) { ?>
            <?php
            $abierto = $tramite['respondido_en'] === null && ((int) $tramite['estado_es_final']) !== 1;
            $vencido = $abierto && (string) $tramite['vence_en'] < $hoy;
            $porVencer = $abierto && !$vencido && (string) $tramite['vence_en'] <= $pronto;
            ?>
            <li class="tarjeta flex flex-wrap items-center gap-x-4 gap-y-2 <?= $vencido ? 'border-error-600' : ($porVencer ? 'border-aviso-600' : '') ?>">
                <div class="min-w-0 flex-1">
                    <a class="font-semibold texto-titular no-underline hover:underline"
                       href="/admin/tramites/<?= (int) $tramite['id'] ?>">
                        <?= $e((string) $tramite['radicado']) ?> — <?= $e((string) $tramite['asunto']) ?>
                    </a>
                    <p class="mt-0.5 text-sm texto-suave">
                        <?= $e((string) $tramite['tipo_nombre']) ?>
                        · <?= ((int) $tramite['anonimo']) === 1 ? 'Anónimo' : $e((string) ($tramite['nombre'] ?? '')) ?>
                        · recibido <?= $e(Fechas::larga((string) $tramite['recibido_en'])) ?>
                        <?php if (($tramite['asignado_nombre'] ?? null) !== null) { ?>
                            · asignado a <?= $e((string) $tramite['asignado_nombre']) ?>
                        <?php } ?>
                    </p>
                </div>
                <?php if ($vencido) { ?>
                    <span class="insignia insignia-error">VENCIDO <?= $e((string) $tramite['vence_en']) ?></span>
                <?php } elseif ($porVencer) { ?>
                    <span class="insignia insignia-aviso">Vence <?= $e((string) $tramite['vence_en']) ?></span>
                <?php } elseif ($abierto) { ?>
                    <span class="text-sm texto-suave">vence <?= $e((string) $tramite['vence_en']) ?></span>
                <?php } ?>
                <span class="insignia <?= ((int) $tramite['estado_es_final']) === 1 ? 'insignia-exito' : 'insignia-aviso' ?>">
                    <?= $e((string) $tramite['estado_nombre']) ?>
                </span>
            </li>
        <?php } ?>
    </ul>
<?php } ?>

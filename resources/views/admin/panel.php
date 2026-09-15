<?php
/**
 * Portada del panel: pendientes y últimos movimientos.
 * Espera: $usuarioNombre (string), $cifras (array<string,int>),
 * $movimientos (list<fila de auditoría>), $puede (callable(string): bool).
 *
 * Aquí vivió un rato un tablero «Stark Industries / J.A.R.V.I.S.»: cian sobre
 * negro, fondos translúcidos con desenfoque y rótulos como «SECURITY LEVEL:
 * ALPHA». Se retiró el 2026-09-15 por ilegible —el cian claro sobre vidrio
 * difuminado no alcanza contraste AA y cansa la vista— y porque el panel de
 * una E.S.E. no se llama como una empresa de ficción.
 *
 * Esto usa los componentes del sistema de diseño (.tarjeta, .alerta-*) y nada
 * más. Cada cifra es un enlace a la pantalla donde se resuelve.
 */

use App\Core\Support\Fechas;

$puede ??= static fn (string $permiso): bool => true;

// Cada tarjeta declara el permiso de la pantalla a la que lleva y se descarta
// si el usuario no lo tiene: un atajo que responde 403 al pulsarlo no informa.
$tarjetas = array_values(array_filter([
    [
        'titulo' => 'Trámites abiertos',
        'cifra' => $cifras['tramitesAbiertos'],
        'url' => '/admin/tramites',
        'ayuda' => 'PQRSDF y solicitudes ciudadanas sin cerrar.',
        'permiso' => 'tramites.gestionar',
    ],
    [
        'titulo' => 'Noticias en borrador',
        'cifra' => $cifras['noticiasBorrador'],
        'url' => '/admin/noticias',
        'ayuda' => 'Redactadas pero todavía sin publicar.',
        'permiso' => 'contenido.ver',
    ],
    [
        'titulo' => 'Documentos sin publicar',
        'cifra' => $cifras['documentosSinPublicar'],
        'url' => '/admin/documentos',
        'ayuda' => 'Cargados pero no visibles en el sitio.',
        'permiso' => 'documentos.ver',
    ],
    [
        'titulo' => 'Usuarios activos',
        'cifra' => $cifras['usuariosActivos'],
        'url' => '/admin/usuarios',
        'ayuda' => 'Cuentas con acceso al panel.',
        'permiso' => 'sistema.usuarios',
    ],
], static fn (array $tarjeta): bool => $puede($tarjeta['permiso'])));
?>
<div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
    <h1 class="text-2xl">Hola, <?= $e($usuarioNombre) ?></h1>
    <p class="texto-suave text-sm">Esto es lo que está pendiente hoy.</p>
</div>

<?php if ($tarjetas !== []) { ?>
<div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <?php foreach ($tarjetas as $tarjeta) { ?>
        <a class="tarjeta no-underline flex flex-col gap-1 hover:border-primario-400 transition-colors" href="<?= $e($tarjeta['url']) ?>">
            <span class="text-3xl font-extrabold texto-titular tabular-nums"><?= (int) $tarjeta['cifra'] ?></span>
            <h2 class="text-base font-semibold texto-titular"><?= $e($tarjeta['titulo']) ?></h2>
            <p class="text-sm texto-suave leading-snug"><?= $e($tarjeta['ayuda']) ?></p>
        </a>
    <?php } ?>
</div>
<?php } ?>

<?php if ($puede('sistema.usuarios') && ($cifras['passwordProvisional'] > 0 || $cifras['cuentasBloqueadas'] > 0)) { ?>
    <div class="alerta alerta-aviso mt-8" role="status">
        <h2 class="text-base font-semibold">Cuentas que requieren atención</h2>
        <ul class="mt-2 list-disc ps-5 text-sm flex flex-col gap-1">
            <?php if ($cifras['passwordProvisional'] > 0) { ?>
                <li>
                    <strong><?= (int) $cifras['passwordProvisional'] ?></strong>
                    con contraseña provisional sin cambiar: quien la creó todavía la conoce.
                </li>
            <?php } ?>
            <?php if ($cifras['cuentasBloqueadas'] > 0) { ?>
                <li>
                    <strong><?= (int) $cifras['cuentasBloqueadas'] ?></strong>
                    bloqueadas por intentos fallidos de acceso.
                </li>
            <?php } ?>
        </ul>
        <p class="mt-3">
            <a class="boton boton-secundario no-underline" href="/admin/usuarios">Ir a usuarios</a>
        </p>
    </div>
<?php } ?>

<?php /* La auditoría es de control interno y de sistema: quien no puede
         abrirla tampoco ve su resumen aquí. */ ?>
<?php if ($puede('sistema.auditoria')) { ?>
<section class="mt-10" aria-labelledby="movimientos-titulo">
    <div class="flex flex-wrap items-center gap-3 border-b borde-suave pb-3 mb-4">
        <h2 id="movimientos-titulo" class="text-xl">Últimos movimientos</h2>
        <a class="ms-auto text-sm" href="/admin/auditoria">Ver toda la auditoría</a>
    </div>

    <?php if ($movimientos === []) { ?>
        <p class="tarjeta texto-suave text-sm">Todavía no hay actividad registrada.</p>
    <?php } else { ?>
        <ul class="flex flex-col gap-2">
            <?php foreach ($movimientos as $movimiento) { ?>
                <li class="tarjeta text-sm flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <span class="texto-suave text-xs shrink-0 tabular-nums"><?= $e(Fechas::conHora((string) $movimiento['creado_en'])) ?></span>
                    <strong class="shrink-0"><?= $e((string) ($movimiento['usuario_nombre'] ?? 'sistema')) ?></strong>
                    <span class="min-w-0 flex-1"><?= $e((string) ($movimiento['descripcion'] ?? $movimiento['accion'])) ?></span>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>
</section>
<?php } ?>

<?php
/**
 * Políticas versionadas.
 * Espera: $porTipo (array<string, list<fila>>), $tipos (array<string,string>),
 *         $mensaje, $error, $tokenCsrf.
 */
?>
<h1 class="text-2xl">Políticas institucionales</h1>
<p class="mt-2 texto-suave max-w-prose">
    Una versión publicada <strong>no se edita jamás</strong>: la evidencia de
    cada autorización apunta a la versión exacta que el ciudadano aceptó.
    Para cambiar un texto, publica una versión nueva: entra en vigencia al
    instante y cierra la anterior.
</p>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>

<details class="tarjeta mt-6">
    <summary class="cursor-pointer font-semibold texto-titular">Publicar versión nueva</summary>
    <form class="mt-4" method="post" action="/admin/politicas">
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="etiqueta" for="p-tipo">Política</label>
                <select class="campo" id="p-tipo" name="tipo">
                    <?php foreach ($tipos as $codigo => $nombre) { ?>
                        <option value="<?= $e($codigo) ?>"><?= $e($nombre) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div>
                <label class="etiqueta" for="p-version">Versión</label>
                <input class="campo" id="p-version" type="text" name="version" required maxlength="20" placeholder="2.0">
                <p class="ayuda-campo">Ejemplo: 2.0, 2026-1…</p>
            </div>
            <div>
                <label class="etiqueta" for="p-titulo">Título</label>
                <input class="campo" id="p-titulo" type="text" name="titulo" required maxlength="255">
            </div>
        </div>
        <div class="mt-4">
            <label class="etiqueta" for="contenido">Contenido</label>
            <textarea class="campo" id="contenido" name="contenido" rows="12" data-editor="contenido" required></textarea>
        </div>
        <p class="mt-4"><button type="submit" class="boton boton-primario">Publicar y poner en vigencia</button></p>
    </form>
</details>

<div class="mt-6 flex flex-col gap-6">
    <?php foreach ($tipos as $codigo => $nombre) { ?>
        <section class="tarjeta" aria-labelledby="pol-<?= $e($codigo) ?>">
            <h2 id="pol-<?= $e($codigo) ?>" class="text-base"><?= $e($nombre) ?></h2>
            <?php if (!isset($porTipo[$codigo])) { ?>
                <p class="mt-2 text-sm texto-suave">Sin versiones todavía.</p>
            <?php } else { ?>
                <ol class="mt-3 flex flex-col gap-2 text-sm">
                    <?php foreach ($porTipo[$codigo] as $politica) { ?>
                        <li class="flex flex-wrap items-center gap-2">
                            <strong>v<?= $e((string) $politica['version']) ?></strong>
                            · <?= $e((string) $politica['titulo']) ?>
                            · vigente desde <?= $e((string) $politica['vigente_desde']) ?>
                            <?php if (($politica['vigente_hasta'] ?? null) !== null) { ?>
                                hasta <?= $e((string) $politica['vigente_hasta']) ?>
                            <?php } ?>
                            <?php if (((int) $politica['activa']) === 1) { ?>
                                <span class="insignia insignia-exito">Vigente</span>
                            <?php } else { ?>
                                <span class="insignia insignia-aviso">Cerrada</span>
                            <?php } ?>
                        </li>
                    <?php } ?>
                </ol>
            <?php } ?>
        </section>
    <?php } ?>
</div>

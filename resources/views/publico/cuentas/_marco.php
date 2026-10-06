<?php
/**
 * El marco del portal del contratista: minimalista a propósito (§2 del
 * documento del módulo). Logo, título grande, contenido y nada más; sin
 * menús anidados ni distracciones. Tipografía 18px+, botones de 56px, y
 * el mismo lenguaje visual del ingreso (degradado de marca y tarjetas
 * blancas redondeadas).
 * Espera: $titulo, $contenido, $paso (?int 1..4), $conSesion (bool).
 */
use App\Core\Support\Recursos;

$conSesion = $conSesion ?? false;
$paso = $paso ?? null;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= $e($titulo) ?> — Cuentas de cobro · Hospital Rosario Pumarejo de López</title>
    <?php /* Con huella de versión: sin ella el navegador se queda con
             la hoja vieja y la pantalla mezcla marcado nuevo con
             estilos de ayer. */ ?>
    <link rel="stylesheet" href="<?= $e(Recursos::url('/css/cuentas.css')) ?>">
</head>
<body>
<a class="saltar" href="#contenido">Saltar al contenido</a>
<header class="cabecera">
    <?php /* El mismo logo compacto del ingreso al panel y del portal: una
             sola marca en toda la plataforma. */ ?>
    <img src="<?= $e(Recursos::url('/img/logo_compacto.jpg')) ?>"
         alt="E.S.E. Hospital Rosario Pumarejo de López" width="64" height="64">
    <?php if ($conSesion) { ?>
        <form method="post" action="/cuentas/salir">
            <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
            <button class="boton-salir" type="submit">Salir</button>
        </form>
    <?php } ?>
</header>
<main id="contenido" class="principal">
    <?php if ($paso !== null) { ?>
        <p class="progreso" aria-label="Progreso del formulario">Paso <?= (int) $paso ?> de 4</p>
        <div class="barra" role="progressbar" aria-valuenow="<?= (int) $paso ?>" aria-valuemin="1" aria-valuemax="4">
            <div class="barra-lleno paso-<?= (int) $paso ?>"></div>
        </div>
    <?php } ?>
    <h1 class="titulo"><?= $e($titulo) ?></h1>
    <?= $contenido ?>
</main>
<footer class="pie">
    <p>¿Necesita ayuda? Comuníquese con la oficina de Contratación del hospital.</p>
</footer>
</body>
</html>

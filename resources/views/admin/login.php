<?php
/**
 * Ingreso al panel. Página completa, sin navegación.
 * Espera: $tokenCsrf (string), $error (?string).
 */

use App\Core\Support\Recursos;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Ingreso al panel — E.S.E. Hospital Rosario Pumarejo de López</title>
    <link rel="stylesheet" href="<?= $e(Recursos::url('/build/app.css')) ?>">
</head>
<body class="fondo-superficie">
    <main class="min-h-svh flex items-center justify-center px-4 py-8">
        <div class="w-full max-w-sm">
            <h1 class="text-xl text-center">Panel administrativo</h1>
            <p class="mt-1 text-sm texto-suave text-center">E.S.E. Hospital Rosario Pumarejo de López</p>

            <?php if ($error !== null) { ?>
                <div class="alerta alerta-error mt-6" role="alert"><?= $e($error) ?></div>
            <?php } ?>

            <form method="post" action="/admin/entrar" class="tarjeta mt-6 flex flex-col gap-4">
                <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">

                <div>
                    <label class="etiqueta" for="correo">Correo institucional</label>
                    <input class="campo" type="email" id="correo" name="correo" required
                           autocomplete="username" autofocus>
                </div>

                <div>
                    <label class="etiqueta" for="password">Contraseña</label>
                    <input class="campo" type="password" id="password" name="password" required
                           autocomplete="current-password">
                </div>

                <button type="submit" class="boton boton-primario w-full">Ingresar</button>
            </form>

            <p class="mt-4 text-sm texto-suave text-center">
                ¿Olvidaste tu contraseña? Pídele al administrador del sitio que la restablezca.
            </p>
        </div>
    </main>
</body>
</html>

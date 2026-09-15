<?php
/**
 * Ingreso al panel. Página completa, sin navegación.
 * Espera: $tokenCsrf (string), $error (?string), $correo (string),
 *         $recordar (bool), $persistente (bool).
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
    <script src="/js/login.js" defer></script>
</head>
<body class="fondo-degradado-marca">
    <main class="min-h-svh flex items-center justify-center px-4 py-8">
        <div class="w-full max-w-sm">
            <img src="<?= $e(Recursos::url('/img/logo_compacto.jpg')) ?>"
                 alt="E.S.E. Hospital Rosario Pumarejo de López"
                 width="384" height="384"
                 class="mx-auto h-24 w-24 rounded-2xl object-contain bg-white p-1 shadow-lg">

            <h1 class="mt-5 text-xl text-center">Panel administrativo</h1>
            <p class="mt-1 text-sm texto-suave text-center">E.S.E. Hospital Rosario Pumarejo de López</p>

            <?php if ($error !== null) { ?>
                <div class="alerta alerta-error mt-6" role="alert"><?= $e($error) ?></div>
            <?php } ?>

            <form method="post" action="/admin/entrar" class="tarjeta mt-6 flex flex-col gap-4">
                <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">

                <div>
                    <label class="etiqueta" for="correo">Correo institucional</label>
                    <input class="campo" type="email" id="correo" name="correo" required
                           value="<?= $e($correo) ?>"
                           autocomplete="username" <?= $correo === '' ? 'autofocus' : '' ?>>
                </div>

                <div>
                    <label class="etiqueta" for="password">Contraseña</label>
                    <?php /* El botón del ojo nace `hidden`: sin JavaScript no
                             puede alternar nada, y un control muerto confunde
                             más de lo que ayuda. `login.js` lo destapa. */ ?>
                    <div class="campo-clave">
                        <input class="campo" type="password" id="password" name="password" required
                               autocomplete="current-password" <?= $correo === '' ? '' : 'autofocus' ?>>
                        <button type="button" class="campo-clave-ojo" hidden
                                data-ver-clave="password"
                                aria-controls="password"
                                aria-pressed="false"
                                aria-label="Mostrar la contraseña">
                            <svg data-icono="ojo-abierto" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="1.8"
                                 stroke-linecap="round" stroke-linejoin="round"
                                 class="h-5 w-5" aria-hidden="true" focusable="false">
                                <path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg data-icono="ojo-tachado" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="1.8"
                                 stroke-linecap="round" stroke-linejoin="round"
                                 class="h-5 w-5" aria-hidden="true" focusable="false" hidden>
                                <path d="M10.7 6.1A9.9 9.9 0 0 1 12 5.5c6.4 0 10 6.5 10 6.5a18 18 0 0 1-3.1 3.9"></path>
                                <path d="M6.3 7.9A17.7 17.7 0 0 0 2 12s3.6 6.5 10 6.5a9.8 9.8 0 0 0 4-.8"></path>
                                <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"></path>
                                <path d="m3 3 18 18"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <label class="flex items-start gap-2.5 text-sm" for="recordar">
                        <input type="checkbox" id="recordar" name="recordar" value="1"
                               class="mt-0.5 size-4 shrink-0 accent-primario-600"
                               <?= $recordar ? 'checked' : '' ?>>
                        <span>Recordar mi cuenta en este equipo</span>
                    </label>

                    <label class="flex items-start gap-2.5 text-sm" for="persistente">
                        <input type="checkbox" id="persistente" name="persistente" value="1"
                               class="mt-0.5 size-4 shrink-0 accent-primario-600"
                               <?= $persistente ? 'checked' : '' ?>>
                        <span>
                            Mantener la sesión iniciada
                            <span class="block texto-suave">No lo marques en un computador compartido.</span>
                        </span>
                    </label>
                </div>

                <button type="submit" class="boton boton-primario w-full">Ingresar</button>
            </form>

            <p class="mt-4 text-sm text-center panel-vidrio rounded-xl px-3 py-2">
                ¿Olvidaste tu contraseña? Pídele al administrador del sitio que la restablezca.
            </p>
        </div>
    </main>
</body>
</html>

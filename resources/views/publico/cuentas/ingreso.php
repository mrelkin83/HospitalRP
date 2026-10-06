<?php
/**
 * P0 — Ingreso del contratista al portal de cuentas de cobro.
 *
 * Página completa, sin navegación, con el mismo diseño del ingreso al
 * panel (logo, degradado de marca y tarjeta): es la misma institución y la
 * misma puerta, y reconocerla da confianza. Lo que NO se copia del panel
 * es el tamaño: aquí los controles van más grandes porque buena parte de
 * quienes entran son adultos mayores (§2 del documento del módulo).
 *
 * Espera: $error, $mensaje, $tokenCsrf, $modo, $identificador, $rotulo,
 * $ayuda, $ejemplo, $recordar, $persistente.
 */

use App\Core\Support\Recursos;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Ingreso al portal de cuentas de cobro — E.S.E. Hospital Rosario Pumarejo de López</title>
    <link rel="stylesheet" href="<?= $e(Recursos::url('/build/app.css')) ?>">
    <script src="/js/login.js" defer></script>
    <script src="/js/cuentas-acceso.js" defer></script>
</head>
<body class="fondo-degradado-marca">
    <main class="min-h-svh flex items-center justify-center px-4 py-8">
        <div class="w-full max-w-md">
            <img src="<?= $e(Recursos::url('/img/logo_compacto.jpg')) ?>"
                 alt="E.S.E. Hospital Rosario Pumarejo de López"
                 width="384" height="384"
                 class="mx-auto h-24 w-24 rounded-2xl object-contain bg-white p-1 shadow-lg">

            <h1 class="mt-5 text-xl text-center">Portal de cuentas de cobro</h1>
            <p class="mt-1 text-sm texto-suave text-center">E.S.E. Hospital Rosario Pumarejo de López</p>

            <?php if ($error !== null) { ?>
                <div class="alerta alerta-error mt-6" role="alert"><?= $e($error) ?></div>
            <?php } ?>
            <?php if ($mensaje !== null) { ?>
                <div class="alerta alerta-exito mt-6" role="status"><?= $e($mensaje) ?></div>
            <?php } ?>

            <form method="post" action="/cuentas/ingreso" class="tarjeta mt-6 flex flex-col gap-4">
                <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">

                <div>
                    <label class="etiqueta text-base" for="modo">¿Con qué dato quiere entrar?</label>
                    <?php /* Los textos viajan en `data-*` y no dentro del
                             JavaScript: son de la interfaz, y quien los
                             retoque debe poder hacerlo aquí, junto a lo que
                             se ve. */ ?>
                    <select class="campo text-lg" id="modo" name="modo"
                            data-campo-identificador="identificador"
                            data-etiqueta-cedula="Ingrese su número de cédula"
                            data-etiqueta-contrato="Ingrese su número de contrato"
                            data-ayuda-cedula="Solo los números, sin puntos ni comas. Por ejemplo: 1100000002."
                            data-ayuda-contrato="Está en la primera página de su copia del contrato. Por ejemplo: 1407-2026."
                            data-ejemplo-cedula="1100000002"
                            data-ejemplo-contrato="1407-2026">
                        <option value="cedula" <?= $modo === 'contrato' ? '' : 'selected' ?>>Número de cédula</option>
                        <option value="contrato" <?= $modo === 'contrato' ? 'selected' : '' ?>>Número de contrato</option>
                    </select>
                </div>

                <div>
                    <?php /* El rótulo y la ayuda salen del servidor ya
                             ajustados al modo, para que sin JavaScript la
                             pantalla siga siendo coherente. */ ?>
                    <label class="etiqueta text-base" for="identificador"><?= $e($rotulo) ?></label>
                    <input class="campo text-lg" type="text" id="identificador" name="identificador" required
                           value="<?= $e($identificador) ?>" autocomplete="username"
                           placeholder="<?= $e($ejemplo) ?>" inputmode="<?= $modo === 'contrato' ? 'text' : 'numeric' ?>"
                           aria-describedby="ayuda-identificador"
                           <?= $identificador === '' ? 'autofocus' : '' ?>>
                    <p class="mt-1 text-sm texto-suave" id="ayuda-identificador"><?= $e($ayuda) ?></p>
                </div>

                <div>
                    <label class="etiqueta text-base" for="contrasena">Contraseña</label>
                    <?php /* El ojo nace `hidden`: sin JavaScript no puede
                             alternar nada, y un control muerto confunde más de
                             lo que ayuda. `login.js` lo destapa. */ ?>
                    <div class="campo-clave">
                        <input class="campo text-lg" type="password" id="contrasena" name="contrasena" required
                               autocomplete="current-password"
                               <?= $identificador === '' ? '' : 'autofocus' ?>>
                        <button type="button" class="campo-clave-ojo" hidden
                                data-ver-clave="contrasena"
                                aria-controls="contrasena"
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
                    <label class="flex items-start gap-2.5" for="recordar">
                        <input type="checkbox" id="recordar" name="recordar" value="1"
                               class="mt-1 size-5 shrink-0 accent-primario-600"
                               <?= $recordar ? 'checked' : '' ?>>
                        <span>Recordar mi cuenta en este equipo</span>
                    </label>

                    <label class="flex items-start gap-2.5" for="persistente">
                        <input type="checkbox" id="persistente" name="persistente" value="1"
                               class="mt-1 size-5 shrink-0 accent-primario-600"
                               <?= $persistente ? 'checked' : '' ?>>
                        <span>
                            Mantener la sesión iniciada
                            <span class="block text-sm texto-suave">No la marque en un computador prestado o de la familia.</span>
                        </span>
                    </label>
                </div>

                <button type="submit" class="boton boton-primario w-full text-lg">Entrar</button>
            </form>

            <div class="mt-4 panel-vidrio rounded-xl px-4 py-3 text-center">
                <p>
                    <a class="enlace" href="/cuentas/recuperar-contrasena">Olvidé mi contraseña</a>
                </p>
                <p class="mt-2 text-sm">
                    ¿Primera vez aquí?
                    <a class="enlace" href="/cuentas/crear-contrasena">Cree su contraseña</a>
                    con la fecha de su contrato.
                </p>
            </div>

            <p class="mt-4 text-center text-sm texto-suave">
                Este portal es solo para contratistas de prestación de servicios del hospital.
            </p>
        </div>
    </main>
</body>
</html>

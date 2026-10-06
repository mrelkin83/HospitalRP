<?php
/**
 * P0b — Crear o recuperar la contraseña del portal.
 *
 * Las dos pantallas son el MISMO formulario —identificarse, confirmar la
 * fecha del contrato y escribir la clave nueva— y por eso comparten
 * plantilla: dos copias del mismo formulario acaban divergiendo, y la que
 * se olvida siempre es la que menos se usa.
 *
 * Mismo diseño que el ingreso al panel (logo, degradado y tarjeta), con
 * los controles más grandes que allá.
 *
 * Espera: $accion, $titulo, $intro, $boton, $error, $mensaje, $tokenCsrf,
 * $modo, $identificador, $rotulo, $ayuda, $ejemplo.
 */

use App\Core\Support\Recursos;

/** El ojo para mostrar la clave; dos campos lo necesitan igual. */
$ojo = static function (string $id) use ($e): void { ?>
    <button type="button" class="campo-clave-ojo" hidden
            data-ver-clave="<?= $e($id) ?>"
            aria-controls="<?= $e($id) ?>"
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
<?php };
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= $e($titulo) ?> — Cuentas de cobro · E.S.E. Hospital Rosario Pumarejo de López</title>
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

            <h1 class="mt-5 text-xl text-center"><?= $e($titulo) ?></h1>
            <p class="mt-1 text-sm texto-suave text-center">E.S.E. Hospital Rosario Pumarejo de López</p>

            <?php if ($error !== null) { ?>
                <div class="alerta alerta-error mt-6" role="alert"><?= $e($error) ?></div>
            <?php } ?>
            <?php if ($mensaje !== null) { ?>
                <div class="alerta alerta-exito mt-6" role="status"><?= $e($mensaje) ?></div>
            <?php } ?>

            <form method="post" action="<?= $e($accion) ?>" class="tarjeta mt-6 flex flex-col gap-4">
                <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">

                <p><?= $e($intro) ?></p>

                <div>
                    <label class="etiqueta text-base" for="modo">¿Con qué dato se identifica?</label>
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
                    <label class="etiqueta text-base" for="identificador"><?= $e($rotulo) ?></label>
                    <input class="campo text-lg" type="text" id="identificador" name="identificador" required
                           value="<?= $e($identificador) ?>" autocomplete="username" autofocus
                           placeholder="<?= $e($ejemplo) ?>" inputmode="<?= $modo === 'contrato' ? 'text' : 'numeric' ?>"
                           aria-describedby="ayuda-identificador">
                    <p class="mt-1 text-sm texto-suave" id="ayuda-identificador"><?= $e($ayuda) ?></p>
                </div>

                <div>
                    <label class="etiqueta text-base" for="fecha-suscripcion">Fecha de suscripción del contrato</label>
                    <input class="campo text-lg" type="date" id="fecha-suscripcion" name="fecha_suscripcion" required
                           aria-describedby="ayuda-fecha">
                    <p class="mt-1 text-sm texto-suave" id="ayuda-fecha">
                        Aparece junto a las firmas, en su copia del contrato. Con ella
                        confirmamos que es usted.
                    </p>
                </div>

                <div>
                    <label class="etiqueta text-base" for="contrasena">Contraseña nueva</label>
                    <div class="campo-clave">
                        <input class="campo text-lg" type="password" id="contrasena" name="contrasena" required
                               minlength="8" autocomplete="new-password" aria-describedby="ayuda-contrasena">
                        <?php $ojo('contrasena'); ?>
                    </div>
                    <p class="mt-1 text-sm texto-suave" id="ayuda-contrasena">
                        Mínimo 8 caracteres. Puede ser una frase fácil de recordar.
                    </p>
                </div>

                <div>
                    <label class="etiqueta text-base" for="contrasena-confirmacion">Repita la contraseña</label>
                    <div class="campo-clave">
                        <input class="campo text-lg" type="password" id="contrasena-confirmacion"
                               name="contrasena_confirmacion" required minlength="8" autocomplete="new-password">
                        <?php $ojo('contrasena-confirmacion'); ?>
                    </div>
                </div>

                <button type="submit" class="boton boton-primario w-full text-lg"><?= $e($boton) ?></button>
            </form>

            <p class="mt-4 panel-vidrio rounded-xl px-4 py-3 text-center">
                <a class="enlace" href="/cuentas/ingreso">Volver al ingreso</a>
            </p>
        </div>
    </main>
</body>
</html>

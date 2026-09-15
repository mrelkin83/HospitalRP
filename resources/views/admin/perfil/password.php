<?php
/**
 * Cambio de contraseña, voluntario u obligatorio en la primera entrada.
 * Espera: $obligatorio (bool), $errores (list<string>), $tokenCsrf, $largoMinimo.
 */
?>
<h1 class="text-2xl">Cambiar contraseña</h1>

<?php if ($obligatorio) { ?>
    <div class="alerta alerta-aviso mt-4" role="alert">
        <p class="font-semibold">Tu contraseña es provisional.</p>
        <p class="mt-1 text-sm">
            La conoce quien creó o restableció tu cuenta. Elige una nueva para
            continuar: hasta entonces el panel no te deja entrar a otras
            secciones.
        </p>
    </div>
<?php } ?>

<?php if ($errores !== []) { ?>
    <div class="alerta alerta-error mt-4" role="alert">
        <p class="font-semibold">No se pudo cambiar:</p>
        <ul class="mt-2 list-disc ps-5 text-sm">
            <?php foreach ($errores as $problema) { ?>
                <li><?= $e($problema) ?></li>
            <?php } ?>
        </ul>
    </div>
<?php } ?>

<form class="tarjeta mt-6" method="post" action="/admin/perfil/password">
    <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">

    <div>
        <label class="etiqueta" for="password-actual">Contraseña actual</label>
        <input class="campo" id="password-actual" type="password" name="password_actual"
               required autocomplete="current-password">
    </div>

    <div class="mt-4">
        <label class="etiqueta" for="password-nueva">Contraseña nueva</label>
        <input class="campo" id="password-nueva" type="password" name="password_nueva"
               required minlength="<?= (int) $largoMinimo ?>" autocomplete="new-password">
        <p class="mt-1 text-sm texto-suave">
            Al menos <?= (int) $largoMinimo ?> caracteres, con letras y números. No
            puede contener tu nombre ni tu usuario de correo.
        </p>
    </div>

    <div class="mt-4">
        <label class="etiqueta" for="password-confirmacion">Repite la contraseña nueva</label>
        <input class="campo" id="password-confirmacion" type="password" name="password_confirmacion"
               required minlength="<?= (int) $largoMinimo ?>" autocomplete="new-password">
    </div>

    <p class="mt-6"><button type="submit" class="boton boton-primario">Cambiar contraseña</button></p>
</form>

<?php
/**
 * Cuenta propia del funcionario.
 * Espera: $usuario (fila), $mensaje (?string), $error (?string), $tokenCsrf,
 * $largoMinimo (int).
 */

use App\Core\Support\Fechas;
?>
<h1 class="text-2xl">Mi cuenta</h1>

<?php if ($mensaje !== null) { ?>
    <div class="alerta alerta-exito mt-4" role="status"><?= $e($mensaje) ?></div>
<?php } ?>
<?php if ($error !== null) { ?>
    <div class="alerta alerta-error mt-4" role="alert"><?= $e($error) ?></div>
<?php } ?>

<section class="tarjeta mt-6">
    <h2 class="text-base texto-titular">Datos</h2>
    <form class="mt-4" method="post" action="/admin/perfil">
        <input type="hidden" name="_token" value="<?= $e($tokenCsrf) ?>">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="etiqueta" for="perfil-nombre">Nombre completo</label>
                <input class="campo" id="perfil-nombre" type="text" name="nombre" required maxlength="160" value="<?= $e((string) $usuario['nombre']) ?>">
            </div>
            <div>
                <label class="etiqueta" for="perfil-cargo">Cargo</label>
                <input class="campo" id="perfil-cargo" type="text" name="cargo" maxlength="120" value="<?= $e((string) ($usuario['cargo'] ?? '')) ?>">
            </div>
        </div>
        <p class="mt-4"><button type="submit" class="boton boton-primario">Guardar datos</button></p>
    </form>

    <dl class="mt-6 grid gap-2 text-sm sm:grid-cols-2">
        <div>
            <dt class="font-semibold texto-titular">Usuario de ingreso</dt>
            <dd class="texto-suave"><?= $e((string) $usuario['correo']) ?></dd>
        </div>
        <div>
            <dt class="font-semibold texto-titular">Rol</dt>
            <dd class="texto-suave"><?= $e((string) $usuario['rol_nombre']) ?></dd>
        </div>
        <div>
            <dt class="font-semibold texto-titular">Último acceso</dt>
            <dd class="texto-suave"><?= $e(Fechas::conHora($usuario['ultimo_acceso'] === null ? null : (string) $usuario['ultimo_acceso']) ?: 'este es el primero') ?></dd>
        </div>
    </dl>
    <p class="mt-4 text-sm texto-suave">
        El correo y el rol no se editan desde aquí: el correo es tu credencial de
        ingreso y el rol son tus permisos. Los cambia un administrador.
    </p>
</section>

<section class="tarjeta mt-6">
    <h2 class="text-base texto-titular">Contraseña</h2>
    <p class="mt-2 text-sm texto-suave">
        Mínimo <?= (int) $largoMinimo ?> caracteres, con letras y números. Nadie
        —tampoco el administrador— puede ver tu contraseña: solo restablecerla.
    </p>
    <p class="mt-4"><a class="boton boton-secundario" href="/admin/perfil/password">Cambiar mi contraseña</a></p>
</section>

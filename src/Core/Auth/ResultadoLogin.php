<?php

declare(strict_types=1);

namespace App\Core\Auth;

/**
 * Resultado de un intento de inicio de sesión.
 *
 * `CredencialesInvalidas` cubre también correo inexistente y cuenta inactiva:
 * distinguirlos de cara afuera le confirma a un atacante qué correos existen.
 * `CuentaBloqueada` sí se distingue: el funcionario legítimo necesita saber
 * que debe esperar, no que se equivocó de contraseña.
 */
enum ResultadoLogin
{
    case Correcto;
    case CredencialesInvalidas;
    case CuentaBloqueada;
}

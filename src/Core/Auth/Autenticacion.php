<?php

declare(strict_types=1);

namespace App\Core\Auth;

/**
 * Lo que devuelve un intento de login: el veredicto y, solo si fue correcto,
 * el usuario autenticado.
 */
final readonly class Autenticacion
{
    private function __construct(
        public ResultadoLogin $resultado,
        public ?Usuario $usuario,
    ) {
    }

    public static function correcta(Usuario $usuario): self
    {
        return new self(ResultadoLogin::Correcto, $usuario);
    }

    public static function invalida(): self
    {
        return new self(ResultadoLogin::CredencialesInvalidas, null);
    }

    public static function bloqueada(): self
    {
        return new self(ResultadoLogin::CuentaBloqueada, null);
    }
}

<?php

declare(strict_types=1);

namespace App\Core\Auth;

use DateTimeImmutable;

/**
 * Proyección del usuario para autenticación y sesión.
 *
 * No es el CRUD de usuarios del panel (eso vive en Modules/Sistema, Fase 0
 * paso 8 en adelante): aquí solo están los campos que el login necesita.
 */
final readonly class Usuario
{
    public function __construct(
        public int $id,
        public string $nombre,
        public string $correo,
        public string $hashPassword,
        public int $rolId,
        public bool $activo,
        public int $intentosFallidos,
        public ?DateTimeImmutable $bloqueadoHasta,
        public bool $debeCambiarPassword,
    ) {
    }

    /**
     * @param array<string, mixed> $fila Fila de la tabla `usuarios`
     */
    public static function desdeFila(array $fila): self
    {
        return new self(
            id: (int) $fila['id'],
            nombre: (string) $fila['nombre'],
            correo: (string) $fila['correo'],
            hashPassword: (string) $fila['hash_password'],
            rolId: (int) $fila['rol_id'],
            activo: (bool) $fila['activo'],
            intentosFallidos: (int) $fila['intentos_fallidos'],
            bloqueadoHasta: isset($fila['bloqueado_hasta'])
                ? new DateTimeImmutable((string) $fila['bloqueado_hasta'])
                : null,
            debeCambiarPassword: (bool) $fila['debe_cambiar_pass'],
        );
    }

    public function bloqueadoEn(DateTimeImmutable $momento): bool
    {
        return $this->bloqueadoHasta !== null && $this->bloqueadoHasta > $momento;
    }
}

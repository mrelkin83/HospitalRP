<?php

declare(strict_types=1);

namespace App\Core\Audit;

/**
 * Un hecho auditable, listo para escribirse.
 *
 * `usuarioNombre` viaja desnormalizado a propósito: la fila de auditoría debe
 * seguir diciendo quién hizo qué aunque el usuario se elimine después. Por lo
 * mismo la tabla no tiene FK a `usuarios`.
 */
final readonly class RegistroAuditoria
{
    /**
     * @param ?array<string, mixed> $antes   Instantánea previa al cambio
     * @param ?array<string, mixed> $despues Instantánea posterior al cambio
     */
    public function __construct(
        public AccionAuditoria $accion,
        public string $entidad,
        public ?int $entidadId = null,
        public ?string $descripcion = null,
        public ?array $antes = null,
        public ?array $despues = null,
        public ?int $usuarioId = null,
        public ?string $usuarioNombre = null,
        public ?string $ip = null,
        public ?string $userAgent = null,
    ) {
    }
}

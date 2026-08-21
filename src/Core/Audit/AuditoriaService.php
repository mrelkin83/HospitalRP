<?php

declare(strict_types=1);

namespace App\Core\Audit;

use Doctrine\DBAL\Connection;

/**
 * Servicio central de auditoría (CLAUDE.md §2, regla de oro #3).
 *
 * Solo sabe escribir: la tabla es append-only y los triggers de la base
 * rechazan cualquier UPDATE o DELETE. La lectura llegará con la bandeja de
 * auditoría del panel, como repositorio aparte.
 */
final readonly class AuditoriaService
{
    public function __construct(private Connection $conexion)
    {
    }

    public function registrar(RegistroAuditoria $registro): void
    {
        $this->conexion->insert('auditoria', [
            'usuario_id' => $registro->usuarioId,
            'usuario_nombre' => $registro->usuarioNombre,
            'accion' => $registro->accion->value,
            'entidad' => $registro->entidad,
            'entidad_id' => $registro->entidadId,
            'descripcion' => $registro->descripcion,
            'antes' => $this->comoJson($registro->antes),
            'despues' => $this->comoJson($registro->despues),
            'ip' => $this->comoBinario($registro->ip),
            'user_agent' => $registro->userAgent,
        ]);
    }

    /**
     * @param ?array<string, mixed> $instantanea
     */
    private function comoJson(?array $instantanea): ?string
    {
        if ($instantanea === null) {
            return null;
        }

        return json_encode($instantanea, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * VARBINARY(16): cabe IPv4 e IPv6. Una IP que no parsea se guarda como
     * NULL en vez de tumbar la operación auditada: el hecho importa más que
     * el dato accesorio.
     */
    private function comoBinario(?string $ip): ?string
    {
        if ($ip === null) {
            return null;
        }

        $binario = inet_pton($ip);

        return $binario === false ? null : $binario;
    }
}

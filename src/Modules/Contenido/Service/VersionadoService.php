<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Service;

use Doctrine\DBAL\Connection;

/**
 * Versionado genérico sobre `contenido_versiones` (CLAUDE.md §6):
 * cada guardado deja un snapshot JSON completo de la entidad, restaurable.
 *
 * Este servicio guarda y lee snapshots; aplicar una restauración —y
 * auditarla— es del servicio de cada entidad, que sabe qué campos existen.
 */
final readonly class VersionadoService
{
    public function __construct(private Connection $conexion)
    {
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function registrar(
        string $entidad,
        int $entidadId,
        array $snapshot,
        ?string $nota,
        ?int $usuarioId,
    ): int {
        // Transacción: dos guardados simultáneos de la misma pieza no pueden
        // terminar con el mismo número de versión (hay unique en la tabla).
        return $this->conexion->transactional(
            function () use ($entidad, $entidadId, $snapshot, $nota, $usuarioId): int {
                $version = 1 + (int) $this->conexion->fetchOne(
                    'SELECT COALESCE(MAX(version), 0) FROM contenido_versiones
                      WHERE entidad = ? AND entidad_id = ? FOR UPDATE',
                    [$entidad, $entidadId],
                );

                $this->conexion->insert('contenido_versiones', [
                    'entidad' => $entidad,
                    'entidad_id' => $entidadId,
                    'version' => $version,
                    'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'nota' => $nota,
                    'creado_por' => $usuarioId,
                ]);

                return $version;
            },
        );
    }

    /**
     * @return ?array<string, mixed>
     */
    public function snapshot(string $entidad, int $entidadId, int $version): ?array
    {
        $json = $this->conexion->fetchOne(
            'SELECT snapshot FROM contenido_versiones
              WHERE entidad = ? AND entidad_id = ? AND version = ?',
            [$entidad, $entidadId, $version],
        );

        if (!is_string($json)) {
            return null;
        }

        /** @var array<string, mixed> */
        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Historial para el panel, de la más reciente a la más vieja.
     *
     * @return list<array{version: int, nota: ?string, creado_por: ?int, creado_en: string}>
     */
    public function historial(string $entidad, int $entidadId): array
    {
        /** @var list<array{version: int, nota: ?string, creado_por: ?int, creado_en: string}> $filas */
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT version, nota, creado_por, creado_en FROM contenido_versiones
              WHERE entidad = ? AND entidad_id = ?
              ORDER BY version DESC',
            [$entidad, $entidadId],
        );

        return array_map(static fn (array $f): array => [
            'version' => (int) $f['version'],
            'nota' => $f['nota'] !== null ? (string) $f['nota'] : null,
            'creado_por' => $f['creado_por'] !== null ? (int) $f['creado_por'] : null,
            'creado_en' => (string) $f['creado_en'],
        ], $filas);
    }
}

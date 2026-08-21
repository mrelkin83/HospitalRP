<?php

declare(strict_types=1);

namespace App\Modules\Sistema\Repository;

use Doctrine\DBAL\Connection;

/**
 * Dependencias (áreas administrativas). Las usan los documentos y, desde la
 * Fase 5, los trámites.
 */
final readonly class DependenciaRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarParaPanel(): array
    {
        return $this->conexion->fetchAllAssociative('SELECT * FROM dependencias ORDER BY orden, nombre');
    }

    /**
     * @return list<array{id: int, nombre: string}>
     */
    public function activas(): array
    {
        /** @var list<array{id: int|string, nombre: string}> $filas */
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT id, nombre FROM dependencias WHERE activa = 1 ORDER BY orden, nombre',
        );

        return array_map(
            static fn (array $f): array => ['id' => (int) $f['id'], 'nombre' => (string) $f['nombre']],
            $filas,
        );
    }

    /**
     * @return ?array<string, mixed>
     */
    public function porId(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative('SELECT * FROM dependencias WHERE id = ?', [$id]);

        return $fila === false ? null : $fila;
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function crear(array $campos): int
    {
        $this->conexion->insert('dependencias', $campos);

        return (int) $this->conexion->lastInsertId();
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function actualizar(int $id, array $campos): void
    {
        $this->conexion->update('dependencias', $campos, ['id' => $id]);
    }
}

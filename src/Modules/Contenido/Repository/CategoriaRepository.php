<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Repository;

use Doctrine\DBAL\Connection;

final readonly class CategoriaRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarParaPanel(): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT * FROM categorias ORDER BY tipo, orden, nombre',
        );
    }

    /**
     * @return ?array<string, mixed>
     */
    public function porId(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative('SELECT * FROM categorias WHERE id = ?', [$id]);

        return $fila === false ? null : $fila;
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function crear(array $campos): int
    {
        $this->conexion->insert('categorias', $campos);

        return (int) $this->conexion->lastInsertId();
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function actualizar(int $id, array $campos): void
    {
        $this->conexion->update('categorias', $campos, ['id' => $id]);
    }

    /**
     * @return list<array{id: int, nombre: string}>
     */
    public function activasPorTipo(string $tipo): array
    {
        /** @var list<array{id: int|string, nombre: string}> $filas */
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT id, nombre FROM categorias
              WHERE tipo = ? AND activa = 1
              ORDER BY orden, nombre',
            [$tipo],
        );

        return array_map(
            static fn (array $f): array => ['id' => (int) $f['id'], 'nombre' => $f['nombre']],
            $filas,
        );
    }
}

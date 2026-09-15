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

    /**
     * Resuelve una categoría por su slug.
     *
     * El buscador documental filtra por id, y un id no sirve para un ítem de
     * menú: cambia entre instalaciones según el orden de sembrado, así que
     * `/documentos?categoria=7` se rompería al resembrar. El slug es estable
     * y además se lee.
     *
     * @return ?array{id: int, nombre: string, slug: string}
     */
    public function porSlug(string $tipo, string $slug): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT id, nombre, slug FROM categorias WHERE tipo = ? AND slug = ? AND activa = 1',
            [$tipo, $slug],
        );

        return $fila === false ? null : [
            'id' => (int) $fila['id'],
            'nombre' => (string) $fila['nombre'],
            'slug' => (string) $fila['slug'],
        ];
    }
}

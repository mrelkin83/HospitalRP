<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Repository;

use Doctrine\DBAL\Connection;

final readonly class FaqRepository
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
            'SELECT f.*, c.nombre AS categoria_nombre
               FROM faq f
               LEFT JOIN categorias c ON c.id = f.categoria_id
              ORDER BY f.orden, f.id',
        );
    }

    /**
     * @return ?array<string, mixed>
     */
    public function porId(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative('SELECT * FROM faq WHERE id = ?', [$id]);

        return $fila === false ? null : $fila;
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function crear(array $campos): int
    {
        $this->conexion->insert('faq', $campos);

        return (int) $this->conexion->lastInsertId();
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function actualizar(int $id, array $campos): void
    {
        $this->conexion->update('faq', $campos, ['id' => $id]);
    }

    public function eliminar(int $id): void
    {
        // La tabla no tiene borrado lógico (esquema §2): una pregunta
        // frecuente no es un acto institucional, es ayuda editable.
        $this->conexion->delete('faq', ['id' => $id]);
    }
}

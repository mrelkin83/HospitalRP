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

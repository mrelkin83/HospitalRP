<?php

declare(strict_types=1);

namespace App\Modules\Cumplimiento\Repository;

use Doctrine\DBAL\Connection;

final readonly class PoliticaRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    /**
     * @return ?array<string, mixed>
     */
    public function activaPorTipo(string $tipo): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM politicas WHERE tipo = ? AND activa = 1 ORDER BY id DESC LIMIT 1',
            [$tipo],
        );

        return $fila === false ? null : $fila;
    }
}

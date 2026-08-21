<?php

declare(strict_types=1);

namespace App\Modules\Tramites\Service;

use Doctrine\DBAL\Connection;

/**
 * Radicados `PREFIJO-AAAA-secuencia`, únicos bajo concurrencia.
 *
 * El truco es atómico en MySQL: `INSERT … ON DUPLICATE KEY UPDATE
 * ultimo = LAST_INSERT_ID(ultimo + 1)` toma el bloqueo de fila e incrementa
 * en una sola sentencia; dos radicaciones simultáneas jamás reciben el mismo
 * número. Además `tramites.radicado` es UNIQUE: si algo fallara, la base
 * rechaza el duplicado en vez de aceptarlo en silencio.
 */
final readonly class GeneradorRadicado
{
    public function __construct(private Connection $conexion)
    {
    }

    public function siguiente(string $prefijo, ?int $anio = null): string
    {
        $anio ??= (int) date('Y');

        $this->conexion->executeStatement(
            'INSERT INTO radicado_secuencias (anio, prefijo, ultimo)
             VALUES (?, ?, LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE ultimo = LAST_INSERT_ID(ultimo + 1)',
            [$anio, $prefijo],
        );

        $secuencia = (int) $this->conexion->lastInsertId();

        return sprintf('%s-%d-%06d', $prefijo, $anio, $secuencia);
    }
}

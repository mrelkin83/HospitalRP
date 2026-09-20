<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Repository;

use Doctrine\DBAL\Connection;

/**
 * Los trabajos de extracción de minutas (`cc_extracciones`): cada PDF
 * subido, su estado, el JSON extraído y las alertas de inconsistencia.
 */
final readonly class ExtraccionMinutaRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    public function crear(string $minutaArchivo, int $usuarioId): int
    {
        $this->conexion->insert('cc_extracciones', [
            'minuta_archivo' => $minutaArchivo,
            'creado_por' => $usuarioId,
        ]);

        return (int) $this->conexion->lastInsertId();
    }

    /** @return ?array<string, mixed> */
    public function porId(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative('SELECT * FROM cc_extracciones WHERE id = ?', [$id]);

        return $fila === false ? null : $fila;
    }

    /** @return list<array<string, mixed>> */
    public function recientes(int $limite = 30): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT e.*, u.nombre AS subida_por, k.numero AS numero_contrato
               FROM cc_extracciones e
               LEFT JOIN usuarios u ON u.id = e.creado_por
               LEFT JOIN cc_contratos k ON k.id = e.contrato_id
              ORDER BY e.id DESC
              LIMIT ' . max(1, min(200, $limite)),
        );
    }

    /** @param array<string, mixed> $campos */
    public function actualizar(int $id, array $campos): void
    {
        if ($campos !== []) {
            $this->conexion->update('cc_extracciones', $campos, ['id' => $id]);
        }
    }

    /**
     * El resultado extraído, ya decodificado (o vacío).
     *
     * @param array<string, mixed> $extraccion
     *
     * @return array<string, mixed>
     */
    public static function resultado(array $extraccion): array
    {
        if (!is_string($extraccion['resultado_json'] ?? null)) {
            return [];
        }

        $datos = json_decode((string) $extraccion['resultado_json'], true);

        return is_array($datos) ? $datos : [];
    }

    /**
     * @param array<string, mixed> $extraccion
     *
     * @return list<string>
     */
    public static function alertas(array $extraccion): array
    {
        if (!is_string($extraccion['alertas_json'] ?? null)) {
            return [];
        }

        $datos = json_decode((string) $extraccion['alertas_json'], true);

        return is_array($datos) ? array_values(array_map(strval(...), $datos)) : [];
    }
}

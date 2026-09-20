<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Repository;

use Doctrine\DBAL\Connection;

/**
 * Contratistas de prestación de servicios: una fila por cédula, con sus
 * datos de contacto, banco y firma. Los contratos van aparte.
 *
 * Aquí no hay DELETE: un contratista que se va se desactiva, porque sus
 * cuentas aprobadas siguen siendo documentos con valor legal.
 */
final readonly class ContratistaRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    /** @return list<array<string, mixed>> */
    public function listar(): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT c.*, (SELECT COUNT(*) FROM cc_contratos k WHERE k.contratista_id = c.id) AS contratos
               FROM cc_contratistas c
              ORDER BY c.activo DESC, c.apellidos, c.nombres',
        );
    }

    /** @return ?array<string, mixed> */
    public function porId(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative('SELECT * FROM cc_contratistas WHERE id = ?', [$id]);

        return $fila === false ? null : $fila;
    }

    /** @return ?array<string, mixed> */
    public function porDocumento(string $numeroDocumento): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM cc_contratistas WHERE numero_documento = ?',
            [$numeroDocumento],
        );

        return $fila === false ? null : $fila;
    }

    /** @param array<string, mixed> $campos */
    public function crear(array $campos): int
    {
        $this->conexion->insert('cc_contratistas', $campos);

        return (int) $this->conexion->lastInsertId();
    }

    /** @param array<string, mixed> $campos */
    public function actualizar(int $id, array $campos): void
    {
        if ($campos !== []) {
            $this->conexion->update('cc_contratistas', $campos, ['id' => $id]);
        }
    }

    public function cambiarActivo(int $id, bool $activo): void
    {
        $this->conexion->update('cc_contratistas', ['activo' => $activo ? 1 : 0], ['id' => $id]);
    }
}

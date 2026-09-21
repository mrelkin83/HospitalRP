<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Repository;

use Doctrine\DBAL\Connection;

/**
 * Los revisores de cuentas de cobro: el equipo (hoy, cuatro personas) que
 * verifica cada cuenta ENVIADA y la firma antes de pasarla al supervisor.
 * Comparten una sola bandeja: cualquiera puede tomar cualquier cuenta.
 */
final readonly class RevisorRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    /** @return list<array<string, mixed>> */
    public function listar(): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT r.*, u.correo AS usuario_correo,
                    (SELECT COUNT(*) FROM cc_cuentas c WHERE c.revisada_por = r.id) AS revisadas
               FROM cc_revisores r
               JOIN usuarios u ON u.id = r.usuario_id
              ORDER BY r.activo DESC, r.nombre_completo',
        );
    }

    /** @return ?array<string, mixed> */
    public function porId(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative('SELECT * FROM cc_revisores WHERE id = ?', [$id]);

        return $fila === false ? null : $fila;
    }

    /** @return ?array<string, mixed> */
    public function porUsuarioId(int $usuarioId): ?array
    {
        $fila = $this->conexion->fetchAssociative('SELECT * FROM cc_revisores WHERE usuario_id = ?', [$usuarioId]);

        return $fila === false ? null : $fila;
    }

    /** @return list<string> Correos de los revisores activos, para avisarles. */
    public function correosActivos(): array
    {
        return array_map(strval(...), $this->conexion->fetchFirstColumn(
            'SELECT u.correo FROM cc_revisores r JOIN usuarios u ON u.id = r.usuario_id
              WHERE r.activo = 1 AND u.correo IS NOT NULL',
        ));
    }

    /**
     * Usuarios activos del panel aún sin vincular como revisores.
     *
     * @return list<array<string, mixed>>
     */
    public function usuariosElegibles(): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT u.id, u.nombre, u.correo FROM usuarios u
              WHERE u.activo = 1 AND u.eliminado_en IS NULL
                AND NOT EXISTS (SELECT 1 FROM cc_revisores r WHERE r.usuario_id = u.id)
              ORDER BY u.nombre',
        );
    }

    /** @param array<string, mixed> $campos */
    public function crear(array $campos): int
    {
        $this->conexion->insert('cc_revisores', $campos);

        return (int) $this->conexion->lastInsertId();
    }

    /** @param array<string, mixed> $campos */
    public function actualizar(int $id, array $campos): void
    {
        if ($campos !== []) {
            $this->conexion->update('cc_revisores', $campos, ['id' => $id]);
        }
    }
}

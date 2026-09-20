<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Repository;

use Doctrine\DBAL\Connection;

/**
 * Supervisores de contratos: empleados con usuario del panel. El nombre y
 * el cargo se guardan TAL CUAL deben aparecer en el informe de supervisión,
 * porque ese PDF es un documento oficial y no admite «como quedó en el
 * directorio» sino «como firma esta persona».
 */
final readonly class SupervisorRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    /** @return list<array<string, mixed>> */
    public function listar(): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT s.*, u.correo AS usuario_correo, u.nombre AS usuario_nombre,
                    (SELECT COUNT(*) FROM cc_contratos k WHERE k.supervisor_id = s.id) AS contratos
               FROM cc_supervisores s
               JOIN usuarios u ON u.id = s.usuario_id
              ORDER BY s.activo DESC, s.nombre_completo',
        );
    }

    /** @return list<array<string, mixed>> */
    public function activos(): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT * FROM cc_supervisores WHERE activo = 1 ORDER BY nombre_completo',
        );
    }

    /** @return ?array<string, mixed> */
    public function porId(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative('SELECT * FROM cc_supervisores WHERE id = ?', [$id]);

        return $fila === false ? null : $fila;
    }

    /** @return ?array<string, mixed> */
    public function porUsuarioId(int $usuarioId): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM cc_supervisores WHERE usuario_id = ?',
            [$usuarioId],
        );

        return $fila === false ? null : $fila;
    }

    /**
     * Usuarios activos del panel que aún no son supervisores, para el
     * selector de vinculación.
     *
     * @return list<array<string, mixed>>
     */
    public function usuariosElegibles(): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT u.id, u.nombre, u.correo, u.cargo
               FROM usuarios u
              WHERE u.activo = 1 AND u.eliminado_en IS NULL
                AND NOT EXISTS (SELECT 1 FROM cc_supervisores s WHERE s.usuario_id = u.id)
              ORDER BY u.nombre',
        );
    }

    /** @param array<string, mixed> $campos */
    public function crear(array $campos): int
    {
        $this->conexion->insert('cc_supervisores', $campos);

        return (int) $this->conexion->lastInsertId();
    }

    /** @param array<string, mixed> $campos */
    public function actualizar(int $id, array $campos): void
    {
        if ($campos !== []) {
            $this->conexion->update('cc_supervisores', $campos, ['id' => $id]);
        }
    }
}

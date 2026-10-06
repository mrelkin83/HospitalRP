<?php

declare(strict_types=1);

namespace App\Modules\Sistema\Repository;

use Doctrine\DBAL\Connection;

/**
 * La tabla `usuarios` vista desde el panel.
 *
 * Separado de Core\Auth\UsuarioRepository a propósito: aquel sirve al login y
 * solo sabe de credenciales; este administra cuentas y nunca debe cargarse en
 * el camino de autenticación. Ninguno de los dos devuelve el hash a la vista.
 */
final readonly class UsuarioAdminRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function todos(bool $incluirEliminados = false): array
    {
        $filtro = $incluirEliminados ? '' : 'WHERE u.eliminado_en IS NULL';

        /** @var list<array<string, mixed>> $filas */
        $filas = $this->conexion->fetchAllAssociative(
            "SELECT u.id, u.nombre, u.correo, u.cargo, u.activo, u.debe_cambiar_pass,
                    u.intentos_fallidos, u.bloqueado_hasta, u.ultimo_acceso, u.eliminado_en,
                    u.rol_id, r.nombre AS rol_nombre, r.slug AS rol_slug,
                    u.dependencia_id, d.nombre AS dependencia_nombre
               FROM usuarios u
               JOIN roles r ON r.id = u.rol_id
          LEFT JOIN dependencias d ON d.id = u.dependencia_id
              {$filtro}
           ORDER BY u.activo DESC, u.nombre",
        );

        return $filas;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function porId(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT u.*, r.nombre AS rol_nombre, r.slug AS rol_slug
               FROM usuarios u
               JOIN roles r ON r.id = u.rol_id
              WHERE u.id = ?',
            [$id],
        );

        return $fila === false ? null : $fila;
    }

    /**
     * Búsqueda por el dato que el funcionario sí recuerda cuando ya no
     * recuerda la contraseña: su correo de ingreso.
     *
     * @return array<string, mixed>|null
     */
    public function porCorreo(string $correo): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT u.*, r.nombre AS rol_nombre, r.slug AS rol_slug
               FROM usuarios u
               JOIN roles r ON r.id = u.rol_id
              WHERE u.correo = ?',
            [$correo],
        );

        return $fila === false ? null : $fila;
    }

    /**
     * Incluye los eliminados: el correo es único en toda la tabla y reciclar
     * el de una cuenta dada de baja confundiría la auditoría histórica.
     */
    public function correoOcupado(string $correo, ?int $exceptoId = null): bool
    {
        $sql = 'SELECT id FROM usuarios WHERE correo = ?';
        $parametros = [$correo];

        if ($exceptoId !== null) {
            $sql .= ' AND id <> ?';
            $parametros[] = $exceptoId;
        }

        return $this->conexion->fetchOne($sql, $parametros) !== false;
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function crear(array $campos): int
    {
        $this->conexion->insert('usuarios', $campos);

        return (int) $this->conexion->lastInsertId();
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function actualizar(int $id, array $campos): void
    {
        $this->conexion->update('usuarios', $campos, ['id' => $id]);
    }

    /**
     * Cambio de credencial: además del hash, limpia el bloqueo por intentos
     * fallidos. Quien acaba de demostrar que conoce la clave nueva no tiene
     * por qué arrastrar el castigo de los intentos viejos.
     */
    public function cambiarPassword(int $id, string $hash, bool $debeCambiar): void
    {
        $this->conexion->executeStatement(
            'UPDATE usuarios
                SET hash_password = ?, debe_cambiar_pass = ?, intentos_fallidos = 0, bloqueado_hasta = NULL
              WHERE id = ?',
            [$hash, $debeCambiar ? 1 : 0, $id],
        );
    }

    public function desbloquear(int $id): void
    {
        $this->conexion->executeStatement(
            'UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?',
            [$id],
        );
    }

    /**
     * Baja lógica. No se borra la fila: la auditoría y los trámites asignados
     * apuntan a este id, y un DELETE dejaría el histórico sin responsable.
     */
    public function eliminar(int $id): void
    {
        $this->conexion->executeStatement(
            'UPDATE usuarios SET eliminado_en = NOW(), activo = 0 WHERE id = ?',
            [$id],
        );
    }

    public function restaurar(int $id): void
    {
        $this->conexion->executeStatement(
            'UPDATE usuarios SET eliminado_en = NULL, activo = 1 WHERE id = ?',
            [$id],
        );
    }

    /**
     * Cuántas cuentas activas quedan con un permiso dado, para no dejar el
     * sitio sin nadie que pueda administrarlo.
     */
    public function administradoresActivos(): int
    {
        return (int) $this->conexion->fetchOne(
            "SELECT COUNT(*)
               FROM usuarios u
               JOIN roles r ON r.id = u.rol_id
              WHERE u.activo = 1
                AND u.eliminado_en IS NULL
                AND JSON_CONTAINS(r.permisos, '\"*\"')",
        );
    }

    public function hashDe(int $id): ?string
    {
        $hash = $this->conexion->fetchOne(
            'SELECT hash_password FROM usuarios WHERE id = ? AND eliminado_en IS NULL',
            [$id],
        );

        return is_string($hash) ? $hash : null;
    }
}

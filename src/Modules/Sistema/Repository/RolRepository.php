<?php

declare(strict_types=1);

namespace App\Modules\Sistema\Repository;

use Doctrine\DBAL\Connection;

/**
 * Roles y su lista de permisos.
 *
 * Los permisos viajan como JSON en la columna `permisos` porque así los lee
 * AutorizacionService; aquí se convierten a lista de PHP y de vuelta para que
 * ni el controlador ni la vista tengan que saber de codificación.
 */
final readonly class RolRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    /**
     * @return list<array<string, mixed>> Con `permisos` decodificado y el conteo de usuarios
     */
    public function todos(): array
    {
        /** @var list<array<string, mixed>> $filas */
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT r.*, (
                        SELECT COUNT(*)
                          FROM usuarios u
                         WHERE u.rol_id = r.id AND u.eliminado_en IS NULL
                    ) AS usuarios
               FROM roles r
           ORDER BY r.protegido DESC, r.nombre',
        );

        foreach ($filas as $indice => $fila) {
            $filas[$indice]['permisos'] = $this->decodificar($fila['permisos']);
        }

        return $filas;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function porId(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative('SELECT * FROM roles WHERE id = ?', [$id]);

        if ($fila === false) {
            return null;
        }

        $fila['permisos'] = $this->decodificar($fila['permisos']);

        return $fila;
    }

    /**
     * @return list<array{id: int, nombre: string}>
     */
    public function paraSelector(): array
    {
        /** @var list<array{id: int, nombre: string}> $filas */
        $filas = $this->conexion->fetchAllAssociative('SELECT id, nombre FROM roles ORDER BY nombre');

        return $filas;
    }

    public function slugOcupado(string $slug, ?int $exceptoId = null): bool
    {
        $sql = 'SELECT id FROM roles WHERE slug = ?';
        $parametros = [$slug];

        if ($exceptoId !== null) {
            $sql .= ' AND id <> ?';
            $parametros[] = $exceptoId;
        }

        return $this->conexion->fetchOne($sql, $parametros) !== false;
    }

    /**
     * @param list<string> $permisos
     */
    public function crear(string $nombre, string $slug, ?string $descripcion, array $permisos): int
    {
        $this->conexion->insert('roles', [
            'nombre' => $nombre,
            'slug' => $slug,
            'descripcion' => $descripcion,
            'permisos' => $this->codificar($permisos),
            'protegido' => 0,
        ]);

        return (int) $this->conexion->lastInsertId();
    }

    /**
     * @param list<string> $permisos
     */
    public function actualizar(int $id, string $nombre, ?string $descripcion, array $permisos): void
    {
        $this->conexion->update('roles', [
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'permisos' => $this->codificar($permisos),
        ], ['id' => $id]);
    }

    public function eliminar(int $id): void
    {
        $this->conexion->delete('roles', ['id' => $id]);
    }

    public function usuariosCon(int $rolId): int
    {
        return (int) $this->conexion->fetchOne(
            'SELECT COUNT(*) FROM usuarios WHERE rol_id = ? AND eliminado_en IS NULL',
            [$rolId],
        );
    }

    /**
     * @param list<string> $permisos
     */
    private function codificar(array $permisos): string
    {
        return json_encode(array_values($permisos), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return list<string>
     */
    private function decodificar(mixed $json): array
    {
        if (!is_string($json)) {
            return [];
        }

        $decodificado = json_decode($json, true);
        $permisos = [];

        if (is_array($decodificado)) {
            foreach ($decodificado as $permiso) {
                if (is_string($permiso) && $permiso !== '') {
                    $permisos[] = $permiso;
                }
            }
        }

        return $permisos;
    }
}

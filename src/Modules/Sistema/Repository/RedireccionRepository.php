<?php

declare(strict_types=1);

namespace App\Modules\Sistema\Repository;

use Doctrine\DBAL\Connection;

/**
 * Redirecciones 301 (CLAUDE.md §7): los enlaces del Joomla viejo citados en
 * actos administrativos, oficios y buscadores no pueden morir en un 404.
 *
 * Dos formas de origen: exacto (`/sitio/index.php/es/transparencia`) o de
 * prefijo terminado en `*` (`/sitio/*`), que atrapa todo lo que empiece así.
 * El exacto siempre gana; entre prefijos gana el más largo.
 */
final readonly class RedireccionRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    /**
     * @return ?array{id: int, destino: string, codigo: int}
     */
    public function destinoPara(string $ruta): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT id, destino, codigo FROM redirecciones WHERE origen = ? AND activa = 1',
            [$ruta],
        );

        if ($fila === false) {
            $fila = $this->conexion->fetchAssociative(
                "SELECT id, destino, codigo FROM redirecciones
                  WHERE activa = 1 AND origen LIKE '%*'
                    AND ? LIKE CONCAT(REPLACE(origen, '*', ''), '%')
                  ORDER BY LENGTH(origen) DESC
                  LIMIT 1",
                [$ruta],
            );
        }

        if ($fila === false) {
            return null;
        }

        return [
            'id' => (int) $fila['id'],
            'destino' => (string) $fila['destino'],
            'codigo' => (int) $fila['codigo'],
        ];
    }

    public function registrarHit(int $id): void
    {
        $this->conexion->executeStatement(
            'UPDATE redirecciones SET hits = hits + 1, ultimo_hit = NOW() WHERE id = ?',
            [$id],
        );
    }

    /**
     * Semilla idempotente: si el origen ya existe (editado desde el panel a
     * futuro), no se toca.
     */
    public function crearSiNoExiste(string $origen, string $destino, int $codigo = 301): bool
    {
        $cambios = $this->conexion->executeStatement(
            'INSERT IGNORE INTO redirecciones (origen, destino, codigo) VALUES (?, ?, ?)',
            [$origen, $destino, $codigo],
        );

        return $cambios > 0;
    }

    // --- Administración desde el panel ---

    /**
     * @return list<array<string, mixed>>
     */
    public function todas(string $busqueda = ''): array
    {
        $where = '';
        $parametros = [];

        if ($busqueda !== '') {
            $where = 'WHERE origen LIKE ? OR destino LIKE ?';
            $parametros = ['%' . $busqueda . '%', '%' . $busqueda . '%'];
        }

        /** @var list<array<string, mixed>> $filas */
        $filas = $this->conexion->fetchAllAssociative(
            "SELECT * FROM redirecciones {$where} ORDER BY hits DESC, origen LIMIT 500",
            $parametros,
        );

        return $filas;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function porId(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative('SELECT * FROM redirecciones WHERE id = ?', [$id]);

        return $fila === false ? null : $fila;
    }

    public function origenOcupado(string $origen, ?int $exceptoId = null): bool
    {
        $sql = 'SELECT id FROM redirecciones WHERE origen = ?';
        $parametros = [$origen];

        if ($exceptoId !== null) {
            $sql .= ' AND id <> ?';
            $parametros[] = $exceptoId;
        }

        return $this->conexion->fetchOne($sql, $parametros) !== false;
    }

    public function crear(string $origen, string $destino, int $codigo, bool $activa): int
    {
        $this->conexion->insert('redirecciones', [
            'origen' => $origen,
            'destino' => $destino,
            'codigo' => $codigo,
            'activa' => $activa ? 1 : 0,
        ]);

        return (int) $this->conexion->lastInsertId();
    }

    public function actualizar(int $id, string $origen, string $destino, int $codigo, bool $activa): void
    {
        $this->conexion->update('redirecciones', [
            'origen' => $origen,
            'destino' => $destino,
            'codigo' => $codigo,
            'activa' => $activa ? 1 : 0,
        ], ['id' => $id]);
    }

    public function eliminar(int $id): void
    {
        $this->conexion->delete('redirecciones', ['id' => $id]);
    }

    /**
     * @return array{total: int, activas: int, hits: int}
     */
    public function resumen(): array
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT COUNT(*) AS total, SUM(activa) AS activas, COALESCE(SUM(hits), 0) AS hits FROM redirecciones',
        );

        return [
            'total' => (int) ($fila['total'] ?? 0),
            'activas' => (int) ($fila['activas'] ?? 0),
            'hits' => (int) ($fila['hits'] ?? 0),
        ];
    }
}

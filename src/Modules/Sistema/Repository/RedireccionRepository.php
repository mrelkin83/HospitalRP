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
}

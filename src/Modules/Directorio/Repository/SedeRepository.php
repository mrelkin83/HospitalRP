<?php

declare(strict_types=1);

namespace App\Modules\Directorio\Repository;

use App\Core\Support\Slugs;
use App\Modules\Directorio\Entity\Sede;
use Doctrine\DBAL\Connection;

final readonly class SedeRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    /**
     * @return list<Sede>
     */
    public function activasPublicas(): array
    {
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT * FROM sedes WHERE activa = 1 ORDER BY orden, nombre',
        );

        return array_map(Sede::desdeFila(...), $filas);
    }

    /**
     * @return list<Sede>
     */
    public function conUrgencias(): array
    {
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT * FROM sedes WHERE activa = 1 AND tiene_urgencias = 1 ORDER BY orden, nombre',
        );

        return array_map(Sede::desdeFila(...), $filas);
    }

    public function publicaPorSlug(string $slug): ?Sede
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM sedes WHERE slug = ? AND activa = 1',
            [$slug],
        );

        return $fila === false ? null : Sede::desdeFila($fila);
    }

    /**
     * @return list<Sede>
     */
    public function listarParaPanel(): array
    {
        $filas = $this->conexion->fetchAllAssociative('SELECT * FROM sedes ORDER BY orden, nombre');

        return array_map(Sede::desdeFila(...), $filas);
    }

    public function porId(int $id): ?Sede
    {
        $fila = $this->conexion->fetchAssociative('SELECT * FROM sedes WHERE id = ?', [$id]);

        return $fila === false ? null : Sede::desdeFila($fila);
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function crear(array $campos): int
    {
        $campos['slug'] = $this->slugUnico((string) $campos['nombre']);
        $this->conexion->insert('sedes', $campos);

        return (int) $this->conexion->lastInsertId();
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function actualizar(int $id, array $campos): void
    {
        $this->conexion->update('sedes', $campos, ['id' => $id]);
    }

    private function slugUnico(string $nombre): string
    {
        $base = Slugs::desde($nombre, 170);
        $slug = $base;
        $sufijo = 2;

        while (false !== $this->conexion->fetchOne('SELECT id FROM sedes WHERE slug = ?', [$slug])) {
            $slug = $base . '-' . $sufijo++;
        }

        return $slug;
    }
}

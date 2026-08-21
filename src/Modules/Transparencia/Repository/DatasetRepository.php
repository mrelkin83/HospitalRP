<?php

declare(strict_types=1);

namespace App\Modules\Transparencia\Repository;

use App\Core\Support\Slugs;
use Doctrine\DBAL\Connection;

/**
 * Datos abiertos: conjuntos de datos con recursos descargables en formato
 * abierto (CSV, JSON, XML…), nunca en PDF.
 */
final readonly class DatasetRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    /**
     * @return list<array<string, mixed>> Datasets con sus recursos
     */
    public function publicados(): array
    {
        return $this->conRecursos('WHERE d.publicado = 1');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarParaPanel(): array
    {
        return $this->conRecursos('');
    }

    /**
     * @return ?array<string, mixed>
     */
    public function porId(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative('SELECT * FROM datasets WHERE id = ?', [$id]);

        return $fila === false ? null : $fila;
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function crear(array $campos): int
    {
        $base = Slugs::desde((string) $campos['titulo'], 300);
        $slug = $base;
        $sufijo = 2;

        while (false !== $this->conexion->fetchOne('SELECT id FROM datasets WHERE slug = ?', [$slug])) {
            $slug = $base . '-' . $sufijo++;
        }

        $campos['slug'] = $slug;
        $this->conexion->insert('datasets', $campos);

        return (int) $this->conexion->lastInsertId();
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function actualizar(int $id, array $campos): void
    {
        $this->conexion->update('datasets', $campos, ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function crearRecurso(array $campos): int
    {
        $this->conexion->insert('dataset_recursos', $campos);

        return (int) $this->conexion->lastInsertId();
    }

    /**
     * @return ?array<string, mixed>
     */
    public function recursoPublico(int $recursoId): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT r.* FROM dataset_recursos r
               JOIN datasets d ON d.id = r.dataset_id AND d.publicado = 1
              WHERE r.id = ?',
            [$recursoId],
        );

        return $fila === false ? null : $fila;
    }

    public function eliminarRecurso(int $recursoId): void
    {
        $this->conexion->delete('dataset_recursos', ['id' => $recursoId]);
    }

    public function contarDescarga(int $recursoId): void
    {
        $this->conexion->executeStatement(
            'UPDATE dataset_recursos SET descargas = descargas + 1 WHERE id = ?',
            [$recursoId],
        );
    }

    // --- soporte ---

    /**
     * @return list<array<string, mixed>>
     */
    private function conRecursos(string $condicion): array
    {
        $datasets = $this->conexion->fetchAllAssociative(
            "SELECT d.*, dep.nombre AS dependencia_nombre
               FROM datasets d
               LEFT JOIN dependencias dep ON dep.id = d.dependencia_id
              {$condicion}
              ORDER BY d.titulo",
        );

        $recursos = [];

        foreach ($this->conexion->fetchAllAssociative(
            'SELECT * FROM dataset_recursos ORDER BY formato',
        ) as $recurso) {
            $recursos[(int) $recurso['dataset_id']][] = $recurso;
        }

        return array_map(static function (array $d) use ($recursos): array {
            $d['recursos'] = $recursos[(int) $d['id']] ?? [];

            return $d;
        }, $datasets);
    }
}

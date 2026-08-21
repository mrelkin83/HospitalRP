<?php

declare(strict_types=1);

namespace App\Modules\Directorio\Repository;

use App\Core\Support\Slugs;
use App\Modules\Directorio\Entity\Especialidad;
use Doctrine\DBAL\Connection;

final readonly class EspecialidadRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    /**
     * Activas con sus sedes cargadas en una sola consulta extra: alimenta el
     * filtro por sede del sitio público.
     *
     * @return list<Especialidad>
     */
    public function activasConSedes(): array
    {
        return $this->conSedes('WHERE activa = 1');
    }

    /**
     * @return list<Especialidad>
     */
    public function listarParaPanel(): array
    {
        return $this->conSedes('');
    }

    public function publicaPorSlug(string $slug): ?Especialidad
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM especialidades WHERE slug = ? AND activa = 1',
            [$slug],
        );

        return $fila === false ? null : Especialidad::desdeFila($fila, $this->sedesDe((int) $fila['id']));
    }

    public function porId(int $id): ?Especialidad
    {
        $fila = $this->conexion->fetchAssociative('SELECT * FROM especialidades WHERE id = ?', [$id]);

        return $fila === false ? null : Especialidad::desdeFila($fila, $this->sedesDe($id));
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function crear(array $campos): int
    {
        $campos['slug'] = $this->slugUnico((string) $campos['nombre']);
        $this->conexion->insert('especialidades', $campos);

        return (int) $this->conexion->lastInsertId();
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function actualizar(int $id, array $campos): void
    {
        $this->conexion->update('especialidades', $campos, ['id' => $id]);
    }

    /**
     * @param list<int> $sedeIds
     */
    public function sincronizarSedes(int $id, array $sedeIds): void
    {
        $this->conexion->delete('especialidad_sede', ['especialidad_id' => $id]);

        foreach (array_unique($sedeIds) as $sedeId) {
            $this->conexion->insert('especialidad_sede', [
                'especialidad_id' => $id,
                'sede_id' => $sedeId,
            ]);
        }
    }

    // --- soporte ---

    /**
     * @return list<Especialidad>
     */
    private function conSedes(string $condicion): array
    {
        $filas = $this->conexion->fetchAllAssociative(
            "SELECT * FROM especialidades {$condicion} ORDER BY orden, nombre",
        );

        $relaciones = [];

        foreach ($this->conexion->fetchAllAssociative('SELECT especialidad_id, sede_id FROM especialidad_sede') as $r) {
            $relaciones[(int) $r['especialidad_id']][] = (int) $r['sede_id'];
        }

        return array_map(
            static fn (array $f): Especialidad => Especialidad::desdeFila($f, $relaciones[(int) $f['id']] ?? []),
            $filas,
        );
    }

    /**
     * @return list<int>
     */
    private function sedesDe(int $id): array
    {
        return array_map(
            intval(...),
            $this->conexion->fetchFirstColumn('SELECT sede_id FROM especialidad_sede WHERE especialidad_id = ?', [$id]),
        );
    }

    private function slugUnico(string $nombre): string
    {
        $base = Slugs::desde($nombre, 170);
        $slug = $base;
        $sufijo = 2;

        while (false !== $this->conexion->fetchOne('SELECT id FROM especialidades WHERE slug = ?', [$slug])) {
            $slug = $base . '-' . $sufijo++;
        }

        return $slug;
    }
}

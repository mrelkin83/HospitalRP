<?php

declare(strict_types=1);

namespace App\Modules\Directorio\Repository;

use App\Core\Support\Slugs;
use App\Modules\Directorio\Entity\Servicio;
use Doctrine\DBAL\Connection;

final readonly class ServicioRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    /**
     * @return list<Servicio>
     */
    public function activosConSedes(): array
    {
        return $this->conSedes('WHERE activo = 1');
    }

    /**
     * @return list<Servicio>
     */
    public function listarParaPanel(): array
    {
        return $this->conSedes('');
    }

    /**
     * Los servicios que la portada muestra con fotografía.
     *
     * Exige `imagen_id`: una tarjeta de servicio sin foto deja un hueco gris
     * en medio de una retícula que vive de las imágenes, y queda peor que no
     * estar. Los que no tienen fotografía siguen apareciendo en el portafolio
     * completo, que es una lista y no una retícula.
     *
     * El orden es el de Panel → Servicios, así que el hospital decide cuáles
     * salen primero sin tocar código.
     *
     * @return list<Servicio>
     */
    public function conFotoParaLaPortada(int $limite = 6): array
    {
        // El recorte va en PHP y no en la consulta: conSedes() pega la
        // condición ANTES del ORDER BY, así que un LIMIT ahí sería un error
        // de sintaxis. Son quince filas como mucho; cortar aquí no cuesta.
        return array_slice(
            $this->conSedes('WHERE activo = 1 AND imagen_id IS NOT NULL'),
            0,
            max(1, min(12, $limite)),
        );
    }

    public function publicoPorSlug(string $slug): ?Servicio
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM servicios WHERE slug = ? AND activo = 1',
            [$slug],
        );

        if ($fila === false) {
            return null;
        }

        $sedeIds = array_map(
            intval(...),
            $this->conexion->fetchFirstColumn('SELECT sede_id FROM servicio_sede WHERE servicio_id = ?', [(int) $fila['id']]),
        );

        return Servicio::desdeFila($fila, $sedeIds);
    }

    public function porId(int $id): ?Servicio
    {
        $fila = $this->conexion->fetchAssociative('SELECT * FROM servicios WHERE id = ?', [$id]);

        if ($fila === false) {
            return null;
        }

        $sedeIds = array_map(
            intval(...),
            $this->conexion->fetchFirstColumn('SELECT sede_id FROM servicio_sede WHERE servicio_id = ?', [$id]),
        );

        return Servicio::desdeFila($fila, $sedeIds);
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function crear(array $campos): int
    {
        $campos['slug'] = $this->slugUnico((string) $campos['nombre']);
        $this->conexion->insert('servicios', $campos);

        return (int) $this->conexion->lastInsertId();
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function actualizar(int $id, array $campos): void
    {
        $this->conexion->update('servicios', $campos, ['id' => $id]);
    }

    /**
     * @param list<int> $sedeIds
     */
    public function sincronizarSedes(int $id, array $sedeIds): void
    {
        $this->conexion->delete('servicio_sede', ['servicio_id' => $id]);

        foreach (array_unique($sedeIds) as $sedeId) {
            $this->conexion->insert('servicio_sede', [
                'servicio_id' => $id,
                'sede_id' => $sedeId,
            ]);
        }
    }

    // --- soporte ---

    /**
     * @return list<Servicio>
     */
    private function conSedes(string $condicion): array
    {
        $filas = $this->conexion->fetchAllAssociative(
            "SELECT * FROM servicios {$condicion} ORDER BY orden, nombre",
        );

        $relaciones = [];

        foreach ($this->conexion->fetchAllAssociative('SELECT servicio_id, sede_id FROM servicio_sede') as $r) {
            $relaciones[(int) $r['servicio_id']][] = (int) $r['sede_id'];
        }

        return array_map(
            static fn (array $f): Servicio => Servicio::desdeFila($f, $relaciones[(int) $f['id']] ?? []),
            $filas,
        );
    }

    private function slugUnico(string $nombre): string
    {
        $base = Slugs::desde($nombre, 170);
        $slug = $base;
        $sufijo = 2;

        while (false !== $this->conexion->fetchOne('SELECT id FROM servicios WHERE slug = ?', [$slug])) {
            $slug = $base . '-' . $sufijo++;
        }

        return $slug;
    }
}

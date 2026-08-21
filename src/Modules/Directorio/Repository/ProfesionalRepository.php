<?php

declare(strict_types=1);

namespace App\Modules\Directorio\Repository;

use App\Core\Support\Slugs;
use App\Modules\Directorio\Entity\Profesional;
use Doctrine\DBAL\Connection;

final readonly class ProfesionalRepository
{
    /**
     * Criterio de aceptación de la Fase 2: nada de este directorio sale al
     * público sin autorización expresa Y la cuenta activa.
     */
    private const string VISIBLE_PUBLICO = 'autoriza_publicacion = 1 AND activo = 1';

    public function __construct(private Connection $conexion)
    {
    }

    /**
     * @return list<Profesional>
     */
    public function publicosConEspecialidades(): array
    {
        return $this->conEspecialidades('WHERE ' . self::VISIBLE_PUBLICO);
    }

    /**
     * @return list<Profesional>
     */
    public function listarParaPanel(): array
    {
        return $this->conEspecialidades('');
    }

    public function publicoPorSlug(string $slug): ?Profesional
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM profesionales WHERE slug = ? AND ' . self::VISIBLE_PUBLICO,
            [$slug],
        );

        if ($fila === false) {
            return null;
        }

        [$ids, $nombres] = $this->especialidadesDe((int) $fila['id']);

        return Profesional::desdeFila($fila, $ids, $nombres);
    }

    /**
     * Profesionales públicos de una especialidad, para su página de detalle.
     *
     * @return list<Profesional>
     */
    public function publicosDeEspecialidad(int $especialidadId): array
    {
        return $this->conEspecialidades(sprintf(
            'JOIN profesional_especialidad pe ON pe.profesional_id = profesionales.id
             AND pe.especialidad_id = %d WHERE ' . self::VISIBLE_PUBLICO,
            $especialidadId,
        ));
    }

    public function porId(int $id): ?Profesional
    {
        $fila = $this->conexion->fetchAssociative('SELECT * FROM profesionales WHERE id = ?', [$id]);

        if ($fila === false) {
            return null;
        }

        [$ids, $nombres] = $this->especialidadesDe($id);

        return Profesional::desdeFila($fila, $ids, $nombres);
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function crear(array $campos): int
    {
        $campos['slug'] = $this->slugUnico((string) $campos['nombre']);
        $this->conexion->insert('profesionales', $campos);

        return (int) $this->conexion->lastInsertId();
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function actualizar(int $id, array $campos): void
    {
        $this->conexion->update('profesionales', $campos, ['id' => $id]);
    }

    /**
     * @param list<int> $especialidadIds
     */
    public function sincronizarEspecialidades(int $id, array $especialidadIds): void
    {
        $this->conexion->delete('profesional_especialidad', ['profesional_id' => $id]);

        foreach (array_unique($especialidadIds) as $especialidadId) {
            $this->conexion->insert('profesional_especialidad', [
                'profesional_id' => $id,
                'especialidad_id' => $especialidadId,
            ]);
        }
    }

    // --- soporte ---

    /**
     * @return list<Profesional>
     */
    private function conEspecialidades(string $condicion): array
    {
        $filas = $this->conexion->fetchAllAssociative(
            "SELECT profesionales.* FROM profesionales {$condicion} ORDER BY orden, nombre",
        );

        $ids = [];
        $nombres = [];

        foreach ($this->conexion->fetchAllAssociative(
            'SELECT pe.profesional_id, e.id, e.nombre
               FROM profesional_especialidad pe
               JOIN especialidades e ON e.id = pe.especialidad_id
              ORDER BY e.nombre',
        ) as $r) {
            $ids[(int) $r['profesional_id']][] = (int) $r['id'];
            $nombres[(int) $r['profesional_id']][] = (string) $r['nombre'];
        }

        return array_map(
            static fn (array $f): Profesional => Profesional::desdeFila(
                $f,
                $ids[(int) $f['id']] ?? [],
                $nombres[(int) $f['id']] ?? [],
            ),
            $filas,
        );
    }

    /**
     * @return array{list<int>, list<string>}
     */
    private function especialidadesDe(int $id): array
    {
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT e.id, e.nombre
               FROM profesional_especialidad pe
               JOIN especialidades e ON e.id = pe.especialidad_id
              WHERE pe.profesional_id = ?
              ORDER BY e.nombre',
            [$id],
        );

        return [
            array_map(static fn (array $f): int => (int) $f['id'], $filas),
            array_map(static fn (array $f): string => (string) $f['nombre'], $filas),
        ];
    }

    private function slugUnico(string $nombre): string
    {
        $base = Slugs::desde($nombre, 200);
        $slug = $base;
        $sufijo = 2;

        while (false !== $this->conexion->fetchOne('SELECT id FROM profesionales WHERE slug = ?', [$slug])) {
            $slug = $base . '-' . $sufijo++;
        }

        return $slug;
    }
}

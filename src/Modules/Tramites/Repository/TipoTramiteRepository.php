<?php

declare(strict_types=1);

namespace App\Modules\Tramites\Repository;

use App\Core\Support\Slugs;
use App\Modules\Tramites\Entity\TipoTramite;
use Doctrine\DBAL\Connection;

final readonly class TipoTramiteRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    /**
     * @return list<TipoTramite> Activos, sin campos ni estados (para listados)
     */
    public function activos(): array
    {
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT * FROM tipos_tramite WHERE activo = 1 ORDER BY orden, nombre',
        );

        return array_map(static fn (array $f): TipoTramite => TipoTramite::desdeFila($f), $filas);
    }

    /**
     * @return list<TipoTramite>
     */
    public function listarParaPanel(): array
    {
        $filas = $this->conexion->fetchAllAssociative('SELECT * FROM tipos_tramite ORDER BY orden, nombre');

        return array_map(static fn (array $f): TipoTramite => TipoTramite::desdeFila($f), $filas);
    }

    public function activoPorSlug(string $slug): ?TipoTramite
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM tipos_tramite WHERE slug = ? AND activo = 1',
            [$slug],
        );

        return $fila === false ? null : $this->completo($fila);
    }

    public function porId(int $id): ?TipoTramite
    {
        $fila = $this->conexion->fetchAssociative('SELECT * FROM tipos_tramite WHERE id = ?', [$id]);

        return $fila === false ? null : $this->completo($fila);
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function crear(array $campos): int
    {
        $base = Slugs::desde((string) $campos['nombre'], 210);
        $slug = $base;
        $sufijo = 2;

        while (false !== $this->conexion->fetchOne('SELECT id FROM tipos_tramite WHERE slug = ?', [$slug])) {
            $slug = $base . '-' . $sufijo++;
        }

        $campos['slug'] = $slug;
        $this->conexion->insert('tipos_tramite', $campos);

        return (int) $this->conexion->lastInsertId();
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function actualizar(int $id, array $campos): void
    {
        $this->conexion->update('tipos_tramite', $campos, ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function crearEstado(array $campos): int
    {
        $this->conexion->insert('tramite_estados', $campos);

        return (int) $this->conexion->lastInsertId();
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function crearCampo(array $campos): int
    {
        $this->conexion->insert('tramite_campos', $campos);

        return (int) $this->conexion->lastInsertId();
    }

    public function eliminarCampo(int $campoId): void
    {
        // Los valores ya radicados caen en cascada con el campo; los
        // trámites históricos conservan asunto, descripción y adjuntos.
        $this->conexion->delete('tramite_campos', ['id' => $campoId]);
    }

    // --- soporte ---

    /**
     * @param array<string, mixed> $fila
     */
    private function completo(array $fila): TipoTramite
    {
        $id = (int) $fila['id'];

        return TipoTramite::desdeFila(
            $fila,
            $this->conexion->fetchAllAssociative(
                'SELECT * FROM tramite_campos WHERE tipo_tramite_id = ? ORDER BY orden, id',
                [$id],
            ),
            $this->conexion->fetchAllAssociative(
                'SELECT * FROM tramite_estados WHERE tipo_tramite_id = ? ORDER BY orden, id',
                [$id],
            ),
        );
    }
}

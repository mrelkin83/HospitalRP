<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Repository;

use App\Core\Support\Slugs;
use App\Modules\Contenido\Entity\Campana;
use App\Modules\Contenido\Entity\EstadoAprobacion;
use App\Modules\Contenido\Request\CampanaDatos;
use Doctrine\DBAL\Connection;

final readonly class CampanaRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    public function porId(int $id): ?Campana
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM campanas WHERE id = ? AND eliminado_en IS NULL',
            [$id],
        );

        return $fila === false ? null : Campana::desdeFila($fila);
    }

    /**
     * @return list<Campana>
     */
    public function listarParaPanel(): array
    {
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT * FROM campanas WHERE eliminado_en IS NULL ORDER BY actualizado_en DESC LIMIT 200',
        );

        return array_map(Campana::desdeFila(...), $filas);
    }

    public function crear(CampanaDatos $datos, string $contenidoSaneado, ?int $imagenId, ?int $creadoPor): int
    {
        $this->conexion->insert('campanas', [
            'slug' => $this->slugUnico($datos->titulo),
            'titulo' => $datos->titulo,
            'resumen' => $datos->resumen,
            'contenido' => $contenidoSaneado,
            'imagen_id' => $imagenId,
            'poblacion_objetivo' => $datos->poblacionObjetivo,
            'inicio' => $datos->inicio,
            'fin' => $datos->fin,
            'estado_aprobacion' => EstadoAprobacion::Borrador->value,
            'creado_por' => $creadoPor,
            'actualizado_por' => $creadoPor,
        ]);

        return (int) $this->conexion->lastInsertId();
    }

    public function actualizar(int $id, CampanaDatos $datos, string $contenidoSaneado, ?int $imagenId, ?int $actualizadoPor): void
    {
        $this->conexion->update('campanas', [
            'titulo' => $datos->titulo,
            'resumen' => $datos->resumen,
            'contenido' => $contenidoSaneado,
            'imagen_id' => $imagenId,
            'poblacion_objetivo' => $datos->poblacionObjetivo,
            'inicio' => $datos->inicio,
            'fin' => $datos->fin,
            'actualizado_por' => $actualizadoPor,
        ], ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function aplicarSnapshot(int $id, array $campos, ?int $actualizadoPor): void
    {
        $this->conexion->update('campanas', [
            'titulo' => (string) $campos['titulo'],
            'resumen' => $campos['resumen'] !== null ? (string) $campos['resumen'] : null,
            'contenido' => (string) $campos['contenido'],
            'imagen_id' => $campos['imagen_id'] !== null ? (int) $campos['imagen_id'] : null,
            'poblacion_objetivo' => $campos['poblacion_objetivo'] !== null ? (string) $campos['poblacion_objetivo'] : null,
            'inicio' => $campos['inicio'] !== null ? (string) $campos['inicio'] : null,
            'fin' => $campos['fin'] !== null ? (string) $campos['fin'] : null,
            'actualizado_por' => $actualizadoPor,
        ], ['id' => $id]);
    }

    public function cambiarEstado(int $id, EstadoAprobacion $estado): void
    {
        $this->conexion->update('campanas', ['estado_aprobacion' => $estado->value], ['id' => $id]);
    }

    public function eliminarLogicamente(int $id, ?int $usuarioId): void
    {
        $this->conexion->executeStatement(
            'UPDATE campanas SET eliminado_en = NOW(), actualizado_por = ? WHERE id = ?',
            [$usuarioId, $id],
        );
    }

    private function slugUnico(string $titulo): string
    {
        $base = Slugs::desde($titulo);
        $slug = $base;
        $sufijo = 2;

        while (false !== $this->conexion->fetchOne('SELECT id FROM campanas WHERE slug = ?', [$slug])) {
            $slug = $base . '-' . $sufijo++;
        }

        return $slug;
    }
}

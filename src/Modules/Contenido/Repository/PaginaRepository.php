<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Repository;

use App\Core\Support\Slugs;
use App\Modules\Contenido\Entity\EstadoAprobacion;
use App\Modules\Contenido\Entity\Pagina;
use App\Modules\Contenido\Request\PaginaDatos;
use Doctrine\DBAL\Connection;

final readonly class PaginaRepository
{
    private const string VISIBLE_PUBLICO = "estado_aprobacion = 'publicado'
        AND eliminado_en IS NULL
        AND (publicar_en IS NULL OR publicar_en <= NOW())
        AND (despublicar_en IS NULL OR despublicar_en > NOW())";

    public function __construct(private Connection $conexion)
    {
    }

    public function publicaPorRuta(string $ruta): ?Pagina
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM paginas WHERE ruta = ? AND ' . self::VISIBLE_PUBLICO,
            ['/' . ltrim($ruta, '/')],
        );

        return $fila === false ? null : Pagina::desdeFila($fila);
    }

    public function contarVista(int $id): void
    {
        $this->conexion->executeStatement('UPDATE paginas SET vistas = vistas + 1 WHERE id = ?', [$id]);
    }

    public function porId(int $id): ?Pagina
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM paginas WHERE id = ? AND eliminado_en IS NULL',
            [$id],
        );

        return $fila === false ? null : Pagina::desdeFila($fila);
    }

    /**
     * @return list<Pagina>
     */
    public function listarParaPanel(): array
    {
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT * FROM paginas WHERE eliminado_en IS NULL ORDER BY ruta LIMIT 300',
        );

        return array_map(Pagina::desdeFila(...), $filas);
    }

    public function rutaOcupada(string $ruta, ?int $exceptoId = null): bool
    {
        $id = $this->conexion->fetchOne(
            'SELECT id FROM paginas WHERE ruta = ? AND eliminado_en IS NULL' . ($exceptoId !== null ? ' AND id <> ?' : ''),
            $exceptoId !== null ? [$ruta, $exceptoId] : [$ruta],
        );

        return $id !== false;
    }

    public function crear(PaginaDatos $datos, string $contenidoSaneado, ?int $imagenId, ?int $creadoPor): int
    {
        $this->conexion->insert('paginas', [
            'slug' => Slugs::desde($datos->titulo),
            'ruta' => $datos->ruta,
            'titulo' => $datos->titulo,
            'resumen' => $datos->resumen,
            'contenido' => $contenidoSaneado,
            'imagen_id' => $imagenId,
            'meta_titulo' => $datos->metaTitulo,
            'meta_descripcion' => $datos->metaDescripcion,
            'noindex' => $datos->noindex ? 1 : 0,
            'publicar_en' => $datos->publicarEn,
            'despublicar_en' => $datos->despublicarEn,
            'estado_aprobacion' => EstadoAprobacion::Borrador->value,
            'creado_por' => $creadoPor,
            'actualizado_por' => $creadoPor,
        ]);

        return (int) $this->conexion->lastInsertId();
    }

    public function actualizar(int $id, PaginaDatos $datos, string $contenidoSaneado, ?int $imagenId, ?int $actualizadoPor): void
    {
        $this->conexion->update('paginas', [
            'ruta' => $datos->ruta,
            'titulo' => $datos->titulo,
            'resumen' => $datos->resumen,
            'contenido' => $contenidoSaneado,
            'imagen_id' => $imagenId,
            'meta_titulo' => $datos->metaTitulo,
            'meta_descripcion' => $datos->metaDescripcion,
            'noindex' => $datos->noindex ? 1 : 0,
            'publicar_en' => $datos->publicarEn,
            'despublicar_en' => $datos->despublicarEn,
            'actualizado_por' => $actualizadoPor,
        ], ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function aplicarSnapshot(int $id, array $campos, ?int $actualizadoPor): void
    {
        $this->conexion->update('paginas', [
            'titulo' => (string) $campos['titulo'],
            'resumen' => $campos['resumen'] !== null ? (string) $campos['resumen'] : null,
            'contenido' => (string) $campos['contenido'],
            'imagen_id' => $campos['imagen_id'] !== null ? (int) $campos['imagen_id'] : null,
            'meta_titulo' => $campos['meta_titulo'] !== null ? (string) $campos['meta_titulo'] : null,
            'meta_descripcion' => $campos['meta_descripcion'] !== null ? (string) $campos['meta_descripcion'] : null,
            'noindex' => !empty($campos['noindex']) ? 1 : 0,
            'actualizado_por' => $actualizadoPor,
        ], ['id' => $id]);
    }

    public function cambiarEstado(int $id, EstadoAprobacion $estado, ?int $usuarioId): void
    {
        $this->conexion->update('paginas', ['estado_aprobacion' => $estado->value], ['id' => $id]);

        if ($estado === EstadoAprobacion::Aprobado) {
            $this->conexion->executeStatement(
                'UPDATE paginas SET aprobado_por = ?, aprobado_en = NOW() WHERE id = ?',
                [$usuarioId, $id],
            );
        }
    }

    /**
     * Devuelve false si la página está protegida: las que exige la normativa
     * no se eliminan, solo se editan.
     */
    public function eliminarLogicamente(int $id, ?int $usuarioId): bool
    {
        $afectadas = $this->conexion->executeStatement(
            'UPDATE paginas SET eliminado_en = NOW(), actualizado_por = ? WHERE id = ? AND protegida = 0',
            [$usuarioId, $id],
        );

        return $afectadas > 0;
    }
}

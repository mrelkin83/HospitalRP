<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Repository;

use App\Core\Support\Slugs;
use App\Modules\Contenido\Entity\EstadoAprobacion;
use App\Modules\Contenido\Entity\Noticia;
use App\Modules\Contenido\Request\NoticiaDatos;
use Doctrine\DBAL\Connection;

final readonly class NoticiaRepository
{
    /**
     * La condición de visibilidad pública, en un solo lugar: publicada, no
     * eliminada y dentro de la ventana programada. La evalúa el reloj de
     * MySQL, el mismo de la tarea programada.
     */
    private const string VISIBLE_PUBLICO = "estado_aprobacion = 'publicado'
        AND eliminado_en IS NULL
        AND (publicar_en IS NULL OR publicar_en <= NOW())
        AND (despublicar_en IS NULL OR despublicar_en > NOW())";

    public function __construct(private Connection $conexion)
    {
    }

    public function porId(int $id): ?Noticia
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM noticias WHERE id = ? AND eliminado_en IS NULL',
            [$id],
        );

        return $fila === false ? null : Noticia::desdeFila($fila);
    }

    public function publicaPorSlug(string $slug): ?Noticia
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM noticias WHERE slug = ? AND ' . self::VISIBLE_PUBLICO,
            [$slug],
        );

        return $fila === false ? null : Noticia::desdeFila($fila);
    }

    /**
     * @return list<Noticia>
     */
    public function publicasRecientes(int $limite): array
    {
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT * FROM noticias WHERE ' . self::VISIBLE_PUBLICO . '
              ORDER BY COALESCE(publicar_en, creado_en) DESC
              LIMIT ' . max(1, $limite),
        );

        return array_map(Noticia::desdeFila(...), $filas);
    }

    /**
     * @return array{noticias: list<Noticia>, total: int}
     */
    public function publicasPaginadas(int $pagina, int $porPagina): array
    {
        $total = (int) $this->conexion->fetchOne(
            'SELECT COUNT(*) FROM noticias WHERE ' . self::VISIBLE_PUBLICO,
        );

        $filas = $this->conexion->fetchAllAssociative(sprintf(
            'SELECT * FROM noticias WHERE %s
              ORDER BY COALESCE(publicar_en, creado_en) DESC
              LIMIT %d OFFSET %d',
            self::VISIBLE_PUBLICO,
            max(1, $porPagina),
            max(0, ($pagina - 1) * $porPagina),
        ));

        return ['noticias' => array_map(Noticia::desdeFila(...), $filas), 'total' => $total];
    }

    /**
     * Bandeja del panel: todo lo no eliminado, lo más trabajado primero.
     *
     * @return list<Noticia>
     */
    public function listarParaPanel(): array
    {
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT * FROM noticias WHERE eliminado_en IS NULL
              ORDER BY actualizado_en DESC
              LIMIT 200',
        );

        return array_map(Noticia::desdeFila(...), $filas);
    }

    public function crear(NoticiaDatos $datos, string $contenidoSaneado, ?int $imagenId, ?int $creadoPor): int
    {
        $this->conexion->insert('noticias', [
            'slug' => $this->slugUnico($datos->titulo),
            'titulo' => $datos->titulo,
            'resumen' => $datos->resumen,
            'contenido' => $contenidoSaneado,
            'imagen_id' => $imagenId,
            'categoria_id' => $datos->categoriaId,
            'destacada' => $datos->destacada ? 1 : 0,
            'meta_titulo' => $datos->metaTitulo,
            'meta_descripcion' => $datos->metaDescripcion,
            'publicar_en' => $datos->publicarEn,
            'despublicar_en' => $datos->despublicarEn,
            'estado_aprobacion' => EstadoAprobacion::Borrador->value,
            'creado_por' => $creadoPor,
            'actualizado_por' => $creadoPor,
        ]);

        return (int) $this->conexion->lastInsertId();
    }

    public function actualizar(int $id, NoticiaDatos $datos, string $contenidoSaneado, ?int $imagenId, ?int $actualizadoPor): void
    {
        $this->conexion->update('noticias', [
            'titulo' => $datos->titulo,
            'resumen' => $datos->resumen,
            'contenido' => $contenidoSaneado,
            'imagen_id' => $imagenId,
            'categoria_id' => $datos->categoriaId,
            'destacada' => $datos->destacada ? 1 : 0,
            'meta_titulo' => $datos->metaTitulo,
            'meta_descripcion' => $datos->metaDescripcion,
            'publicar_en' => $datos->publicarEn,
            'despublicar_en' => $datos->despublicarEn,
            'actualizado_por' => $actualizadoPor,
        ], ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $campos Campos de un snapshot de versión
     */
    public function aplicarSnapshot(int $id, array $campos, ?int $actualizadoPor): void
    {
        $this->conexion->update('noticias', [
            'titulo' => (string) $campos['titulo'],
            'resumen' => (string) $campos['resumen'],
            'contenido' => (string) $campos['contenido'],
            'imagen_id' => $campos['imagen_id'] !== null ? (int) $campos['imagen_id'] : null,
            'categoria_id' => $campos['categoria_id'] !== null ? (int) $campos['categoria_id'] : null,
            'destacada' => !empty($campos['destacada']) ? 1 : 0,
            'meta_titulo' => $campos['meta_titulo'] !== null ? (string) $campos['meta_titulo'] : null,
            'meta_descripcion' => $campos['meta_descripcion'] !== null ? (string) $campos['meta_descripcion'] : null,
            'actualizado_por' => $actualizadoPor,
        ], ['id' => $id]);
    }

    public function cambiarEstado(int $id, EstadoAprobacion $estado, ?int $usuarioId): void
    {
        $campos = ['estado_aprobacion' => $estado->value];

        if ($estado === EstadoAprobacion::Aprobado) {
            $campos['aprobado_por'] = $usuarioId;
        }

        $this->conexion->update('noticias', $campos, ['id' => $id]);

        if ($estado === EstadoAprobacion::Aprobado) {
            $this->conexion->executeStatement('UPDATE noticias SET aprobado_en = NOW() WHERE id = ?', [$id]);
        }
    }

    public function eliminarLogicamente(int $id, ?int $usuarioId): void
    {
        $this->conexion->executeStatement(
            'UPDATE noticias SET eliminado_en = NOW(), actualizado_por = ? WHERE id = ?',
            [$usuarioId, $id],
        );
    }

    private function slugUnico(string $titulo): string
    {
        $base = Slugs::desde($titulo);
        $slug = $base;
        $sufijo = 2;

        while (false !== $this->conexion->fetchOne('SELECT id FROM noticias WHERE slug = ?', [$slug])) {
            $slug = $base . '-' . $sufijo++;
        }

        return $slug;
    }
}

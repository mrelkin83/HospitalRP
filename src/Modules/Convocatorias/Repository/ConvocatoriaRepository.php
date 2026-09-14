<?php

declare(strict_types=1);

namespace App\Modules\Convocatorias\Repository;

use App\Core\Support\Slugs;
use Doctrine\DBAL\Connection;

/**
 * Convocatorias de empleo, contratación y proveedores.
 *
 * El estado lo fija un funcionario, no el reloj: una convocatoria sigue
 * «abierta» hasta que alguien la cierra, aunque la fecha de cierre ya pasara.
 * Al ciudadano se le muestran ambas cosas —el estado y la fecha— para que
 * pueda reclamar si no coinciden.
 */
final readonly class ConvocatoriaRepository
{
    private const string VISIBLE = 'publicada = 1 AND eliminado_en IS NULL';

    public function __construct(private Connection $conexion)
    {
    }

    /**
     * Listado público. Las abiertas primero: es lo que el ciudadano viene a
     * buscar; el histórico va después, de la más reciente a la más vieja.
     *
     * @return list<array<string, mixed>>
     */
    public function publicadas(?string $tipo = null): array
    {
        $condicion = self::VISIBLE;
        $parametros = [];

        if ($tipo !== null) {
            $condicion .= ' AND tipo = ?';
            $parametros[] = $tipo;
        }

        return $this->conexion->fetchAllAssociative(
            "SELECT id, titulo, slug, tipo, estado, apertura, cierre, url_externa,
                    LEFT(descripcion, 300) AS extracto
               FROM convocatorias
              WHERE {$condicion}
              ORDER BY FIELD(estado, 'abierta', 'proxima', 'en_evaluacion', 'cerrada', 'adjudicada', 'desierta'),
                       COALESCE(cierre, apertura, creado_en) DESC",
            $parametros,
        );
    }

    /** @return ?array<string, mixed> */
    public function publicaPorSlug(string $slug): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM convocatorias WHERE slug = ? AND ' . self::VISIBLE,
            [$slug],
        );

        return $fila === false ? null : $fila;
    }

    /**
     * Documentos adjuntos publicados de una convocatoria: términos de
     * referencia, cronograma, resultados.
     *
     * @return list<array<string, mixed>>
     */
    public function documentosDe(int $convocatoriaId): array
    {
        return $this->conexion->fetchAllAssociative(
            "SELECT d.id, d.titulo, d.slug
               FROM convocatoria_documentos cd
               JOIN documentos d ON d.id = cd.documento_id
              WHERE cd.convocatoria_id = ?
                AND d.estado_aprobacion = 'publicado'
                AND d.eliminado_en IS NULL
              ORDER BY cd.orden, d.titulo",
            [$convocatoriaId],
        );
    }

    /** @return list<array<string, mixed>> */
    public function todas(): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT id, titulo, slug, tipo, estado, publicada, apertura, cierre
               FROM convocatorias
              WHERE eliminado_en IS NULL
              ORDER BY creado_en DESC',
        );
    }

    /** @return ?array<string, mixed> */
    public function porId(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM convocatorias WHERE id = ? AND eliminado_en IS NULL',
            [$id],
        );

        return $fila === false ? null : $fila;
    }

    /** @param array<string, mixed> $campos */
    public function crear(array $campos): int
    {
        $campos['slug'] = $this->slugLibre((string) $campos['titulo']);
        $this->conexion->insert('convocatorias', $campos);

        return (int) $this->conexion->lastInsertId();
    }

    /** @param array<string, mixed> $campos */
    public function actualizar(int $id, array $campos): void
    {
        $this->conexion->update('convocatorias', $campos, ['id' => $id]);
    }

    public function eliminar(int $id): void
    {
        $this->conexion->update('convocatorias', ['eliminado_en' => date('Y-m-d H:i:s')], ['id' => $id]);
    }

    private function slugLibre(string $titulo): string
    {
        $base = Slugs::desde($titulo, 300);
        $slug = $base;
        $sufijo = 2;

        while (false !== $this->conexion->fetchOne('SELECT id FROM convocatorias WHERE slug = ?', [$slug])) {
            $slug = $base . '-' . $sufijo++;
        }

        return $slug;
    }
}

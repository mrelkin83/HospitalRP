<?php

declare(strict_types=1);

namespace App\Modules\Eventos\Repository;

use App\Core\Support\Slugs;
use Doctrine\DBAL\Connection;

/**
 * Calendario institucional. Cubre los ítems 1.12 (calendario de actividades)
 * y 6.5 (calendario de participación ciudadana) del ITA.
 *
 * El calendario es de eventos INSTITUCIONALES: rendiciones de cuentas,
 * jornadas, capacitaciones. No agenda citas médicas —eso está fuera de v1—
 * ni contiene dato clínico de nadie.
 */
final readonly class EventoRepository
{
    private const string VISIBLE = 'publicado = 1 AND eliminado_en IS NULL';

    public function __construct(private Connection $conexion)
    {
    }

    /**
     * Eventos de un mes, para la vista de calendario.
     *
     * Incluye los que empiezan antes del mes pero terminan dentro: una
     * semana de la salud que cruza fin de mes debe verse en ambos meses.
     *
     * @return list<array<string, mixed>>
     */
    public function delMes(int $anio, int $mes): array
    {
        $desde = sprintf('%04d-%02d-01 00:00:00', $anio, $mes);
        $hasta = date('Y-m-t 23:59:59', (int) strtotime($desde));

        return $this->conexion->fetchAllAssociative(
            'SELECT id, titulo, slug, tipo, inicio, fin, todo_el_dia, lugar, url_externa
               FROM eventos
              WHERE ' . self::VISIBLE . '
                AND inicio <= ?
                AND COALESCE(fin, inicio) >= ?
              ORDER BY inicio',
            [$hasta, $desde],
        );
    }

    /**
     * Próximos eventos, para la portada y la página de participación.
     *
     * @return list<array<string, mixed>>
     */
    public function proximos(int $limite = 5): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT id, titulo, slug, tipo, inicio, fin, todo_el_dia, lugar, url_externa
               FROM eventos
              WHERE ' . self::VISIBLE . '
                AND COALESCE(fin, inicio) >= NOW()
              ORDER BY inicio
              LIMIT ' . max(1, min($limite, 50)),
        );
    }

    /** @return ?array<string, mixed> */
    public function publicoPorSlug(string $slug): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM eventos WHERE slug = ? AND ' . self::VISIBLE,
            [$slug],
        );

        return $fila === false ? null : $fila;
    }

    /** @return list<array<string, mixed>> */
    public function todos(): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT id, titulo, slug, tipo, inicio, fin, publicado
               FROM eventos WHERE eliminado_en IS NULL
              ORDER BY inicio DESC',
        );
    }

    /** @return ?array<string, mixed> */
    public function porId(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM eventos WHERE id = ? AND eliminado_en IS NULL',
            [$id],
        );

        return $fila === false ? null : $fila;
    }

    /** @param array<string, mixed> $campos */
    public function crear(array $campos): int
    {
        $campos['slug'] = $this->slugLibre((string) $campos['titulo']);
        $this->conexion->insert('eventos', $campos);

        return (int) $this->conexion->lastInsertId();
    }

    /** @param array<string, mixed> $campos */
    public function actualizar(int $id, array $campos): void
    {
        $this->conexion->update('eventos', $campos, ['id' => $id]);
    }

    public function eliminar(int $id): void
    {
        $this->conexion->update('eventos', ['eliminado_en' => date('Y-m-d H:i:s')], ['id' => $id]);
    }

    private function slugLibre(string $titulo): string
    {
        $base = Slugs::desde($titulo, 300);
        $slug = $base;
        $sufijo = 2;

        while (false !== $this->conexion->fetchOne('SELECT id FROM eventos WHERE slug = ?', [$slug])) {
            $slug = $base . '-' . $sufijo++;
        }

        return $slug;
    }
}

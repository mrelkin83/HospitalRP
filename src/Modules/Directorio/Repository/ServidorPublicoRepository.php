<?php

declare(strict_types=1);

namespace App\Modules\Directorio\Repository;

use App\Core\Support\Slugs;
use Doctrine\DBAL\Connection;

/**
 * Directorio de servidores públicos (ITA 1.6 y 1.6.1).
 *
 * Solo expone datos institucionales: cargo, dependencia, correo y teléfono de
 * oficina, escala salarial y enlace a la hoja de vida en el SIGEP. Ni esta
 * clase ni la tabla tienen por dónde devolver un dato personal, y esa es la
 * defensa: no se confía en que la vista recuerde ocultarlo.
 */
final readonly class ServidorPublicoRepository
{
    private const string VISIBLE = 'publicado = 1 AND eliminado_en IS NULL';

    public function __construct(private Connection $conexion)
    {
    }

    /**
     * Agrupado por dependencia, que es como lo busca el ciudadano: primero
     * ubica el área y después a la persona.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function publicadosPorDependencia(): array
    {
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT s.id, s.nombres, s.apellidos, s.slug, s.cargo, s.oficina,
                    s.tipo_vinculacion, s.formacion_academica,
                    s.correo_institucional, s.telefono_institucional,
                    s.url_hoja_vida, s.escala_salarial, s.url_escala_salarial,
                    -- ITA 3.5.b, 3.5.d y 3.5.j. El lugar de nacimiento va sin
                    -- la fecha a propósito: el criterio pide el lugar, y lugar
                    -- más fecha juntos reconstruyen un dato identificador.
                    s.pais_nacimiento, s.departamento_nacimiento, s.ciudad_nacimiento,
                    s.experiencia_laboral,
                    s.contrato_objeto, s.contrato_valor, s.contrato_desde, s.contrato_hasta,
                    COALESCE(d.nombre, ?) AS dependencia
               FROM servidores_publicos s
               LEFT JOIN dependencias d ON d.id = s.dependencia_id
              WHERE ' . self::VISIBLE . '
              ORDER BY dependencia, s.orden, s.apellidos, s.nombres',
            ['Sin dependencia asignada'],
        );

        $agrupado = [];

        foreach ($filas as $fila) {
            $agrupado[(string) $fila['dependencia']][] = $fila;
        }

        return $agrupado;
    }

    public function totalPublicados(): int
    {
        return (int) $this->conexion->fetchOne(
            'SELECT COUNT(*) FROM servidores_publicos WHERE ' . self::VISIBLE,
        );
    }

    /** @return list<array<string, mixed>> */
    public function todos(): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT id, nombres, apellidos, cargo, tipo_vinculacion, publicado
               FROM servidores_publicos WHERE eliminado_en IS NULL
              ORDER BY apellidos, nombres',
        );
    }

    /** @return ?array<string, mixed> */
    public function porId(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM servidores_publicos WHERE id = ? AND eliminado_en IS NULL',
            [$id],
        );

        return $fila === false ? null : $fila;
    }

    /** @param array<string, mixed> $campos */
    public function crear(array $campos): int
    {
        $campos['slug'] = $this->slugLibre(
            trim((string) ($campos['nombres'] ?? '') . ' ' . (string) ($campos['apellidos'] ?? '')),
        );
        $this->conexion->insert('servidores_publicos', $campos);

        return (int) $this->conexion->lastInsertId();
    }

    /** @param array<string, mixed> $campos */
    public function actualizar(int $id, array $campos): void
    {
        $this->conexion->update('servidores_publicos', $campos, ['id' => $id]);
    }

    public function eliminar(int $id): void
    {
        $this->conexion->update(
            'servidores_publicos',
            ['eliminado_en' => date('Y-m-d H:i:s')],
            ['id' => $id],
        );
    }

    private function slugLibre(string $nombre): string
    {
        $base = Slugs::desde($nombre !== '' ? $nombre : 'servidor', 240);
        $slug = $base;
        $sufijo = 2;

        while (false !== $this->conexion->fetchOne('SELECT id FROM servidores_publicos WHERE slug = ?', [$slug])) {
            $slug = $base . '-' . $sufijo++;
        }

        return $slug;
    }
}

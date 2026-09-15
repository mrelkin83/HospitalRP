<?php

declare(strict_types=1);

namespace App\Modules\Indicadores\Repository;

use App\Core\Support\Slugs;
use Doctrine\DBAL\Connection;

/**
 * Indicadores estadísticos de la entidad.
 *
 * Cada fila es un indicador en un periodo. El público ve el ÚLTIMO periodo
 * publicado de cada indicador, más su serie para comparar; el panel ve todo.
 *
 * Ninguna consulta pública devuelve filas sin publicar: una cifra a medio
 * cargar es peor que ninguna cifra.
 */
final readonly class IndicadorRepository
{
    private const string VISIBLE = 'publicado = 1 AND eliminado_en IS NULL';

    /** Los tres grupos que pidió el hospital, más un cajón para lo demás. */
    public const array GRUPOS = [
        'contratacion' => 'Así va la contratación',
        'ocupacion' => 'Ocupación hospitalaria',
        'vitales' => 'Estadísticas vitales',
        'otro' => 'Otros indicadores',
    ];

    public function __construct(private Connection $conexion)
    {
    }

    /**
     * Último valor publicado de cada indicador de un grupo.
     *
     * Se resuelve con una subconsulta por el periodo máximo de cada slug: si
     * el hospital carga marzo, marzo manda, y si aún no cargó marzo se sigue
     * viendo febrero en lugar de un hueco.
     *
     * @return list<array<string, mixed>>
     */
    public function ultimosPorGrupo(string $grupo): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT i.* FROM indicadores i
               JOIN (
                    SELECT slug, MAX(periodo) AS periodo
                      FROM indicadores
                     WHERE ' . self::VISIBLE . ' AND grupo = ?
                     GROUP BY slug
               ) u ON u.slug = i.slug AND u.periodo = i.periodo
              WHERE ' . self::VISIBLE . ' AND i.grupo = ?
              ORDER BY i.orden, i.nombre',
            [$grupo, $grupo],
        );
    }

    /**
     * Indicadores marcados para la portada, con su último periodo publicado.
     *
     * El héroe tiene sitio para pocos, y el límite es duro a propósito: si el
     * hospital marca diez, se muestran los primeros por `orden` en vez de
     * romper la maqueta.
     *
     * @return list<array<string, mixed>>
     */
    public function destacados(int $limite = 2): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT i.* FROM indicadores i
               JOIN (
                    SELECT slug, MAX(periodo) AS periodo
                      FROM indicadores
                     WHERE ' . self::VISIBLE . ' AND destacado = 1
                     GROUP BY slug
               ) u ON u.slug = i.slug AND u.periodo = i.periodo
              WHERE ' . self::VISIBLE . ' AND i.destacado = 1
              ORDER BY i.orden, i.nombre
              LIMIT ' . max(1, min($limite, 6)),
        );
    }

    /** @return list<string> grupos que tienen algo publicado */
    public function gruposConDatos(): array
    {
        return array_map(
            static fn (mixed $g): string => (string) $g,
            $this->conexion->fetchFirstColumn(
                'SELECT DISTINCT grupo FROM indicadores WHERE ' . self::VISIBLE,
            ),
        );
    }

    /**
     * Serie histórica de un indicador, la más antigua primero, para dibujar
     * la evolución.
     *
     * @return list<array{periodo: string, valor: float}>
     */
    public function serie(string $slug, int $limite = 12): array
    {
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT periodo, valor FROM indicadores
              WHERE ' . self::VISIBLE . ' AND slug = ?
              ORDER BY periodo DESC
              LIMIT ' . max(2, min($limite, 60)),
            [$slug],
        );

        return array_map(
            static fn (array $f): array => ['periodo' => (string) $f['periodo'], 'valor' => (float) $f['valor']],
            array_reverse($filas),
        );
    }

    /** @return list<array<string, mixed>> */
    public function todos(): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT * FROM indicadores WHERE eliminado_en IS NULL
              ORDER BY grupo, orden, nombre, periodo DESC',
        );
    }

    /** @return ?array<string, mixed> */
    public function porId(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM indicadores WHERE id = ? AND eliminado_en IS NULL',
            [$id],
        );

        return $fila === false ? null : $fila;
    }

    /**
     * Crea o actualiza la lectura de un indicador en un periodo.
     *
     * Es un «upsert» a propósito: cargar dos veces el dato de marzo tiene que
     * corregirlo, no reventar contra la clave única ni duplicar la cifra.
     *
     * @param array<string, mixed> $campos
     */
    public function guardar(array $campos): int
    {
        $campos['slug'] = Slugs::desde((string) $campos['nombre'], 200);

        $existente = $this->conexion->fetchOne(
            'SELECT id FROM indicadores WHERE slug = ? AND periodo = ?',
            [$campos['slug'], $campos['periodo']],
        );

        if ($existente !== false) {
            $this->conexion->update('indicadores', $campos, ['id' => (int) $existente]);

            return (int) $existente;
        }

        $this->conexion->insert('indicadores', $campos);

        return (int) $this->conexion->lastInsertId();
    }

    /**
     * Actualiza una lectura existente por id.
     *
     * Separado de `guardar()` a propósito: al editar manda el id, no el par
     * (slug, periodo). Si no fuera así, corregir el nombre de un indicador
     * crearía una fila nueva y dejaría la vieja publicada al lado.
     *
     * @param array<string, mixed> $campos
     */
    public function actualizar(int $id, array $campos): void
    {
        $campos['slug'] = Slugs::desde((string) $campos['nombre'], 200);

        $this->conexion->update('indicadores', $campos, ['id' => $id]);
    }

    public function eliminar(int $id): void
    {
        $this->conexion->update('indicadores', ['eliminado_en' => date('Y-m-d H:i:s')], ['id' => $id]);
    }
}

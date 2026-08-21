<?php

declare(strict_types=1);

namespace App\Modules\Transparencia\Repository;

use Doctrine\DBAL\Connection;

/**
 * Menú de transparencia: secciones del Anexo 2 con sus ítems. El «vistazo»
 * del administrador —qué obligatorias están vacías— sale de aquí.
 */
final readonly class TransparenciaRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    /**
     * Árbol completo: secciones principales con sus subsecciones e ítems, y
     * el conteo de ítems publicados por sección.
     *
     * @return list<array<string, mixed>>
     */
    public function arbol(bool $soloPublico = false): array
    {
        $secciones = $this->conexion->fetchAllAssociative(
            'SELECT s.*, dep.nombre AS dependencia_nombre,
                    (SELECT COUNT(*) FROM transparencia_items i
                      WHERE i.seccion_id = s.id AND i.publicado = 1) AS items_publicados
               FROM transparencia_secciones s
               LEFT JOIN dependencias dep ON dep.id = s.dependencia_id
              ORDER BY s.padre_id IS NOT NULL, s.orden, s.codigo',
        );

        $items = [];

        foreach ($this->conexion->fetchAllAssociative(
            'SELECT i.*, d.slug AS documento_slug, d.titulo AS documento_titulo
               FROM transparencia_items i
               LEFT JOIN documentos d ON d.id = i.documento_id
              ' . ($soloPublico ? 'WHERE i.publicado = 1' : '') . '
              ORDER BY i.orden, i.id',
        ) as $item) {
            $items[(int) $item['seccion_id']][] = $item;
        }

        $porId = [];
        $raices = [];

        foreach ($secciones as $seccion) {
            if ($soloPublico && ((int) $seccion['aplica']) !== 1) {
                continue;
            }

            $seccion['items'] = $items[(int) $seccion['id']] ?? [];
            $seccion['hijas'] = [];
            $porId[(int) $seccion['id']] = $seccion;
        }

        foreach ($porId as $id => $seccion) {
            $padreId = $seccion['padre_id'] !== null ? (int) $seccion['padre_id'] : null;

            if ($padreId !== null && isset($porId[$padreId])) {
                $porId[$padreId]['hijas'][] = &$porId[$id];
            } else {
                $raices[] = &$porId[$id];
            }
        }

        return $raices;
    }

    /**
     * Secciones obligatorias, que aplican, sin ningún ítem publicado (ni en
     * sus hijas): lo que el administrador tiene que ver de un vistazo.
     *
     * @return list<array{codigo: string, nombre: string}>
     */
    public function obligatoriasVacias(): array
    {
        /** @var list<array{codigo: string, nombre: string}> $filas */
        $filas = $this->conexion->fetchAllAssociative(
            "SELECT s.codigo, s.nombre
               FROM transparencia_secciones s
              WHERE s.obligatoria = 1 AND s.aplica = 1
                AND NOT EXISTS (SELECT 1 FROM transparencia_items i
                                 WHERE i.seccion_id = s.id AND i.publicado = 1)
                AND NOT EXISTS (SELECT 1 FROM transparencia_secciones h
                                  JOIN transparencia_items ih ON ih.seccion_id = h.id AND ih.publicado = 1
                                 WHERE h.padre_id = s.id)
              ORDER BY s.codigo",
        );

        return array_map(static fn (array $f): array => [
            'codigo' => (string) $f['codigo'],
            'nombre' => (string) $f['nombre'],
        ], $filas);
    }

    /**
     * @return ?array<string, mixed>
     */
    public function seccionPorId(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative('SELECT * FROM transparencia_secciones WHERE id = ?', [$id]);

        return $fila === false ? null : $fila;
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function actualizarSeccion(int $id, array $campos): void
    {
        $this->conexion->update('transparencia_secciones', $campos, ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function crearItem(array $campos): int
    {
        $this->conexion->insert('transparencia_items', $campos);

        return (int) $this->conexion->lastInsertId();
    }

    /**
     * @return ?array<string, mixed>
     */
    public function itemPorId(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative('SELECT * FROM transparencia_items WHERE id = ?', [$id]);

        return $fila === false ? null : $fila;
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function actualizarItem(int $id, array $campos): void
    {
        $this->conexion->update('transparencia_items', $campos, ['id' => $id]);
    }

    public function eliminarItem(int $id): void
    {
        $this->conexion->delete('transparencia_items', ['id' => $id]);
    }

    public function marcarActualizada(int $seccionId): void
    {
        $this->conexion->executeStatement(
            'UPDATE transparencia_secciones SET ultima_actualizacion = CURDATE() WHERE id = ?',
            [$seccionId],
        );
    }
}

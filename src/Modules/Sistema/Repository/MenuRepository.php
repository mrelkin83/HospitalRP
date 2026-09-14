<?php

declare(strict_types=1);

namespace App\Modules\Sistema\Repository;

use Doctrine\DBAL\Connection;

/**
 * Menús de navegación editables (Fase 1, paso 7). El sitio público lee por
 * ubicación; el panel edita etiquetas, direcciones, orden y visibilidad.
 */
final readonly class MenuRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    /**
     * Items visibles de una ubicación, listos para pintar la navegación.
     *
     * @return list<array{etiqueta: string, url: string, nueva_pestana: bool}>
     */
    public function itemsPorUbicacion(string $ubicacion): array
    {
        /** @var list<array{etiqueta: string, url: string, destino: string}> $filas */
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT i.etiqueta, i.url, i.destino
               FROM menu_items i
               JOIN menus m ON m.id = i.menu_id
              WHERE m.ubicacion = ? AND i.visible = 1 AND i.padre_id IS NULL
              ORDER BY i.orden, i.id',
            [$ubicacion],
        );

        return array_map(static fn (array $f): array => [
            'etiqueta' => $f['etiqueta'],
            'url' => $f['url'],
            'nueva_pestana' => $f['destino'] === 'nueva',
        ], $filas);
    }

    /**
     * @return list<array{id: int, nombre: string, slug: string, ubicacion: string, protegido: bool}>
     */
    public function menus(): array
    {
        /** @var list<array<string, mixed>> $filas */
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT id, nombre, slug, ubicacion, protegido FROM menus ORDER BY id',
        );

        return array_map(static fn (array $f): array => [
            'id' => (int) $f['id'],
            'nombre' => (string) $f['nombre'],
            'slug' => (string) $f['slug'],
            'ubicacion' => (string) $f['ubicacion'],
            'protegido' => (bool) $f['protegido'],
        ], $filas);
    }

    /**
     * @return list<array{id: int, etiqueta: string, url: string, orden: int, visible: bool, protegido: bool}>
     */
    public function itemsDeMenu(int $menuId): array
    {
        /** @var list<array<string, mixed>> $filas */
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT id, etiqueta, url, orden, visible, protegido
               FROM menu_items WHERE menu_id = ? ORDER BY orden, id',
            [$menuId],
        );

        return array_map(static fn (array $f): array => [
            'id' => (int) $f['id'],
            'etiqueta' => (string) $f['etiqueta'],
            'url' => (string) $f['url'],
            'orden' => (int) $f['orden'],
            'visible' => (bool) $f['visible'],
            'protegido' => (bool) $f['protegido'],
        ], $filas);
    }

    /**
     * Todos los menús con sus ítems visibles, hijos incluidos. Lo consume el
     * mapa del sitio: se genera desde los menús vigentes para que añadir un
     * ítem en el panel lo incluya sin tocar código.
     *
     * @return list<array{nombre: string, items: list<array{etiqueta: string, url: string, nivel: int}>}>
     */
    public function arbolPublico(): array
    {
        /** @var list<array<string, mixed>> $filas */
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT m.id AS menu_id, m.nombre AS menu, i.id, i.padre_id, i.etiqueta, i.url
               FROM menus m
               JOIN menu_items i ON i.menu_id = m.id
              WHERE i.visible = 1
              ORDER BY m.id, i.orden, i.id',
        );

        $porMenu = [];
        $hijosDe = [];

        foreach ($filas as $fila) {
            $menuId = (int) $fila['menu_id'];
            $porMenu[$menuId] = (string) $fila['menu'];
            $hijosDe[$menuId][(int) ($fila['padre_id'] ?? 0)][] = [
                'id' => (int) $fila['id'],
                'etiqueta' => (string) $fila['etiqueta'],
                'url' => (string) $fila['url'],
            ];
        }

        $resultado = [];

        foreach ($porMenu as $menuId => $nombre) {
            $resultado[] = [
                'nombre' => $nombre,
                'items' => $this->aplanar($hijosDe[$menuId], 0, 0),
            ];
        }

        return $resultado;
    }

    /**
     * @param array<int, list<array{id: int, etiqueta: string, url: string}>> $hijosDe
     *
     * @return list<array{etiqueta: string, url: string, nivel: int}>
     */
    private function aplanar(array $hijosDe, int $padreId, int $nivel): array
    {
        // Tope de profundidad: corta un padre_id cíclico en vez de agotar la
        // pila. Tres niveles es lo más hondo que llega un menú del sitio.
        if ($nivel > 3) {
            return [];
        }

        $plano = [];

        foreach ($hijosDe[$padreId] ?? [] as $item) {
            $plano[] = ['etiqueta' => $item['etiqueta'], 'url' => $item['url'], 'nivel' => $nivel];
            $plano = array_merge($plano, $this->aplanar($hijosDe, $item['id'], $nivel + 1));
        }

        return $plano;
    }

    /**
     * @return ?array{id: int, menu_id: int, etiqueta: string, protegido: bool}
     */
    public function itemPorId(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT id, menu_id, etiqueta, protegido FROM menu_items WHERE id = ?',
            [$id],
        );

        if ($fila === false) {
            return null;
        }

        return [
            'id' => (int) $fila['id'],
            'menu_id' => (int) $fila['menu_id'],
            'etiqueta' => (string) $fila['etiqueta'],
            'protegido' => (bool) $fila['protegido'],
        ];
    }

    public function actualizarItem(int $id, string $etiqueta, string $url, int $orden, bool $visible): void
    {
        $this->conexion->update('menu_items', [
            'etiqueta' => $etiqueta,
            'url' => $url,
            'orden' => $orden,
            'visible' => $visible ? 1 : 0,
        ], ['id' => $id]);
    }

    public function crearItem(int $menuId, string $etiqueta, string $url, int $orden): int
    {
        $this->conexion->insert('menu_items', [
            'menu_id' => $menuId,
            'etiqueta' => $etiqueta,
            'url' => $url,
            'orden' => $orden,
        ]);

        return (int) $this->conexion->lastInsertId();
    }

    /**
     * Los items protegidos (obligatorios por la Res. 1519) no se pueden
     * borrar, solo ocultar; devuelve false si el item era protegido.
     */
    public function eliminarItem(int $id): bool
    {
        $afectadas = $this->conexion->executeStatement(
            'DELETE FROM menu_items WHERE id = ? AND protegido = 0',
            [$id],
        );

        return $afectadas > 0;
    }
}

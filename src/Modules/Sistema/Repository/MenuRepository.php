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
        /** @var list<array{id: int, padre_id: ?int, etiqueta: string, url: string, destino: string}> $filas */
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT i.id, i.padre_id, i.etiqueta, i.descripcion, i.icono, i.url, i.destino
               FROM menu_items i
               JOIN menus m ON m.id = i.menu_id
              WHERE m.ubicacion = ? AND i.visible = 1
              ORDER BY i.orden, i.id',
            [$ubicacion],
        );

        // Dos niveles: raíces y sus hijos. El panel ya permitía anidar —48 de
        // los 79 ítems sembrados tienen padre— pero la consulta descartaba los
        // hijos con `padre_id IS NULL` y el sitio enseñaba siete enlaces
        // sueltos. El menú del hospital estaba a medias sin que se notara.
        $hijos = [];

        foreach ($filas as $fila) {
            if ($fila['padre_id'] !== null) {
                $hijos[(int) $fila['padre_id']][] = self::formatear($fila);
            }
        }

        $items = [];

        foreach ($filas as $fila) {
            if ($fila['padre_id'] !== null) {
                continue;
            }

            $item = self::formatear($fila);
            // Un hijo huérfano —su padre oculto o borrado— no se cuela por
            // libre: solo se sirven los que cuelgan de una raíz visible.
            $item['hijos'] = $hijos[(int) $fila['id']] ?? [];
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $fila
     *
     * @return array{etiqueta: string, url: string, nueva_pestana: bool, hijos: list<array<string, mixed>>}
     */
    private static function formatear(array $fila): array
    {
        return [
            'etiqueta' => (string) $fila['etiqueta'],
            // Las tarjetas de accesos rápidos llevan una frase y un icono; un
            // enlace de menú normal los deja vacíos y no pinta nada de más.
            'descripcion' => $fila['descripcion'] === null ? '' : (string) $fila['descripcion'],
            'icono' => $fila['icono'] === null ? '' : (string) $fila['icono'],
            'url' => (string) $fila['url'],
            'nueva_pestana' => $fila['destino'] === 'nueva',
            'hijos' => [],
        ];
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
            'SELECT id, padre_id, etiqueta, descripcion, icono, url, orden, visible, protegido
               FROM menu_items WHERE menu_id = ? ORDER BY orden, id',
            [$menuId],
        );

        $items = array_map(static fn (array $f): array => [
            'id' => (int) $f['id'],
            'padre_id' => $f['padre_id'] === null ? null : (int) $f['padre_id'],
            'etiqueta' => (string) $f['etiqueta'],
            'descripcion' => $f['descripcion'] === null ? '' : (string) $f['descripcion'],
            'icono' => $f['icono'] === null ? '' : (string) $f['icono'],
            'url' => (string) $f['url'],
            'orden' => (int) $f['orden'],
            'visible' => (bool) $f['visible'],
            'protegido' => (bool) $f['protegido'],
        ], $filas);

        // Ordenados como se ven en el sitio: cada raíz seguida de sus hijos.
        // Antes salían los 79 en plano y no había forma de saber que
        // «Historia» cuelga de «El Hospital» ni de mover un enlace de sitio.
        $hijos = [];

        foreach ($items as $item) {
            if ($item['padre_id'] !== null) {
                $hijos[$item['padre_id']][] = $item;
            }
        }

        $arbol = [];

        foreach ($items as $item) {
            if ($item['padre_id'] !== null) {
                continue;
            }

            $item['es_hijo'] = false;
            $item['tiene_hijos'] = isset($hijos[$item['id']]);
            $arbol[] = $item;

            foreach ($hijos[$item['id']] ?? [] as $hijo) {
                $hijo['es_hijo'] = true;
                $hijo['tiene_hijos'] = false;
                $arbol[] = $hijo;
            }
        }

        return $arbol;
    }

    /**
     * Raíces de un menú, para el selector de «depende de» del panel.
     *
     * Solo raíces a propósito: el sitio pinta dos niveles, y ofrecer como
     * padre a un enlace que ya es hijo crearía un tercer nivel que nadie
     * llegaría a ver.
     *
     * @return list<array{id: int, etiqueta: string}>
     */
    public function raicesDeMenu(int $menuId): array
    {
        /** @var list<array<string, mixed>> $filas */
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT id, etiqueta FROM menu_items
              WHERE menu_id = ? AND padre_id IS NULL ORDER BY orden, id',
            [$menuId],
        );

        return array_map(static fn (array $f): array => [
            'id' => (int) $f['id'],
            'etiqueta' => (string) $f['etiqueta'],
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

    public function actualizarItem(
        int $id,
        string $etiqueta,
        string $url,
        int $orden,
        bool $visible,
        ?int $padreId = null,
        ?string $descripcion = null,
        ?string $icono = null,
    ): void {
        // Un enlace no puede colgar de sí mismo ni de uno de sus propios
        // hijos: sería un ciclo, y el menú dejaría de pintarse entero.
        if ($padreId === $id || ($padreId !== null && $this->esDescendiente($padreId, $id))) {
            $padreId = null;
        }

        $this->conexion->update('menu_items', [
            'etiqueta' => $etiqueta,
            'url' => $url,
            'orden' => $orden,
            'visible' => $visible ? 1 : 0,
            'padre_id' => $padreId,
            'descripcion' => $descripcion === '' ? null : $descripcion,
            'icono' => $icono === '' ? null : $icono,
        ], ['id' => $id]);
    }

    private function esDescendiente(int $posibleHijo, int $posiblePadre): bool
    {
        $padre = $this->conexion->fetchOne('SELECT padre_id FROM menu_items WHERE id = ?', [$posibleHijo]);

        return $padre !== false && $padre !== null && (int) $padre === $posiblePadre;
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

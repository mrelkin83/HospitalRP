<?php

declare(strict_types=1);

namespace App\Modules\Directorio\Entity;

final readonly class Especialidad
{
    /**
     * @param list<int> $sedeIds Sedes donde se presta (para el filtro público)
     */
    public function __construct(
        public int $id,
        public string $nombre,
        public string $slug,
        public ?string $descripcion,
        public ?int $imagenId,
        public int $orden,
        public bool $activa,
        public array $sedeIds = [],
    ) {
    }

    /**
     * @param array<string, mixed> $fila
     * @param list<int> $sedeIds
     */
    public static function desdeFila(array $fila, array $sedeIds = []): self
    {
        return new self(
            id: (int) $fila['id'],
            nombre: (string) $fila['nombre'],
            slug: (string) $fila['slug'],
            descripcion: isset($fila['descripcion']) ? (string) $fila['descripcion'] : null,
            imagenId: isset($fila['imagen_id']) ? (int) $fila['imagen_id'] : null,
            orden: (int) $fila['orden'],
            activa: (bool) $fila['activa'],
            sedeIds: $sedeIds,
        );
    }
}

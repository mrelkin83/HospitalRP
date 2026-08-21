<?php

declare(strict_types=1);

namespace App\Modules\Directorio\Entity;

final readonly class Servicio
{
    /**
     * @param list<int> $sedeIds
     */
    public function __construct(
        public int $id,
        public string $nombre,
        public string $slug,
        public ?string $descripcion,
        public ?string $requisitos,
        public ?string $horario,
        public ?string $telefono,
        public ?int $imagenId,
        public int $orden,
        public bool $activo,
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
            requisitos: isset($fila['requisitos']) ? (string) $fila['requisitos'] : null,
            horario: isset($fila['horario']) ? (string) $fila['horario'] : null,
            telefono: isset($fila['telefono']) ? (string) $fila['telefono'] : null,
            imagenId: isset($fila['imagen_id']) ? (int) $fila['imagen_id'] : null,
            orden: (int) $fila['orden'],
            activo: (bool) $fila['activo'],
            sedeIds: $sedeIds,
        );
    }
}

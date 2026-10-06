<?php

declare(strict_types=1);

namespace App\Modules\Directorio\Entity;

final readonly class Servicio
{
    /**
     * @param list<array{cifra: string, texto: string}> $destacados
     * @param list<int>                                 $sedeIds
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
        public array $destacados = [],
        public array $sedeIds = [],
    ) {
    }

    /**
     * @param array<string, mixed> $fila
     * @param list<int>            $sedeIds
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
            destacados: self::destacadosDesde($fila['destacados'] ?? null),
            sedeIds: $sedeIds,
        );
    }

    /**
     * Las cifras del portafolio (21 consultorios, 269 camas…) llegan como JSON
     * de la columna `destacados`. Un JSON corrupto o de otra forma se descarta
     * entero en lugar de reventar la página: el servicio se sigue mostrando
     * sin sus cifras, que es degradar, no fallar.
     *
     * @return list<array{cifra: string, texto: string}>
     */
    private static function destacadosDesde(mixed $valor): array
    {
        if (!is_string($valor) || trim($valor) === '') {
            return [];
        }

        try {
            $crudos = json_decode($valor, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!is_array($crudos)) {
            return [];
        }

        $destacados = [];

        foreach ($crudos as $crudo) {
            if (!is_array($crudo) || !isset($crudo['cifra'], $crudo['texto'])) {
                continue;
            }

            $destacados[] = [
                'cifra' => (string) $crudo['cifra'],
                'texto' => (string) $crudo['texto'],
            ];
        }

        return $destacados;
    }
}

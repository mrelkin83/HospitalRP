<?php

declare(strict_types=1);

namespace App\Modules\Directorio\Entity;

/**
 * Sede física del hospital. `horario` es una lista de líneas legibles
 * («Lunes a viernes: 7:00 a. m. – 5:00 p. m.») guardada como JSON.
 */
final readonly class Sede
{
    /**
     * @param list<string> $horario
     */
    public function __construct(
        public int $id,
        public string $nombre,
        public string $slug,
        public ?string $descripcion,
        public string $direccion,
        public string $municipio,
        public string $departamento,
        public ?string $telefono,
        public ?string $correo,
        public array $horario,
        public ?string $latitud,
        public ?string $longitud,
        public bool $tieneUrgencias,
        public ?int $imagenId,
        public int $orden,
        public bool $activa,
    ) {
    }

    /**
     * @param array<string, mixed> $fila
     */
    public static function desdeFila(array $fila): self
    {
        $horario = [];

        if (isset($fila['horario']) && is_string($fila['horario'])) {
            $decodificado = json_decode($fila['horario'], true);

            if (is_array($decodificado)) {
                $horario = array_values(array_filter($decodificado, is_string(...)));
            }
        }

        return new self(
            id: (int) $fila['id'],
            nombre: (string) $fila['nombre'],
            slug: (string) $fila['slug'],
            descripcion: isset($fila['descripcion']) ? (string) $fila['descripcion'] : null,
            direccion: (string) $fila['direccion'],
            municipio: (string) $fila['municipio'],
            departamento: (string) $fila['departamento'],
            telefono: isset($fila['telefono']) ? (string) $fila['telefono'] : null,
            correo: isset($fila['correo']) ? (string) $fila['correo'] : null,
            horario: $horario,
            latitud: isset($fila['latitud']) ? (string) $fila['latitud'] : null,
            longitud: isset($fila['longitud']) ? (string) $fila['longitud'] : null,
            tieneUrgencias: (bool) $fila['tiene_urgencias'],
            imagenId: isset($fila['imagen_id']) ? (int) $fila['imagen_id'] : null,
            orden: (int) $fila['orden'],
            activa: (bool) $fila['activa'],
        );
    }

    public function enlaceMapa(): ?string
    {
        if ($this->latitud === null || $this->longitud === null) {
            return null;
        }

        return sprintf('https://www.google.com/maps?q=%s,%s', $this->latitud, $this->longitud);
    }
}

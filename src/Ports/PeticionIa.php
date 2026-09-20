<?php

declare(strict_types=1);

namespace App\Ports;

final readonly class PeticionIa
{
    /**
     * @param list<ImagenIa> $imagenes Varias imágenes en una sola petición
     *                                 (las páginas de una minuta escaneada).
     *                                 `$imagen` se conserva por los usos de
     *                                 una sola imagen ya existentes.
     */
    public function __construct(
        public string $instrucciones,
        public string $mensaje,
        public ?ImagenIa $imagen = null,
        public int $maxTokens = 1024,
        public array $imagenes = [],
    ) {
    }

    /** @return list<ImagenIa> Todas las imágenes de la petición. */
    public function todasLasImagenes(): array
    {
        return $this->imagen !== null ? [$this->imagen, ...$this->imagenes] : $this->imagenes;
    }
}

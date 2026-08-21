<?php

declare(strict_types=1);

namespace App\Ports;

final readonly class PeticionIa
{
    public function __construct(
        public string $instrucciones,
        public string $mensaje,
        public ?ImagenIa $imagen = null,
        public int $maxTokens = 1024,
    ) {
    }
}

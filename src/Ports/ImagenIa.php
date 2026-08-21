<?php

declare(strict_types=1);

namespace App\Ports;

final readonly class ImagenIa
{
    public function __construct(
        public string $mime,
        public string $base64,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Ports;

final readonly class RespuestaIa
{
    public function __construct(
        public string $texto,
        public int $tokensEntrada,
        public int $tokensSalida,
    ) {
    }
}

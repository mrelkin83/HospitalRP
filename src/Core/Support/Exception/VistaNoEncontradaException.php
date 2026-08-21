<?php

declare(strict_types=1);

namespace App\Core\Support\Exception;

use RuntimeException;

final class VistaNoEncontradaException extends RuntimeException
{
    public static function para(string $plantilla, string $rutaBuscada): self
    {
        return new self(sprintf(
            'La plantilla «%s» no existe (se buscó en %s).',
            $plantilla,
            $rutaBuscada,
        ));
    }
}

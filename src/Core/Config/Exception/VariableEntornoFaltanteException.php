<?php

declare(strict_types=1);

namespace App\Core\Config\Exception;

use RuntimeException;

/**
 * Falta una variable de entorno obligatoria. Se lanza al arrancar, nunca a
 * mitad de una petición: es preferible que la aplicación no levante a que
 * levante con la base de datos equivocada.
 */
final class VariableEntornoFaltanteException extends RuntimeException
{
    public static function para(string $clave): self
    {
        return new self(sprintf(
            'Falta la variable de entorno obligatoria "%s". Revisa tu archivo .env; '
            . 'usa .env.example como referencia.',
            $clave,
        ));
    }
}

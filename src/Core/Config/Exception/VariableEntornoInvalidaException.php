<?php

declare(strict_types=1);

namespace App\Core\Config\Exception;

use RuntimeException;

/**
 * Una variable de entorno existe pero no tiene el tipo o el valor esperado.
 */
final class VariableEntornoInvalidaException extends RuntimeException
{
    public static function noEsEntero(string $clave, string $valor): self
    {
        return new self(sprintf(
            'La variable de entorno "%s" debe ser un número entero, se recibió "%s".',
            $clave,
            $valor,
        ));
    }

    public static function noEsBooleano(string $clave, string $valor): self
    {
        return new self(sprintf(
            'La variable de entorno "%s" debe ser booleana (true, false, 1, 0, on, off), '
            . 'se recibió "%s".',
            $clave,
            $valor,
        ));
    }

    /**
     * @param list<string> $permitidos
     */
    public static function fueraDeLista(string $clave, string $valor, array $permitidos): self
    {
        return new self(sprintf(
            'La variable de entorno "%s" debe ser uno de [%s], se recibió "%s".',
            $clave,
            implode(', ', $permitidos),
            $valor,
        ));
    }
}

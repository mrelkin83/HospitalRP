<?php

declare(strict_types=1);

namespace App\Http\Exception;

use LogicException;

/**
 * Una ruta apunta a un controlador que no existe, no está registrado en el
 * contenedor o no devuelve una Response. Siempre es un error de programación,
 * nunca del usuario: por eso es LogicException y no RuntimeException.
 */
final class ControladorInvalidoException extends LogicException
{
    public static function referenciaMalFormada(string $ruta): self
    {
        return new self(sprintf(
            'La ruta "%s" no declara un controlador válido. Se espera '
            . '_controlador => [Clase::class, \'metodo\'].',
            $ruta,
        ));
    }

    public static function noRegistrado(string $clase): self
    {
        return new self(sprintf(
            'El controlador "%s" no está registrado en el contenedor de servicios. '
            . 'Regístralo en config/servicios.php.',
            $clase,
        ));
    }

    public static function metodoInexistente(string $clase, string $metodo): self
    {
        return new self(sprintf('El controlador "%s" no tiene el método "%s".', $clase, $metodo));
    }

    public static function noDevolvioRespuesta(string $clase, string $metodo, string $tipo): self
    {
        return new self(sprintf(
            '%s::%s() debe devolver una Response, devolvió %s.',
            $clase,
            $metodo,
            $tipo,
        ));
    }
}

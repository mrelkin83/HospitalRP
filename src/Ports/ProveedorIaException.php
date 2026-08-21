<?php

declare(strict_types=1);

namespace App\Ports;

use RuntimeException;

final class ProveedorIaException extends RuntimeException
{
    public static function porHttp(string $proveedor, int $estado, string $detalle): self
    {
        return new self(sprintf(
            'El proveedor %s respondió %d: %s',
            $proveedor,
            $estado,
            mb_substr($detalle, 0, 300),
        ));
    }

    public static function porRed(string $proveedor, string $detalle): self
    {
        return new self(sprintf('No se pudo contactar a %s: %s', $proveedor, $detalle));
    }

    public static function porRespuestaInvalida(string $proveedor): self
    {
        return new self(sprintf('%s devolvió una respuesta que no se pudo interpretar.', $proveedor));
    }
}

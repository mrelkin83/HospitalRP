<?php

declare(strict_types=1);

namespace App\Modules\Sistema\Exception;

use RuntimeException;

final class IaDesactivadaException extends RuntimeException
{
    public static function porConfiguracion(): self
    {
        return new self('La IA del panel está desactivada o el proveedor configurado no tiene clave de API.');
    }
}

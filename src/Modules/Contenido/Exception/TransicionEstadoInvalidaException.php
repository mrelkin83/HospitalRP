<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Exception;

use App\Modules\Contenido\Entity\EstadoAprobacion;
use DomainException;

final class TransicionEstadoInvalidaException extends DomainException
{
    public static function entre(EstadoAprobacion $desde, EstadoAprobacion $hacia): self
    {
        return new self(sprintf(
            'No se puede pasar de «%s» a «%s»: el flujo es borrador → en revisión → aprobado → publicado.',
            $desde->etiqueta(),
            $hacia->etiqueta(),
        ));
    }
}

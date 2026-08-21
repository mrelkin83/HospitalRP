<?php

declare(strict_types=1);

namespace App\Modules\Documentos\Exception;

use DomainException;

/**
 * Criterio de aceptación de la Fase 3: un documento sin resumen, dependencia
 * o fecha no se puede publicar. El mensaje se muestra tal cual al funcionario.
 */
final class PublicacionIncompletaException extends DomainException
{
    /**
     * @param non-empty-list<string> $faltantes
     */
    public static function porFaltantes(array $faltantes): self
    {
        return new self(sprintf(
            'Para publicar el documento falta: %s. Complétalo y vuelve a intentar.',
            implode(', ', $faltantes),
        ));
    }
}

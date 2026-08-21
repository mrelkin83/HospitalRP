<?php

declare(strict_types=1);

namespace App\Modules\Documentos\Entity;

/**
 * Estado normativo del documento. Es independiente de `publicado` (visible o
 * no en el sitio): un derogado sigue publicado —con su advertencia— porque
 * los actos administrativos citados no desaparecen, se derogan.
 */
enum EstadoDocumento: string
{
    case Borrador = 'borrador';
    case Vigente = 'vigente';
    case Derogado = 'derogado';
    case Historico = 'historico';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Vigente => 'Vigente',
            self::Derogado => 'Derogado',
            self::Historico => 'Histórico',
        };
    }
}

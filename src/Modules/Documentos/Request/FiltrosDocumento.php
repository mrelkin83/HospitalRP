<?php

declare(strict_types=1);

namespace App\Modules\Documentos\Request;

use App\Modules\Documentos\Entity\EstadoDocumento;
use Symfony\Component\HttpFoundation\Request;

/**
 * Filtros del buscador documental público. Por defecto solo documentos
 * vigentes: los derogados e históricos hay que pedirlos a propósito
 * (criterio de aceptación de la Fase 3).
 */
final readonly class FiltrosDocumento
{
    public function __construct(
        public string $q,
        public ?int $dependenciaId,
        public ?int $categoriaId,
        public ?int $anio,
        public EstadoDocumento $estado,
        public int $pagina,
    ) {
    }

    public static function desdePeticion(Request $peticion): self
    {
        $q = $peticion->query;

        $estado = EstadoDocumento::tryFrom($q->getString('estado')) ?? EstadoDocumento::Vigente;

        // Los borradores jamás se listan en público, pida lo que pida la URL.
        if ($estado === EstadoDocumento::Borrador) {
            $estado = EstadoDocumento::Vigente;
        }

        return new self(
            q: mb_substr(trim($q->getString('q')), 0, 200),
            dependenciaId: $q->getInt('dependencia') > 0 ? $q->getInt('dependencia') : null,
            categoriaId: $q->getInt('categoria') > 0 ? $q->getInt('categoria') : null,
            anio: $q->getInt('anio') >= 1900 && $q->getInt('anio') <= 2100 ? $q->getInt('anio') : null,
            estado: $estado,
            pagina: max(1, $q->getInt('pagina', 1)),
        );
    }
}

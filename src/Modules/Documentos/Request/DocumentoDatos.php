<?php

declare(strict_types=1);

namespace App\Modules\Documentos\Request;

use Symfony\Component\HttpFoundation\Request;

/**
 * Metadatos del formulario de documento.
 */
final readonly class DocumentoDatos
{
    public function __construct(
        public string $titulo,
        public string $resumen,
        public ?int $categoriaId,
        public ?int $dependenciaId,
        public ?string $numeroActo,
        public string $fechaDocumento,
        public ?string $vigenciaDesde,
        public ?string $vigenciaHasta,
        public ?int $reemplazaAId,
        public ?string $serieDocumental,
        public ?string $subserieDocumental,
        public ?int $tiempoRetencion,
        public string $etiquetas,
    ) {
    }

    public static function desdePeticion(Request $peticion): self
    {
        $r = $peticion->request;
        $texto = static fn (string $clave): ?string => trim($r->getString($clave)) !== '' ? trim($r->getString($clave)) : null;

        return new self(
            titulo: trim($r->getString('titulo')),
            resumen: trim($r->getString('resumen')),
            categoriaId: $r->getString('categoria_id') !== '' ? $r->getInt('categoria_id') : null,
            dependenciaId: $r->getString('dependencia_id') !== '' ? $r->getInt('dependencia_id') : null,
            numeroActo: $texto('numero_acto'),
            fechaDocumento: trim($r->getString('fecha_documento')),
            vigenciaDesde: $texto('vigencia_desde'),
            vigenciaHasta: $texto('vigencia_hasta'),
            reemplazaAId: $r->getString('reemplaza_a_id') !== '' ? $r->getInt('reemplaza_a_id') : null,
            serieDocumental: $texto('serie_documental'),
            subserieDocumental: $texto('subserie_documental'),
            tiempoRetencion: $r->getString('tiempo_retencion') !== '' ? $r->getInt('tiempo_retencion') : null,
            etiquetas: trim($r->getString('etiquetas')),
        );
    }

    /**
     * @return array<string, string>
     */
    public function validar(): array
    {
        $errores = [];

        if ($this->titulo === '') {
            $errores['titulo'] = 'Escribe el título del documento.';
        } elseif (mb_strlen($this->titulo) > 300) {
            $errores['titulo'] = 'El título no puede pasar de 300 caracteres.';
        }

        if ($this->resumen === '') {
            $errores['resumen'] = 'Escribe un resumen: es lo que le dice al ciudadano de qué trata sin abrir el PDF.';
        } elseif (mb_strlen($this->resumen) > 1000) {
            $errores['resumen'] = 'El resumen no puede pasar de 1000 caracteres.';
        }

        if ($this->fechaDocumento === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->fechaDocumento) !== 1) {
            $errores['fecha_documento'] = 'Indica la fecha del documento.';
        }

        if ($this->vigenciaDesde !== null && $this->vigenciaHasta !== null
            && $this->vigenciaHasta < $this->vigenciaDesde
        ) {
            $errores['vigencia_hasta'] = 'La vigencia no puede terminar antes de empezar.';
        }

        return $errores;
    }

    /**
     * @return list<string> Etiquetas separadas por coma, limpias y sin duplicados
     */
    public function etiquetasComoLista(): array
    {
        $lista = array_filter(array_map(trim(...), explode(',', $this->etiquetas)));

        return array_values(array_unique(array_map(
            static fn (string $t): string => mb_substr($t, 0, 80),
            $lista,
        )));
    }
}

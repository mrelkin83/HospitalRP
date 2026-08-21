<?php

declare(strict_types=1);

namespace App\Modules\Documentos\Entity;

/**
 * Un documento es un registro con metadatos, no un archivo suelto
 * (CLAUDE.md §6). El PDF vigente es `archivoId`; el historial vive en
 * `documento_versiones`.
 */
final readonly class Documento
{
    /**
     * @param list<string> $etiquetas
     */
    public function __construct(
        public int $id,
        public string $titulo,
        public string $slug,
        public string $resumen,
        public int $archivoId,
        public ?int $categoriaId,
        public ?string $categoriaNombre,
        public ?int $dependenciaId,
        public ?string $dependenciaNombre,
        public ?string $numeroActo,
        public string $fechaDocumento,
        public ?string $vigenciaDesde,
        public ?string $vigenciaHasta,
        public int $version,
        public EstadoDocumento $estado,
        public ?int $reemplazaAId,
        public ?string $serieDocumental,
        public ?string $subserieDocumental,
        public ?int $tiempoRetencion,
        public bool $publicado,
        public int $descargas,
        public array $etiquetas = [],
    ) {
    }

    /**
     * @param array<string, mixed> $fila
     * @param list<string> $etiquetas
     */
    public static function desdeFila(array $fila, array $etiquetas = []): self
    {
        return new self(
            id: (int) $fila['id'],
            titulo: (string) $fila['titulo'],
            slug: (string) $fila['slug'],
            resumen: (string) $fila['resumen'],
            archivoId: (int) $fila['archivo_id'],
            categoriaId: isset($fila['categoria_id']) ? (int) $fila['categoria_id'] : null,
            categoriaNombre: isset($fila['categoria_nombre']) ? (string) $fila['categoria_nombre'] : null,
            dependenciaId: isset($fila['dependencia_id']) ? (int) $fila['dependencia_id'] : null,
            dependenciaNombre: isset($fila['dependencia_nombre']) ? (string) $fila['dependencia_nombre'] : null,
            numeroActo: isset($fila['numero_acto']) ? (string) $fila['numero_acto'] : null,
            fechaDocumento: (string) $fila['fecha_documento'],
            vigenciaDesde: isset($fila['vigencia_desde']) ? (string) $fila['vigencia_desde'] : null,
            vigenciaHasta: isset($fila['vigencia_hasta']) ? (string) $fila['vigencia_hasta'] : null,
            version: (int) $fila['version'],
            estado: EstadoDocumento::from((string) $fila['estado']),
            reemplazaAId: isset($fila['reemplaza_a_id']) ? (int) $fila['reemplaza_a_id'] : null,
            serieDocumental: isset($fila['serie_documental']) ? (string) $fila['serie_documental'] : null,
            subserieDocumental: isset($fila['subserie_documental']) ? (string) $fila['subserie_documental'] : null,
            tiempoRetencion: isset($fila['tiempo_retencion']) ? (int) $fila['tiempo_retencion'] : null,
            publicado: (bool) $fila['publicado'],
            descargas: (int) $fila['descargas'],
            etiquetas: $etiquetas,
        );
    }

    /**
     * URL pública de la ficha: /documentos/{id}-{slug}.
     */
    public function rutaPublica(): string
    {
        return sprintf('/documentos/%d-%s', $this->id, $this->slug);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Entity;

/**
 * Página institucional publicable por ruta dinámica.
 */
final readonly class Pagina
{
    public function __construct(
        public int $id,
        public string $ruta,
        public string $titulo,
        public ?string $resumen,
        public string $contenido,
        public ?int $imagenId,
        public ?string $metaTitulo,
        public ?string $metaDescripcion,
        public bool $noindex,
        public ?string $actualizadoEn,
    ) {
    }

    /**
     * @param array<string, mixed> $fila
     */
    public static function desdeFila(array $fila): self
    {
        return new self(
            id: (int) $fila['id'],
            ruta: (string) $fila['ruta'],
            titulo: (string) $fila['titulo'],
            resumen: isset($fila['resumen']) ? (string) $fila['resumen'] : null,
            contenido: (string) ($fila['contenido'] ?? ''),
            imagenId: isset($fila['imagen_id']) ? (int) $fila['imagen_id'] : null,
            metaTitulo: isset($fila['meta_titulo']) ? (string) $fila['meta_titulo'] : null,
            metaDescripcion: isset($fila['meta_descripcion']) ? (string) $fila['meta_descripcion'] : null,
            noindex: (bool) $fila['noindex'],
            actualizadoEn: isset($fila['actualizado_en']) ? (string) $fila['actualizado_en'] : null,
        );
    }
}

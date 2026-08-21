<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Entity;

/**
 * Página institucional. `ruta` es la URL pública completa («/institucional/
 * quienes-somos»); `protegida` marca las que la normativa exige y no se
 * pueden eliminar.
 */
final readonly class Pagina
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $ruta,
        public string $titulo,
        public ?string $resumen,
        public string $contenido,
        public ?int $imagenId,
        public ?string $metaTitulo,
        public ?string $metaDescripcion,
        public bool $noindex,
        public EstadoAprobacion $estado,
        public ?string $publicarEn,
        public ?string $despublicarEn,
        public bool $protegida,
        public ?string $creadoEn,
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
            slug: (string) $fila['slug'],
            ruta: (string) $fila['ruta'],
            titulo: (string) $fila['titulo'],
            resumen: isset($fila['resumen']) ? (string) $fila['resumen'] : null,
            contenido: (string) ($fila['contenido'] ?? ''),
            imagenId: isset($fila['imagen_id']) ? (int) $fila['imagen_id'] : null,
            metaTitulo: isset($fila['meta_titulo']) ? (string) $fila['meta_titulo'] : null,
            metaDescripcion: isset($fila['meta_descripcion']) ? (string) $fila['meta_descripcion'] : null,
            noindex: (bool) $fila['noindex'],
            estado: EstadoAprobacion::from((string) $fila['estado_aprobacion']),
            publicarEn: isset($fila['publicar_en']) ? (string) $fila['publicar_en'] : null,
            despublicarEn: isset($fila['despublicar_en']) ? (string) $fila['despublicar_en'] : null,
            protegida: (bool) $fila['protegida'],
            creadoEn: isset($fila['creado_en']) ? (string) $fila['creado_en'] : null,
            actualizadoEn: isset($fila['actualizado_en']) ? (string) $fila['actualizado_en'] : null,
        );
    }

    /**
     * Campos restaurables. Ni el estado ni la ruta van al snapshot: restaurar
     * recupera el contenido sin revivir publicaciones ni romper URLs.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'titulo' => $this->titulo,
            'resumen' => $this->resumen,
            'contenido' => $this->contenido,
            'imagen_id' => $this->imagenId,
            'meta_titulo' => $this->metaTitulo,
            'meta_descripcion' => $this->metaDescripcion,
            'noindex' => $this->noindex,
        ];
    }
}

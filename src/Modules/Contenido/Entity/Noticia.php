<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Entity;

/**
 * Proyección completa de la tabla `noticias` para el panel y el público.
 */
final readonly class Noticia
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $titulo,
        public string $resumen,
        public string $contenido,
        public ?int $imagenId,
        public ?int $categoriaId,
        public bool $destacada,
        public ?string $metaTitulo,
        public ?string $metaDescripcion,
        public EstadoAprobacion $estado,
        public ?string $publicarEn,
        public ?string $despublicarEn,
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
            titulo: (string) $fila['titulo'],
            resumen: (string) $fila['resumen'],
            contenido: (string) $fila['contenido'],
            imagenId: isset($fila['imagen_id']) ? (int) $fila['imagen_id'] : null,
            categoriaId: isset($fila['categoria_id']) ? (int) $fila['categoria_id'] : null,
            destacada: (bool) $fila['destacada'],
            metaTitulo: isset($fila['meta_titulo']) ? (string) $fila['meta_titulo'] : null,
            metaDescripcion: isset($fila['meta_descripcion']) ? (string) $fila['meta_descripcion'] : null,
            estado: EstadoAprobacion::from((string) $fila['estado_aprobacion']),
            publicarEn: isset($fila['publicar_en']) ? (string) $fila['publicar_en'] : null,
            despublicarEn: isset($fila['despublicar_en']) ? (string) $fila['despublicar_en'] : null,
            creadoEn: isset($fila['creado_en']) ? (string) $fila['creado_en'] : null,
            actualizadoEn: isset($fila['actualizado_en']) ? (string) $fila['actualizado_en'] : null,
        );
    }

    /**
     * Los campos que viajan al snapshot de `contenido_versiones`. El estado
     * de aprobación NO va: restaurar una versión recupera el contenido, no
     * revive una publicación.
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
            'categoria_id' => $this->categoriaId,
            'destacada' => $this->destacada,
            'meta_titulo' => $this->metaTitulo,
            'meta_descripcion' => $this->metaDescripcion,
        ];
    }
}

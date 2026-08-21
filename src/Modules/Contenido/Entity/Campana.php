<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Entity;

/**
 * Campaña de salud (vacunación, prevención…). `inicio`/`fin` son la vigencia
 * informativa de la campaña, no una ventana de publicación.
 */
final readonly class Campana
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $titulo,
        public ?string $resumen,
        public string $contenido,
        public ?int $imagenId,
        public ?string $poblacionObjetivo,
        public ?string $inicio,
        public ?string $fin,
        public EstadoAprobacion $estado,
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
            resumen: isset($fila['resumen']) ? (string) $fila['resumen'] : null,
            contenido: (string) ($fila['contenido'] ?? ''),
            imagenId: isset($fila['imagen_id']) ? (int) $fila['imagen_id'] : null,
            poblacionObjetivo: isset($fila['poblacion_objetivo']) ? (string) $fila['poblacion_objetivo'] : null,
            inicio: isset($fila['inicio']) ? (string) $fila['inicio'] : null,
            fin: isset($fila['fin']) ? (string) $fila['fin'] : null,
            estado: EstadoAprobacion::from((string) $fila['estado_aprobacion']),
            creadoEn: isset($fila['creado_en']) ? (string) $fila['creado_en'] : null,
            actualizadoEn: isset($fila['actualizado_en']) ? (string) $fila['actualizado_en'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'titulo' => $this->titulo,
            'resumen' => $this->resumen,
            'contenido' => $this->contenido,
            'imagen_id' => $this->imagenId,
            'poblacion_objetivo' => $this->poblacionObjetivo,
            'inicio' => $this->inicio,
            'fin' => $this->fin,
        ];
    }
}

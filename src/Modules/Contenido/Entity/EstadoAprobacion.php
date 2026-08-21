<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Entity;

/**
 * Flujo de aprobación de todo contenido publicable (CLAUDE.md §6):
 * borrador → en_revision → aprobado → publicado.
 *
 * Los retrocesos permitidos devuelven SIEMPRE a borrador: una pieza
 * rechazada o despublicada vuelve al inicio del flujo, no a un estado
 * intermedio que nadie recuerda quién aprobó.
 */
enum EstadoAprobacion: string
{
    case Borrador = 'borrador';
    case EnRevision = 'en_revision';
    case Aprobado = 'aprobado';
    case Publicado = 'publicado';

    public function puedeCambiarA(self $destino): bool
    {
        return in_array($destino, match ($this) {
            self::Borrador => [self::EnRevision],
            self::EnRevision => [self::Aprobado, self::Borrador],
            self::Aprobado => [self::Publicado, self::Borrador],
            self::Publicado => [self::Borrador],
        }, true);
    }

    /**
     * Solo lo publicado es candidato a verse en el sitio; la ventana
     * publicar_en/despublicar_en se evalúa aparte, en la consulta.
     */
    public function esPublico(): bool
    {
        return $this === self::Publicado;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::EnRevision => 'En revisión',
            self::Aprobado => 'Aprobado',
            self::Publicado => 'Publicado',
        };
    }
}

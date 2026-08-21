<?php

declare(strict_types=1);

namespace App\Modules\Directorio\Entity;

/**
 * Profesional del directorio médico. Publica datos personales: la ficha
 * pública solo existe con `autorizaPublicacion` y `activo`, y JAMÁS lleva
 * teléfonos ni correos personales (Ley 1581, esquema §3).
 */
final readonly class Profesional
{
    /**
     * @param list<int> $especialidadIds
     * @param list<string> $especialidadNombres
     */
    public function __construct(
        public int $id,
        public string $nombre,
        public string $slug,
        public ?string $registroRethus,
        public ?string $perfil,
        public ?int $fotoId,
        public bool $autorizaPublicacion,
        public int $orden,
        public bool $activo,
        public array $especialidadIds = [],
        public array $especialidadNombres = [],
    ) {
    }

    /**
     * @param array<string, mixed> $fila
     * @param list<int> $especialidadIds
     * @param list<string> $especialidadNombres
     */
    public static function desdeFila(array $fila, array $especialidadIds = [], array $especialidadNombres = []): self
    {
        return new self(
            id: (int) $fila['id'],
            nombre: (string) $fila['nombre'],
            slug: (string) $fila['slug'],
            registroRethus: isset($fila['registro_rethus']) ? (string) $fila['registro_rethus'] : null,
            perfil: isset($fila['perfil']) ? (string) $fila['perfil'] : null,
            fotoId: isset($fila['foto_id']) ? (int) $fila['foto_id'] : null,
            autorizaPublicacion: (bool) $fila['autoriza_publicacion'],
            orden: (int) $fila['orden'],
            activo: (bool) $fila['activo'],
            especialidadIds: $especialidadIds,
            especialidadNombres: $especialidadNombres,
        );
    }
}

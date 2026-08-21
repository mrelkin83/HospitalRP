<?php

declare(strict_types=1);

namespace App\Core\Storage;

/**
 * Registro de la tabla `archivos`: metadatos de un archivo almacenado fuera
 * del document root. `ruta` es relativa al directorio base del almacén.
 */
final readonly class Archivo
{
    public function __construct(
        public int $id,
        public string $nombreOriginal,
        public string $ruta,
        public string $mime,
        public string $extension,
        public int $tamano,
        public string $hashSha256,
        public ?int $ancho,
        public ?int $alto,
        public ?string $textoAlt,
        public bool $publico,
    ) {
    }

    /**
     * @param array<string, mixed> $fila
     */
    public static function desdeFila(array $fila): self
    {
        return new self(
            id: (int) $fila['id'],
            nombreOriginal: (string) $fila['nombre_original'],
            ruta: (string) $fila['ruta'],
            mime: (string) $fila['mime'],
            extension: (string) $fila['extension'],
            tamano: (int) $fila['tamano'],
            hashSha256: (string) $fila['hash_sha256'],
            ancho: isset($fila['ancho']) ? (int) $fila['ancho'] : null,
            alto: isset($fila['alto']) ? (int) $fila['alto'] : null,
            textoAlt: isset($fila['texto_alt']) ? (string) $fila['texto_alt'] : null,
            publico: (bool) $fila['publico'],
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Core\Storage;

use RuntimeException;

/**
 * La subida se rechaza antes de tocar disco definitivo. Los mensajes están
 * pensados para mostrarse tal cual a un funcionario en el panel.
 */
final class ArchivoInvalidoException extends RuntimeException
{
    public static function vacio(): self
    {
        return new self('El archivo llegó vacío o no se subió completo. Intenta de nuevo.');
    }

    public static function demasiadoGrande(int $bytes, int $maximo): self
    {
        return new self(sprintf(
            'El archivo pesa %.1f MB y el máximo permitido es %d MB.',
            $bytes / 1_048_576,
            intdiv($maximo, 1_048_576),
        ));
    }

    public static function tipoNoPermitido(string $mime): self
    {
        return new self(sprintf(
            'El tipo de archivo no está permitido (se detectó «%s»). Se aceptan imágenes JPG, PNG, WebP y AVIF, y documentos PDF.',
            $mime,
        ));
    }

    public static function extensionEnganosa(string $extension, string $mime): self
    {
        return new self(sprintf(
            'La extensión «.%s» no corresponde al contenido real del archivo («%s»). Renombra el archivo con su extensión verdadera.',
            $extension,
            $mime,
        ));
    }
}

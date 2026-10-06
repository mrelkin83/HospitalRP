<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Service;

/**
 * Encoge las fotos que suben los contratistas antes de guardarlas.
 *
 * Cada cuenta de cobro trae una evidencia por obligación, y las
 * obligaciones son catorce. Con 1.500 contratistas radicando cada mes, las
 * fotos de 2 MB que salen de un celular son treinta gigas mensuales de
 * pantallazos que nadie va a mirar a tamaño completo: se ven en una
 * pantalla y se anexan a un PDF.
 *
 * Reducir a 1600 px de lado largo y JPEG 80 las deja en ~250 KB sin que el
 * supervisor pierda un solo dato legible. Los PDF no se tocan.
 */
final readonly class CompresorImagen
{
    private const int LADO_MAXIMO = 1600;
    private const int CALIDAD = 80;

    public function disponible(): bool
    {
        return extension_loaded('gd');
    }

    /**
     * Devuelve el JPEG comprimido, o null si no hay nada que hacer (no es
     * una imagen, ya es pequeña, o GD no está).
     *
     * Nunca lanza: una foto que GD no sepa leer se guarda tal cual. Que el
     * contratista no pueda radicar porque su cámara guarda un JPEG raro
     * sería un fallo mucho peor que un archivo grande.
     */
    public function comprimir(string $rutaAbsoluta, string $mime): ?string
    {
        if (!$this->disponible() || !in_array($mime, ['image/jpeg', 'image/png'], true)) {
            return null;
        }

        $imagen = @imagecreatefromstring((string) file_get_contents($rutaAbsoluta));

        if ($imagen === false) {
            return null;
        }

        try {
            $ancho = imagesx($imagen);
            $alto = imagesy($imagen);
            $lado = max($ancho, $alto);

            if ($lado > self::LADO_MAXIMO) {
                $escala = self::LADO_MAXIMO / $lado;
                $redimensionada = imagescale($imagen, (int) round($ancho * $escala), (int) round($alto * $escala));

                if ($redimensionada !== false) {
                    imagedestroy($imagen);
                    $imagen = $redimensionada;
                }
            }

            // Un PNG con transparencia se aplana sobre blanco: el JPEG no
            // la soporta y el negro por defecto convierte un pantallazo en
            // un rectángulo ilegible.
            $plana = imagecreatetruecolor(imagesx($imagen), imagesy($imagen));

            if ($plana === false) {
                return null;
            }

            imagefill($plana, 0, 0, (int) imagecolorallocate($plana, 255, 255, 255));
            imagecopy($plana, $imagen, 0, 0, 0, 0, imagesx($imagen), imagesy($imagen));

            ob_start();
            imagejpeg($plana, null, self::CALIDAD);
            $jpeg = (string) ob_get_clean();
            imagedestroy($plana);

            // Si el «comprimido» pesa más que el original (pasa con
            // pantallazos pequeños de pocos colores), se descarta.
            return $jpeg !== '' && strlen($jpeg) < (int) filesize($rutaAbsoluta) ? $jpeg : null;
        } finally {
            if (is_object($imagen)) {
                imagedestroy($imagen);
            }
        }
    }
}

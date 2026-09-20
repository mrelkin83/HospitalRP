<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Archivos del módulo de cuentas de cobro: firmas, cédulas, RUT, planillas
 * PILA y los PDF generados.
 *
 * Viven FUERA del document root (storage/cuentas/) y NO en la mediateca
 * `archivos` de la plataforma: aquella es la biblioteca pública del sitio y
 * esto son datos personales que solo se sirven a través de un controlador
 * que verifica permisos (§3 del documento del módulo).
 *
 * Reglas §3: máximo 10 MB, solo PDF/JPG/PNG, tipo validado por contenido
 * (finfo), nombre aleatorio no adivinable.
 */
final readonly class ArchivosCuentas
{
    private const int TAMANO_MAXIMO = 10 * 1024 * 1024;

    private const array TIPOS_PERMITIDOS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public function __construct(private string $directorioBase)
    {
    }

    /**
     * Guarda un archivo subido y devuelve su ruta relativa (la que se
     * escribe en las columnas *_archivo de las tablas cc_).
     *
     * @throws \InvalidArgumentException con mensaje apto para mostrar.
     */
    public function guardar(UploadedFile $subido, string $subcarpeta): string
    {
        $tamano = $subido->getSize();

        if (!is_int($tamano) || $tamano <= 0) {
            throw new \InvalidArgumentException('El archivo llegó vacío. Inténtelo de nuevo.');
        }

        if ($tamano > self::TAMANO_MAXIMO) {
            throw new \InvalidArgumentException('El archivo pesa más de 10 MB. Tómele una foto más liviana o comprímalo.');
        }

        // El tipo real sale del contenido (finfo), no del nombre.
        $mime = (string) $subido->getMimeType();

        if (!isset(self::TIPOS_PERMITIDOS[$mime])) {
            throw new \InvalidArgumentException('Solo se aceptan archivos PDF o fotos JPG/PNG.');
        }

        return $this->escribir(
            file_get_contents($subido->getPathname()) ?: '',
            self::TIPOS_PERMITIDOS[$mime],
            $subcarpeta,
        );
    }

    /**
     * Guarda una firma dibujada en pantalla (dataURL de canvas, PNG).
     */
    public function guardarFirmaDibujada(string $dataUrl, string $subcarpeta): string
    {
        if (preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m) !== 1) {
            throw new \InvalidArgumentException('La firma dibujada no se recibió bien. Inténtelo de nuevo.');
        }

        $binario = base64_decode($m[1], true);

        if ($binario === false || strlen($binario) < 100 || strlen($binario) > self::TAMANO_MAXIMO) {
            throw new \InvalidArgumentException('La firma dibujada no se recibió bien. Inténtelo de nuevo.');
        }

        // Confirmación por contenido: debe ser un PNG de verdad.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        if ($finfo->buffer($binario) !== 'image/png') {
            throw new \InvalidArgumentException('La firma dibujada no se recibió bien. Inténtelo de nuevo.');
        }

        return $this->escribir($binario, 'png', $subcarpeta);
    }

    /** Guarda contenido ya generado por el sistema (los PDF). */
    public function guardarGenerado(string $contenido, string $extension, string $subcarpeta): string
    {
        return $this->escribir($contenido, $extension, $subcarpeta);
    }

    public function rutaAbsoluta(string $rutaRelativa): string
    {
        // La ruta viene de la base de datos, pero un traspaso de directorio
        // no debe poder salir de storage/cuentas ni con datos corruptos.
        if (str_contains($rutaRelativa, '..') || str_starts_with($rutaRelativa, '/')) {
            throw new \InvalidArgumentException('Ruta de archivo inválida.');
        }

        return $this->directorioBase . '/' . $rutaRelativa;
    }

    public function existe(string $rutaRelativa): bool
    {
        return is_file($this->rutaAbsoluta($rutaRelativa));
    }

    private function escribir(string $contenido, string $extension, string $subcarpeta): string
    {
        if (preg_match('/^[a-z0-9\-]+$/', $subcarpeta) !== 1) {
            throw new \InvalidArgumentException('Subcarpeta inválida.');
        }

        $rutaRelativa = sprintf('%s/%s/%s.%s', $subcarpeta, date('Y'), bin2hex(random_bytes(20)), $extension);
        $destino = $this->directorioBase . '/' . dirname($rutaRelativa);

        if (!is_dir($destino) && !mkdir($destino, 0755, true) && !is_dir($destino)) {
            throw new \RuntimeException('No se pudo preparar el directorio de archivos.');
        }

        if (file_put_contents($this->directorioBase . '/' . $rutaRelativa, $contenido) === false) {
            throw new \RuntimeException('No se pudo guardar el archivo.');
        }

        return $rutaRelativa;
    }
}

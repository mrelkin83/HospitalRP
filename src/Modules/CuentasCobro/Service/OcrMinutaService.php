<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Service;

/**
 * Respaldo OCR de la extracción (§10 paso 5): cuando no hay proveedor de
 * IA, Tesseract lee las páginas 2 a 4 (`-l spa --psm 4`) y unas
 * `ParserTextoMinuta` rescata lo que el OCR rescata bien: la forma de
 * pago, el plazo de la cláusula 5 y las obligaciones numeradas. Ese
 * parser es el mismo que lee la capa de texto de los PDF exportados de
 * Word: una sola batería de expresiones para las dos fuentes.
 *
 * El recuadro de la página 1 no sale bien por OCR (tabla escaneada) y se
 * deja al revisor, como advierte el documento del módulo.
 */
final readonly class OcrMinutaService
{
    public function __construct(
        private string $rutaTesseract,
        private string $rutaTessdata,
        private ArchivosCuentas $archivos,
    ) {
    }

    public function disponible(): bool
    {
        return is_file($this->rutaTesseract);
    }

    /**
     * @param list<string> $imagenes Rutas relativas de las páginas 150 dpi
     *                               (ia-1.png…); se leen la 2, 3 y 4.
     *
     * @return array<string, mixed> Resultado parcial con el esquema de §10.
     */
    public function extraer(array $imagenes): array
    {
        if (!$this->disponible()) {
            throw new \RuntimeException('Tesseract no está instalado; configure OCR_TESSERACT en el .env.');
        }

        $texto = '';

        foreach (array_slice($imagenes, 1, 3) as $ruta) {
            $texto .= "\n" . $this->ocr($this->archivos->rutaAbsoluta($ruta));
        }

        return ['proveedor' => 'ocr'] + ParserTextoMinuta::interpretar($texto);
    }

    private function ocr(string $imagenAbsoluta): string
    {
        $comando = [$this->rutaTesseract, $imagenAbsoluta, 'stdout', '-l', 'spa', '--psm', '4'];
        $entorno = $this->rutaTessdata !== '' ? ['TESSDATA_PREFIX' => $this->rutaTessdata] + getenv() : null;
        $proceso = proc_open($comando, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tuberias, null, $entorno);

        if (!is_resource($proceso)) {
            throw new \RuntimeException('No se pudo iniciar Tesseract.');
        }

        $salida = (string) stream_get_contents($tuberias[1]);
        $errores = (string) stream_get_contents($tuberias[2]);
        fclose($tuberias[1]);
        fclose($tuberias[2]);

        if (proc_close($proceso) !== 0) {
            throw new \RuntimeException('Tesseract falló: ' . mb_substr(trim($errores), 0, 300));
        }

        return $salida;
    }
}

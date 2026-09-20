<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Service;

/**
 * Respaldo OCR de la extracción (§10 paso 5): cuando no hay proveedor de
 * IA, Tesseract lee las páginas 2 a 4 (`-l spa --psm 4`) y unas
 * expresiones regulares rescatan lo que el OCR rescata bien: la forma de
 * pago, el plazo de la cláusula 5 y las obligaciones numeradas.
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

        return self::interpretar($texto);
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

    /** @return array<string, mixed> */
    private static function interpretar(string $texto): array
    {
        // Para localizar cláusulas se aplana TODO el espacio: los títulos
        // del escáner cruzan renglones («PLAZO DE\nEJECUCIÓN»). Para
        // separar obligaciones numeradas se trabaja con más tolerancia.
        $plano = trim((string) preg_replace('/\s+/', ' ', $texto));
        $resultado = ['proveedor' => 'ocr'];

        // FORMA DE PAGO: de la cláusula 3 (o su título) a la cláusula 4.
        if (preg_match('/FORMA DE PAGO\s*[:.]?\s*(.{40,3000}?)(?:CL[AÁ]USULA\s*(?:CUARTA|4)|DISPONIBILIDAD PRESUPUESTAL)/su', $plano, $m) === 1) {
            $resultado['forma_pago_texto'] = trim($m[1]);
        }

        // PLAZO DE EJECUCIÓN (cláusula 5): «…será CINCO (5) MESES, …».
        if (preg_match('/PLAZO DE EJECUCI[OÓ]N.{0,120}?ser[aá]\s+(?:de\s+)?(.{3,80}?)\s*[,.]/su', $plano, $m) === 1) {
            $resultado['plazo_clausula_5'] = trim($m[1]);
        }

        // OBLIGACIONES ESPECÍFICAS: los ítems numerados hasta el bloque
        // del SGIC (o el fin de lo leído).
        if (preg_match('/OBLIGACIONES ESPEC[IÍ]FICAS\s*[:.]?(.+?)(?:SISTEMA DE GESTI[OÓ]N INTEGRAL|OBLIGACIONES FRENTE AL|CL[AÁ]USULA\s*(?:OCTAVA|8)|$)/su', $plano, $m) === 1) {
            preg_match_all('/(?:^|\s)(\d{1,2})[.)]\s+(.{15,700}?)(?=\s\d{1,2}[.)]\s|\z)/su', $m[1], $items, PREG_SET_ORDER);
            $obligaciones = [];

            foreach ($items as $item) {
                $limpia = trim((string) preg_replace('/\s+/', ' ', $item[2]));

                if (mb_strlen($limpia) >= 15) {
                    $obligaciones[] = $limpia;
                }
            }

            if ($obligaciones !== []) {
                $resultado['obligaciones_especificas'] = $obligaciones;
            }
        }

        return $resultado;
    }
}

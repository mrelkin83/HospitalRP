<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Service;

/**
 * Lee la capa de texto de un PDF con `pdftotext` (Poppler).
 *
 * Las minutas nacen en Word. Cuando el PDF que llega es la EXPORTACIÓN de
 * ese Word —y no el escaneo del impreso— trae el texto exacto dentro, y
 * gastar cinco imágenes de IA para leerlo sería pagar por adivinar algo
 * que está escrito. Esta vía cuesta cero y no puede alucinar.
 *
 * El hospital no garantiza tener el Word de los 1.500 contratos, así que
 * esto es un atajo oportunista: si el PDF no trae texto (escaneo puro), se
 * devuelve vacío y la cola sigue por IA/OCR sin ruido.
 */
final readonly class TextoPdfMinutaService
{
    /** Menos de esto es un escaneo con basura suelta, no un documento. */
    private const int MINIMO_CARACTERES = 800;

    public function __construct(
        private string $rutaPdftotext,
        private ArchivosCuentas $archivos,
    ) {
    }

    public function disponible(): bool
    {
        return is_file($this->rutaPdftotext);
    }

    /**
     * El texto de las primeras páginas, o cadena vacía si no hay capa de
     * texto aprovechable.
     */
    public function texto(string $pdfRelativo, int $hastaPagina = 6): string
    {
        if (!$this->disponible()) {
            return '';
        }

        $comando = [
            $this->rutaPdftotext,
            '-f', '1',
            '-l', (string) max(1, $hastaPagina),
            '-layout',
            '-enc', 'UTF-8',
            $this->archivos->rutaAbsoluta($pdfRelativo),
            '-',
        ];

        $proceso = proc_open($comando, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tuberias);

        if (!is_resource($proceso)) {
            return '';
        }

        $salida = (string) stream_get_contents($tuberias[1]);
        fclose($tuberias[1]);
        fclose($tuberias[2]);

        if (proc_close($proceso) !== 0) {
            return '';
        }

        // Un PDF escaneado igual devuelve algo: numeritos del pie, la
        // marca del escáner. El umbral separa eso de un documento real.
        return mb_strlen(trim($salida)) >= self::MINIMO_CARACTERES ? $salida : '';
    }
}

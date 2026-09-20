<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Service;

/**
 * Convierte la minuta PDF escaneada en imágenes PNG (§10 del documento):
 * las páginas 1 a 5 a 150 dpi para el proveedor de IA, y todas a 100 dpi
 * para el visor de la pantalla de revisión.
 *
 * Usa `pdftoppm` (Poppler) como binario externo, con la ruta configurable
 * por variable de entorno: en este equipo vive en C:\laragon\bin\poppler.
 * Se invoca con arreglo de argumentos (sin shell): un nombre de archivo
 * malicioso no puede convertirse en un comando.
 */
final readonly class ConversorPdfImagenes
{
    public function __construct(
        private string $rutaPdftoppm,
        private ArchivosCuentas $archivos,
    ) {
    }

    public function disponible(): bool
    {
        return is_file($this->rutaPdftoppm);
    }

    /**
     * @param string $pdfRelativo Ruta relativa de la minuta en el almacén.
     * @param string $subcarpeta  «extracciones-N», carpeta de las imágenes.
     *
     * @return array{para_ia: list<string>, todas: list<string>} Rutas
     *         relativas de los PNG generados.
     */
    public function convertir(string $pdfRelativo, string $subcarpeta): array
    {
        if (!$this->disponible()) {
            throw new \RuntimeException('No está instalado pdftoppm (Poppler); configure PDF_PDFTOPPM en el .env.');
        }

        $pdfAbsoluto = $this->archivos->rutaAbsoluta($pdfRelativo);
        $destino = dirname($this->archivos->rutaAbsoluta($subcarpeta . '/x'));

        if (!is_dir($destino) && !mkdir($destino, 0755, true) && !is_dir($destino)) {
            throw new \RuntimeException('No se pudo preparar la carpeta de imágenes.');
        }

        // Para la IA: páginas 1-5 a 150 dpi (§10 paso 1).
        $this->ejecutar([$this->rutaPdftoppm, '-png', '-r', '150', '-f', '1', '-l', '5', $pdfAbsoluto, $destino . '/ia']);
        // Para el visor: todas las páginas a 100 dpi.
        $this->ejecutar([$this->rutaPdftoppm, '-png', '-r', '100', $pdfAbsoluto, $destino . '/pag']);

        return [
            'para_ia' => $this->listar($destino, $subcarpeta, 'ia-'),
            'todas' => $this->listar($destino, $subcarpeta, 'pag-'),
        ];
    }

    /** @param list<string> $comando */
    private function ejecutar(array $comando): void
    {
        $proceso = proc_open($comando, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tuberias);

        if (!is_resource($proceso)) {
            throw new \RuntimeException('No se pudo iniciar pdftoppm.');
        }

        stream_get_contents($tuberias[1]);
        $errores = (string) stream_get_contents($tuberias[2]);
        fclose($tuberias[1]);
        fclose($tuberias[2]);

        if (proc_close($proceso) !== 0) {
            throw new \RuntimeException('pdftoppm falló: ' . mb_substr(trim($errores), 0, 300));
        }
    }

    /** @return list<string> Rutas relativas, en orden de página. */
    private function listar(string $destino, string $subcarpeta, string $prefijo): array
    {
        $archivos = glob($destino . '/' . $prefijo . '*.png') ?: [];
        natsort($archivos);

        return array_values(array_map(
            static fn (string $absoluto): string => $subcarpeta . '/' . basename($absoluto),
            $archivos,
        ));
    }
}

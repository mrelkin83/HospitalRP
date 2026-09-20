<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Service;

use setasign\Fpdi\Fpdi;

/**
 * El paquete de la cuenta (§11.4): un solo PDF con la cuenta de cobro, el
 * informe de actividades, el informe de supervisión, la planilla PILA y
 * los demás anexos, en ese orden.
 *
 * FPDI importa las páginas de los PDF generados por Dompdf; los anexos en
 * imagen (la foto de la planilla) se convierten en una página tamaño
 * carta. Si algún PDF ajeno no se puede importar (un comprimido exótico
 * del banco, por ejemplo), el documento se omite del combinado y se
 * informa: mejor un paquete sin ese anexo que ningún paquete (§11.4).
 */
final readonly class CombinadorPaquete
{
    public function __construct(private ArchivosCuentas $archivos)
    {
    }

    /**
     * @param list<array{ruta: string, mime: string}> $documentos En el
     *        orden final del paquete; rutas relativas del almacén privado.
     *
     * @return array{ruta: string, omitidos: list<string>} Ruta del paquete
     *         y qué documentos no pudieron entrar.
     */
    public function combinar(array $documentos): array
    {
        $pdf = new Fpdi();
        $omitidos = [];

        foreach ($documentos as $documento) {
            $absoluta = $this->archivos->rutaAbsoluta($documento['ruta']);

            if (!is_file($absoluta)) {
                $omitidos[] = $documento['ruta'];
                continue;
            }

            try {
                if (str_starts_with($documento['mime'], 'image/')) {
                    $this->agregarImagen($pdf, $absoluta, $documento['mime']);
                } else {
                    $this->agregarPdf($pdf, $absoluta);
                }
            } catch (\Throwable) {
                $omitidos[] = $documento['ruta'];
            }
        }

        $contenido = (string) $pdf->Output('S');

        return [
            'ruta' => $this->archivos->guardarGenerado($contenido, 'pdf', 'pdf'),
            'omitidos' => $omitidos,
        ];
    }

    private function agregarPdf(Fpdi $pdf, string $absoluta): void
    {
        $paginas = $pdf->setSourceFile($absoluta);

        for ($n = 1; $n <= $paginas; $n++) {
            $plantilla = $pdf->importPage($n);
            $tamano = $pdf->getTemplateSize($plantilla);
            $pdf->AddPage($tamano['orientation'], [$tamano['width'], $tamano['height']]);
            $pdf->useTemplate($plantilla);
        }
    }

    /** Una imagen (foto de planilla) ocupa una página carta, centrada. */
    private function agregarImagen(Fpdi $pdf, string $absoluta, string $mime): void
    {
        $dimensiones = getimagesize($absoluta);

        if ($dimensiones === false) {
            throw new \RuntimeException('Imagen ilegible.');
        }

        // Carta: 215.9 × 279.4 mm, con 15 mm de margen.
        $pdf->AddPage('P', 'Letter');
        $anchoUtil = 215.9 - 30;
        $altoUtil = 279.4 - 30;
        $escala = min($anchoUtil / $dimensiones[0], $altoUtil / $dimensiones[1]);
        $ancho = $dimensiones[0] * $escala;
        $alto = $dimensiones[1] * $escala;

        $pdf->Image(
            $absoluta,
            (215.9 - $ancho) / 2,
            (279.4 - $alto) / 2,
            $ancho,
            $alto,
            $mime === 'image/png' ? 'PNG' : 'JPG',
        );
    }
}

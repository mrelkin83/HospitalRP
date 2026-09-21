<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Service;

/**
 * Lee el texto plano de una minuta y devuelve lo que se puede sacar sin IA.
 *
 * Vive aparte porque lo alimentan DOS fuentes con la misma forma y muy
 * distinta calidad: el OCR de un escaneo (sucio, con letras cambiadas) y
 * la capa de texto de un PDF exportado de Word (exacto). Tener una sola
 * batería de expresiones evita que la del escaneo se corrija y la otra se
 * quede atrás —que es como una de las dos acaba mintiendo—.
 *
 * Rescata lo que el texto corrido da bien —forma de pago, plazo de la
 * cláusula 5, objeto y obligaciones numeradas— y lo intenta también con
 * el recuadro de la página 1 (número, valor, cédula, fecha), que en el
 * PDF exportado es texto y en el escaneo es una tabla que casi nunca
 * sobrevive al OCR. `suficiente()` decide si lo rescatado basta para
 * saltarse la IA o hay que pagarla.
 */
final readonly class ParserTextoMinuta
{
    /**
     * @return array<string, mixed> Resultado parcial con el esquema de §10.
     */
    public static function interpretar(string $texto): array
    {
        // Para localizar cláusulas se aplana TODO el espacio: los títulos
        // cruzan renglones («PLAZO DE\nEJECUCIÓN»), tanto en el escaneo
        // como en el PDF exportado.
        $plano = trim((string) preg_replace('/\s+/', ' ', $texto));
        $resultado = [];

        // FORMA DE PAGO: de la cláusula 3 (o su título) a la cláusula 4.
        if (preg_match('/FORMA DE PAGO\s*[:.]?\s*(.{40,3000}?)(?:CL[AÁ]USULA\s*(?:CUARTA|4)|DISPONIBILIDAD PRESUPUESTAL)/su', $plano, $m) === 1) {
            $resultado['forma_pago_texto'] = trim($m[1]);
        }

        // PLAZO DE EJECUCIÓN (cláusula 5): «…será CINCO (5) MESES, …».
        if (preg_match('/PLAZO DE EJECUCI[OÓ]N.{0,120}?ser[aá]\s+(?:de\s+)?(.{3,80}?)\s*[,.]/su', $plano, $m) === 1) {
            $resultado['plazo_clausula_5'] = trim($m[1]);
        }

        // OBJETO: hasta la cláusula siguiente. En el escaneo suele salir
        // cortado; en el texto exportado sale entero.
        if (preg_match('/OBJETO\s*[:.]?\s*(?:EL CONTRATISTA\s+)?(.{40,2000}?)(?:CL[AÁ]USULA\s*(?:SEGUNDA|2)|OBLIGACIONES|VALOR DEL CONTRATO)/su', $plano, $m) === 1) {
            $resultado['objeto'] = trim($m[1]);
        }

        // --- El recuadro de la página 1 ---
        // En un PDF exportado de Word, `pdftotext -layout` conserva el
        // recuadro como texto y estos datos SÍ salen; en un escaneo
        // pasado por OCR casi nunca. De ahí que `suficiente()` los exija:
        // sin número y sin valor, la ruta de texto no ahorra trabajo, solo
        // lo mueve al teclado del revisor.
        if (preg_match('/CONTRATO\s*(?:DE\s+PRESTACI[OÓ]N\s+DE\s+SERVICIOS\s*)?(?:N[°ºo]?\.?\s*)?(\d{3,5}\s*[-–]\s*20\d{2})/ui', $plano, $m) === 1) {
            $resultado['numero_contrato'] = str_replace([' ', '–'], ['', '-'], $m[1]);
        }

        // Sin la bandera `i` a propósito: el rótulo del recuadro va en
        // mayúsculas, y «el valor del contrato se cancelará en cinco
        // cuotas de $2.850.000» de la cláusula de pago se colaba como
        // valor total del contrato.
        if (preg_match('/VALOR\s+(?:TOTAL\s+)?DEL\s+CONTRATO\s*:?.{0,160}?\$\s*([\d][\d.\s]{5,20})/u', $plano, $m) === 1) {
            $resultado['valor_total'] = (int) preg_replace('/\D/', '', $m[1]);
        }

        // Las letras van entre paréntesis justo antes o después de la
        // cifra: «CATORCE MILLONES … ($14.250.000)».
        if (preg_match('/VALOR\s+(?:TOTAL\s+)?DEL\s+CONTRATO.{0,120}?([A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ\s]{15,200}?PESOS[A-ZÁÉÍÓÚÑ\s]{0,60})/u', $plano, $m) === 1) {
            $resultado['valor_total_letras'] = trim((string) preg_replace('/\s+/', ' ', $m[1]));
        }

        if (preg_match('/C\.?\s?C\.?\s*(?:N[°ºo]?\.?\s*)?([\d][\d.\s]{6,14}\d)/ui', $plano, $m) === 1) {
            $resultado['contratista'] = [
                'tipo_documento' => 'CC',
                'numero_documento' => (string) preg_replace('/\D/', '', $m[1]),
            ];
        }

        if (($fecha = self::fecha($plano)) !== null) {
            $resultado['fecha_suscripcion'] = $fecha;
        }

        // OBLIGACIONES ESPECÍFICAS: los ítems numerados hasta el bloque
        // del SGIC (o el fin de lo leído).
        if (preg_match('/OBLIGACIONES ESPEC[IÍ]FICAS\s*[:.]?(.+?)(?:SISTEMA DE GESTI[OÓ]N INTEGRAL|OBLIGACIONES FRENTE AL|CL[AÁ]USULA\s*(?:OCTAVA|8)|$)/su', $plano, $m) === 1) {
            $obligaciones = self::items($m[1]);

            if ($obligaciones !== []) {
                $resultado['obligaciones_especificas'] = $obligaciones;
            }
        }

        // SGIC: el bloque propio, cuando el documento lo trae aparte.
        if (preg_match('/SISTEMA DE GESTI[OÓ]N INTEGRAL(?: DE CALIDAD)?\s*[:.]?(.+?)(?:CL[AÁ]USULA\s*(?:OCTAVA|8)|SUPERVISI[OÓ]N|$)/su', $plano, $m) === 1) {
            $sgic = self::items($m[1]);

            if ($sgic !== []) {
                $resultado['obligaciones_sgic'] = $sgic;
            }
        }

        return $resultado;
    }

    /**
     * Cuántos campos con sustancia trae un resultado parcial.
     *
     * La cola lo usa para decidir si el texto bastó o hay que pagar la
     * IA: un PDF exportado de Word devuelve cuatro o cinco campos; uno
     * escaneado sin capa de texto devuelve cero o uno suelto.
     *
     * @param array<string, mixed> $resultado
     */
    public static function riqueza(array $resultado): int
    {
        $cuenta = 0;

        foreach (['objeto', 'forma_pago_texto', 'plazo_clausula_5'] as $campo) {
            if (is_string($resultado[$campo] ?? null) && mb_strlen($resultado[$campo]) >= 30) {
                $cuenta++;
            }
        }

        if (count($resultado['obligaciones_especificas'] ?? []) >= 3) {
            $cuenta++;
        }

        return $cuenta;
    }

    /**
     * ¿El texto dio para saltarse la IA?
     *
     * Exige lo que identifica al contrato (número y valor) MÁS tres
     * campos de cuerpo. Con menos, la extracción por texto le dejaría al
     * revisor justo la parte aburrida —teclear el recuadro— y no habría
     * ahorrado nada: en ese caso se paga la IA, que es lo que sabe leer
     * tablas escaneadas.
     *
     * @param array<string, mixed> $resultado
     */
    public static function suficiente(array $resultado): bool
    {
        $identificado = ($resultado['numero_contrato'] ?? '') !== ''
            && (int) ($resultado['valor_total'] ?? 0) > 0;

        return $identificado && self::riqueza($resultado) >= 3;
    }

    /** La primera fecha en castellano del documento, en AAAA-MM-DD. */
    private static function fecha(string $plano): ?string
    {
        $meses = [
            'enero' => '01', 'febrero' => '02', 'marzo' => '03', 'abril' => '04',
            'mayo' => '05', 'junio' => '06', 'julio' => '07', 'agosto' => '08',
            'septiembre' => '09', 'setiembre' => '09', 'octubre' => '10',
            'noviembre' => '11', 'diciembre' => '12',
        ];

        if (preg_match('/(\d{1,2})\s+(?:d[ií]as?\s+)?d[eé]l?\s+mes\s+de\s+([a-záéíóúñ]+)\s+d[eé]l?\s+(?:a[ñn]o\s+)?(20\d{2})/ui', $plano, $m) !== 1
            && preg_match('/(\d{1,2})\s+de\s+([a-záéíóúñ]+)\s+de\s+(20\d{2})/ui', $plano, $m) !== 1) {
            return null;
        }

        $mes = $meses[mb_strtolower($m[2])] ?? null;

        return $mes === null ? null : sprintf('%s-%s-%02d', $m[3], $mes, (int) $m[1]);
    }

    /**
     * Los ítems numerados de un bloque, transcritos completos.
     *
     * @return list<string>
     */
    private static function items(string $bloque): array
    {
        preg_match_all('/(?:^|\s)(\d{1,2})[.)]\s+(.{15,700}?)(?=\s\d{1,2}[.)]\s|\z)/su', $bloque, $items, PREG_SET_ORDER);
        $encontrados = [];

        foreach ($items as $item) {
            $limpia = trim((string) preg_replace('/\s+/', ' ', $item[2]));

            if (mb_strlen($limpia) >= 15) {
                $encontrados[] = $limpia;
            }
        }

        return $encontrados;
    }
}

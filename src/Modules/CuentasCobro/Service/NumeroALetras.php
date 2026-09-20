<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Service;

/**
 * Valores en letras para los formatos institucionales (§8.4 del documento).
 *
 * En los documentos reales de la muestra aparecieron valores en letras MAL
 * escritos a mano («Veintisiete días (16)»); esta clase existe para que eso
 * no vuelva a pasar: el número manda y las letras salen de él, nunca al
 * revés.
 *
 * Cubre hasta miles de millones, que es más de lo que cuesta cualquier
 * contrato de prestación de servicios del hospital.
 */
final readonly class NumeroALetras
{
    private const array UNIDADES = [
        1 => 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
    ];

    private const array DIECES = [
        10 => 'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE',
        'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE',
    ];

    private const array VEINTIS = [
        21 => 'VEINTIUNO', 'VEINTIDÓS', 'VEINTITRÉS', 'VEINTICUATRO', 'VEINTICINCO',
        'VEINTISÉIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE',
    ];

    private const array DECENAS = [
        3 => 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA',
    ];

    private const array CENTENAS = [
        2 => 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS',
        'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS',
    ];

    /**
     * «DOS MILLONES TRESCIENTOS CINCUENTA MIL PESOS M/CTE ($ 2.350.000)».
     *
     * Acepta entero en pesos o cadena con decimales («2350000.50»). Los
     * centavos solo aparecen cuando existen, tanto en letras («… PESOS CON
     * CINCUENTA CENTAVOS») como en la cifra («$ 2.350.000,50»).
     */
    public static function pesos(int|string $valor): string
    {
        [$pesos, $centavos] = self::separar($valor);

        if ($pesos === 0 && $centavos === 0) {
            return 'CERO PESOS M/CTE ($ 0)';
        }

        if ($pesos === 1) {
            $letras = 'UN PESO';
        } else {
            // «CATORCE MILLONES DE PESOS» cuando el valor es millones
            // exactos; «CATORCE MILLONES CIEN MIL PESOS» cuando no. Con
            // apócope porque sigue el sustantivo: «VEINTIÚN PESOS».
            $de = ($pesos >= 1_000_000 && $pesos % 1_000_000 === 0) ? ' DE' : '';
            $letras = self::entero($pesos, true) . $de . ' PESOS';
        }

        if ($centavos > 0) {
            $letras .= ' CON ' . ($centavos === 1 ? 'UN CENTAVO' : self::entero($centavos, true) . ' CENTAVOS');
        }

        return $letras . ' M/CTE (' . self::moneda($pesos, $centavos) . ')';
    }

    /**
     * «Veintisiete (27)», para «Dado en Valledupar a los Veintisiete (27)
     * días del mes de…». Con apócope porque siempre antecede a «días»:
     * «Veintiún (21)», «Treinta y un (31)».
     */
    public static function dia(int $d): string
    {
        if ($d < 1 || $d > 31) {
            throw new \InvalidArgumentException("Día fuera de rango: {$d}");
        }

        $letras = mb_strtolower(self::grupo($d, true), 'UTF-8');

        return mb_strtoupper(mb_substr($letras, 0, 1, 'UTF-8'), 'UTF-8')
            . mb_substr($letras, 1, null, 'UTF-8')
            . " ({$d})";
    }

    /** «$ 2.350.000» o «$ 2.350.000,50»; separador de miles punto (§3). */
    public static function moneda(int $pesos, int $centavos = 0): string
    {
        $cifra = '$ ' . number_format($pesos, 0, ',', '.');

        return $centavos > 0 ? $cifra . ',' . sprintf('%02d', $centavos) : $cifra;
    }

    /**
     * Un entero 0..999.999.999.999 en letras, sin la palabra PESOS.
     * Con $apocope el final dice «UN» en vez de «UNO», para cuando sigue
     * un sustantivo: «CIENTO UN PESOS», «VEINTIÚN CENTAVOS».
     */
    public static function entero(int $n, bool $apocope = false): string
    {
        if ($n < 0) {
            throw new \InvalidArgumentException('No hay valores negativos en una cuenta de cobro');
        }

        if ($n === 0) {
            return 'CERO';
        }

        if ($n >= 1_000_000_000_000) {
            throw new \InvalidArgumentException('Valor fuera del rango soportado (hasta miles de millones)');
        }

        if ($n >= 1_000_000) {
            $millones = intdiv($n, 1_000_000);
            $resto = $n % 1_000_000;
            $texto = $millones === 1 ? 'UN MILLÓN' : self::hastaMillon($millones, true) . ' MILLONES';

            return $resto === 0 ? $texto : $texto . ' ' . self::hastaMillon($resto, $apocope);
        }

        return self::hastaMillon($n, $apocope);
    }

    /**
     * 1..999.999. Con $apocope el último grupo dice «UN»/«VEINTIÚN» porque
     * le sigue un sustantivo («VEINTIÚN MILLONES»).
     */
    private static function hastaMillon(int $n, bool $apocope): string
    {
        $miles = intdiv($n, 1000);
        $resto = $n % 1000;
        $partes = [];

        if ($miles > 0) {
            // «MIL», nunca «UN MIL»; y siempre apócope antes de MIL:
            // «VEINTIÚN MIL», «TREINTA Y UN MIL».
            $partes[] = $miles === 1 ? 'MIL' : self::grupo($miles, true) . ' MIL';
        }

        if ($resto > 0) {
            $partes[] = self::grupo($resto, $apocope);
        }

        return implode(' ', $partes);
    }

    /** 1..999. */
    private static function grupo(int $n, bool $apocope): string
    {
        $centena = intdiv($n, 100);
        $resto = $n % 100;
        $partes = [];

        if ($centena === 1) {
            $partes[] = $resto === 0 ? 'CIEN' : 'CIENTO';
        } elseif ($centena > 1) {
            $partes[] = self::CENTENAS[$centena];
        }

        if ($resto >= 1 && $resto <= 9) {
            $partes[] = $resto === 1 ? ($apocope ? 'UN' : 'UNO') : self::UNIDADES[$resto];
        } elseif ($resto >= 10 && $resto <= 19) {
            $partes[] = self::DIECES[$resto];
        } elseif ($resto === 20) {
            $partes[] = 'VEINTE';
        } elseif ($resto >= 21 && $resto <= 29) {
            $partes[] = $resto === 21 ? ($apocope ? 'VEINTIÚN' : 'VEINTIUNO') : self::VEINTIS[$resto];
        } elseif ($resto >= 30) {
            $decena = self::DECENAS[intdiv($resto, 10)];
            $unidad = $resto % 10;
            $partes[] = $unidad === 0
                ? $decena
                : $decena . ' Y ' . ($unidad === 1 ? ($apocope ? 'UN' : 'UNO') : self::UNIDADES[$unidad]);
        }

        return implode(' ', $partes);
    }

    /**
     * @return array{int, int} [pesos, centavos]
     */
    private static function separar(int|string $valor): array
    {
        if (is_int($valor)) {
            if ($valor < 0) {
                throw new \InvalidArgumentException('No hay valores negativos en una cuenta de cobro');
            }

            return [$valor, 0];
        }

        $limpio = trim($valor);

        if (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $limpio, $m) !== 1) {
            throw new \InvalidArgumentException("Valor monetario ilegible: «{$valor}»");
        }

        $centavos = isset($m[2]) ? (int) str_pad($m[2], 2, '0') : 0;

        return [(int) $m[1], $centavos];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Tramites\Service;

use DateTimeImmutable;

/**
 * Festivos de Colombia según la Ley 51 de 1983 (Ley Emiliani), calculados
 * por algoritmo y no a mano: fijos, trasladables al lunes siguiente y los
 * que dependen de la Pascua.
 *
 * Factoría pura. La semilla los persiste en `festivos`; el cálculo de días
 * hábiles lee la tabla, no esta clase (el panel podrá corregir un festivo
 * decretado por fuera de la ley sin tocar código).
 */
final class FestivosColombia
{
    private function __construct()
    {
    }

    /**
     * @return array<string, string> fecha Y-m-d => nombre
     */
    public static function delAnio(int $anio): array
    {
        $festivos = [];

        // Fijos: se celebran el día que caen.
        foreach ([
            "$anio-01-01" => 'Año Nuevo',
            "$anio-05-01" => 'Día del Trabajo',
            "$anio-07-20" => 'Día de la Independencia',
            "$anio-08-07" => 'Batalla de Boyacá',
            "$anio-12-08" => 'Inmaculada Concepción',
            "$anio-12-25" => 'Navidad',
        ] as $fecha => $nombre) {
            $festivos[$fecha] = $nombre;
        }

        // Trasladables al lunes siguiente (Ley Emiliani).
        foreach ([
            "$anio-01-06" => 'Día de los Reyes Magos',
            "$anio-03-19" => 'Día de San José',
            "$anio-06-29" => 'San Pedro y San Pablo',
            "$anio-08-15" => 'Asunción de la Virgen',
            "$anio-10-12" => 'Día de la Raza',
            "$anio-11-01" => 'Todos los Santos',
            "$anio-11-11" => 'Independencia de Cartagena',
        ] as $fecha => $nombre) {
            $festivos[self::alLunes(new DateTimeImmutable($fecha))] = $nombre;
        }

        // Dependientes de la Pascua.
        $pascua = self::domingoDePascua($anio);
        $festivos[$pascua->modify('-3 days')->format('Y-m-d')] = 'Jueves Santo';
        $festivos[$pascua->modify('-2 days')->format('Y-m-d')] = 'Viernes Santo';
        $festivos[self::alLunes($pascua->modify('+39 days'))] = 'Ascensión del Señor';
        $festivos[self::alLunes($pascua->modify('+60 days'))] = 'Corpus Christi';
        $festivos[self::alLunes($pascua->modify('+68 days'))] = 'Sagrado Corazón';

        ksort($festivos);

        return $festivos;
    }

    /**
     * Algoritmo gregoriano anónimo: no depende de ext-calendar.
     */
    private static function domingoDePascua(int $anio): DateTimeImmutable
    {
        $a = $anio % 19;
        $b = intdiv($anio, 100);
        $c = $anio % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new DateTimeImmutable(sprintf('%d-%02d-%02d', $anio, $mes, $dia));
    }

    private static function alLunes(DateTimeImmutable $fecha): string
    {
        if ($fecha->format('N') !== '1') {
            $fecha = $fecha->modify('next monday');
        }

        return $fecha->format('Y-m-d');
    }
}

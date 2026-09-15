<?php

declare(strict_types=1);

namespace App\Core\Support;

use DateTimeImmutable;
use IntlDateFormatter;

/**
 * Fechas legibles en español de Colombia. Factoría pura.
 */
final class Fechas
{
    private function __construct()
    {
    }

    /**
     * «15 de mayo de 2026» a partir de un DATETIME de MySQL.
     */
    public static function larga(?string $fechaMysql): string
    {
        if ($fechaMysql === null || $fechaMysql === '') {
            return '';
        }

        $formateador = new IntlDateFormatter(
            'es_CO',
            IntlDateFormatter::LONG,
            IntlDateFormatter::NONE,
            'America/Bogota',
        );

        $resultado = $formateador->format(new DateTimeImmutable($fechaMysql));

        return is_string($resultado) ? $resultado : $fechaMysql;
    }

    /**
     * «15/05/2026, 3:42 p. m.» a partir de un DATETIME de MySQL.
     *
     * Formato corto y con hora porque donde se usa —auditoría, último acceso—
     * la pregunta es «¿cuándo exactamente?» y las filas se leen en columna:
     * «15 de mayo de 2026 a las …» repetido cincuenta veces no se lee, se
     * estorba.
     */
    public static function conHora(?string $fechaMysql): string
    {
        if ($fechaMysql === null || $fechaMysql === '') {
            return '';
        }

        $formateador = new IntlDateFormatter(
            'es_CO',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            'America/Bogota',
            // Año completo y no el «26» del formato corto: en un registro de
            // auditoría que se conserva años, dos dígitos son una ambigüedad.
            pattern: 'dd/MM/y, h:mm a',
        );

        $resultado = $formateador->format(new DateTimeImmutable($fechaMysql));

        return is_string($resultado) ? $resultado : $fechaMysql;
    }

    /**
     * «septiembre de 2026» a partir de un DATE de MySQL.
     *
     * Los indicadores se publican por periodo mensual: el día no aporta
     * —siempre es el 1— y mostrarlo haría creer que la cifra es de ese día y
     * no del mes completo.
     */
    public static function mesYAnio(?string $fechaMysql): string
    {
        if ($fechaMysql === null || $fechaMysql === '') {
            return '';
        }

        $formateador = new IntlDateFormatter(
            'es_CO',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            'America/Bogota',
            pattern: "LLLL 'de' y",
        );

        $resultado = $formateador->format(new DateTimeImmutable($fechaMysql));

        return is_string($resultado) ? $resultado : $fechaMysql;
    }

    /**
     * «15» — solo el día del mes, para la pastilla de fecha de la agenda.
     */
    public static function dia(?string $fechaMysql): string
    {
        return self::conPatron($fechaMysql, 'd');
    }

    /**
     * «sept» — mes abreviado, en minúscula y sin punto, como lo escribe la
     * ICU para es_CO. Acompaña a `dia()` en la pastilla de la agenda: el par
     * «15 / sept» se lee de un vistazo, que es lo que hace un calendario.
     */
    public static function mesCorto(?string $fechaMysql): string
    {
        return rtrim(self::conPatron($fechaMysql, 'LLL'), '.');
    }

    /**
     * «9:00 a. m.» — hora de un evento. Sin fecha, porque en la agenda el día
     * ya lo dice la pastilla de al lado.
     */
    public static function hora(?string $fechaMysql): string
    {
        return self::conPatron($fechaMysql, 'h:mm a');
    }

    private static function conPatron(?string $fechaMysql, string $patron): string
    {
        if ($fechaMysql === null || $fechaMysql === '') {
            return '';
        }

        $formateador = new IntlDateFormatter(
            'es_CO',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            'America/Bogota',
            pattern: $patron,
        );

        $resultado = $formateador->format(new DateTimeImmutable($fechaMysql));

        return is_string($resultado) ? $resultado : $fechaMysql;
    }
}

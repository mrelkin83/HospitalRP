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
}

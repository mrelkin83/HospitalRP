<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Service;

/**
 * Fechas en el español de los formatos (§8.4 del documento).
 *
 * Existe aparte de `App\Core\Support\Fechas` a propósito: aquella trabaja
 * sobre cadenas MySQL para las vistas del sitio; esta trabaja sobre objetos
 * de fecha y produce los giros exactos de los documentos («del 28 de junio
 * al 8 de julio de 2026»), que en un PDF legal no admiten variación.
 */
final readonly class FechasCuenta
{
    private const array MESES = [
        1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
    ];

    /** «28 de enero de 2026». */
    public static function larga(\DateTimeInterface $f): string
    {
        return $f->format('j') . ' de ' . self::MESES[(int) $f->format('n')] . ' de ' . $f->format('Y');
    }

    /** El nombre del mes en minúsculas: «julio». */
    public static function mes(\DateTimeInterface $f): string
    {
        return self::MESES[(int) $f->format('n')];
    }

    /**
     * El periodo como lo escriben los documentos, sin repetir lo obvio:
     * «del 1 al 31 de agosto de 2026» (mismo mes),
     * «del 28 de junio al 8 de julio de 2026» (mismo año),
     * «del 28 de diciembre de 2026 al 27 de enero de 2027» (años distintos).
     */
    public static function periodo(\DateTimeInterface $desde, \DateTimeInterface $hasta): string
    {
        $mismoAnio = $desde->format('Y') === $hasta->format('Y');
        $mismoMes = $mismoAnio && $desde->format('n') === $hasta->format('n');

        if ($mismoMes) {
            return 'del ' . $desde->format('j') . ' al ' . self::larga($hasta);
        }

        if ($mismoAnio) {
            return 'del ' . $desde->format('j') . ' de ' . self::MESES[(int) $desde->format('n')]
                . ' al ' . self::larga($hasta);
        }

        return 'del ' . self::larga($desde) . ' al ' . self::larga($hasta);
    }

    /** «2026-08-05» → objeto de fecha, sin hora. */
    public static function desdeMysql(string $fecha): \DateTimeImmutable
    {
        $f = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

        if ($f === false) {
            throw new \InvalidArgumentException("Fecha ilegible: «{$fecha}»");
        }

        return $f;
    }
}

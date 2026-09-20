<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Entity;

/**
 * Los parámetros de ley que cambian cada año (tabla `cc_parametros_anuales`).
 *
 * Las tarifas viajan como cadenas decimales («0.1250») y no como float:
 * los aportes se calculan con aritmética entera exacta en CalculosCuenta.
 * El SMLV va en pesos enteros por la misma razón.
 */
final readonly class ParametrosAnuales
{
    public function __construct(
        public int $anio,
        public int $smlv,
        public string $porcentajeIbc = '0.4000',
        public string $tarifaSalud = '0.1250',
        public string $tarifaPension = '0.1600',
        public int $redondeoAportes = 100,
    ) {
    }
}

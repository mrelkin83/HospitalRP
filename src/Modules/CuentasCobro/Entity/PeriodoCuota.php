<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Entity;

/**
 * El resultado de aterrizar una cuota sobre el calendario: sus fechas
 * reales y cuántos días cubre, contando ambos extremos.
 */
final readonly class PeriodoCuota
{
    public function __construct(
        public int $numeroCuota,
        public \DateTimeImmutable $desde,
        public \DateTimeImmutable $hasta,
        public int $dias,
        public int $valor,
        public string $descripcion,
    ) {
    }
}

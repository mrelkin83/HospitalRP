<?php

declare(strict_types=1);

namespace App\Modules\Tramites\Service;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;

/**
 * Términos legales en días hábiles (Ley 1755): el conteo empieza el día
 * hábil siguiente a la radicación y salta fines de semana y los festivos de
 * la tabla `festivos`. Equivocar esto desplaza vencimientos un día entero.
 */
final class CalculadoraDiasHabiles
{
    /** @var ?array<string, true> */
    private ?array $festivos = null;

    public function __construct(private readonly Connection $conexion)
    {
    }

    /**
     * Fecha de vencimiento de un término que corre desde `$recibido`.
     */
    public function vencimiento(DateTimeImmutable $recibido, int $dias, bool $habiles = true): DateTimeImmutable
    {
        $fecha = $recibido->setTime(0, 0);

        if (!$habiles) {
            $fecha = $fecha->modify(sprintf('+%d days', $dias));

            // Si el término calendario vence en día inhábil, corre al hábil
            // siguiente (art. 62, Ley 4ª de 1913).
            while (!$this->esHabil($fecha)) {
                $fecha = $fecha->modify('+1 day');
            }

            return $fecha;
        }

        $restantes = $dias;

        while ($restantes > 0) {
            $fecha = $fecha->modify('+1 day');

            if ($this->esHabil($fecha)) {
                $restantes--;
            }
        }

        return $fecha;
    }

    /**
     * Días hábiles transcurridos entre dos fechas (sin contar el día inicial).
     */
    public function habilesEntre(DateTimeImmutable $desde, DateTimeImmutable $hasta): int
    {
        $fecha = $desde->setTime(0, 0);
        $fin = $hasta->setTime(0, 0);
        $dias = 0;

        while ($fecha < $fin) {
            $fecha = $fecha->modify('+1 day');

            if ($this->esHabil($fecha)) {
                $dias++;
            }
        }

        return $dias;
    }

    public function esHabil(DateTimeImmutable $fecha): bool
    {
        $diaSemana = (int) $fecha->format('N');

        if ($diaSemana >= 6) {
            return false;
        }

        return !isset($this->cargarFestivos()[$fecha->format('Y-m-d')]);
    }

    /**
     * @return array<string, true>
     */
    private function cargarFestivos(): array
    {
        if ($this->festivos !== null) {
            return $this->festivos;
        }

        $festivos = [];

        foreach ($this->conexion->fetchFirstColumn('SELECT fecha FROM festivos') as $fecha) {
            $festivos[(string) $fecha] = true;
        }

        return $this->festivos = $festivos;
    }
}

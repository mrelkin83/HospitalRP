<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Entity;

/**
 * Lo que el sistema calcula que debería decir la planilla PILA del periodo.
 *
 * Son valores SUGERIDOS: lo que vale es lo que reporte el contratista de su
 * planilla real; el sistema solo resalta al supervisor las diferencias
 * mayores al 5 % (§8.3). Los parafiscales existen porque el formato los
 * trae, pero para independientes son cero.
 */
final readonly class AportesSugeridos
{
    public function __construct(
        public int $ibc,
        public int $salud,
        public int $pension,
        public int $fsp,
        public int $arl,
        /** Tarifa ARL aplicada, como decimal («0.02436») para el formato. */
        public string $tarifaArl,
        public int $sena = 0,
        public int $icbf = 0,
        public int $ccf = 0,
    ) {
    }

    public function total(): int
    {
        return $this->salud + $this->pension + $this->fsp + $this->arl
            + $this->sena + $this->icbf + $this->ccf;
    }
}

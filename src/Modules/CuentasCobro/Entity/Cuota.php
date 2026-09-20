<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Entity;

/**
 * Una cuota del cronograma de pagos, tal como se pactó en la cláusula
 * «Forma de pago» de la minuta. Los periodos concretos NO viven aquí:
 * se calculan al registrar el acta de inicio (CalculosCuenta::cronograma)
 * porque dependen de la fecha real de arranque.
 *
 * El valor va en pesos enteros: los contratos de la muestra no traen
 * centavos y sumar dinero en float está prohibido (§3 del documento).
 */
final readonly class Cuota
{
    public function __construct(
        public int $numero,
        public string $descripcion,
        public int $valor,
        public TipoCuota $tipo = TipoCuota::COMPLETA,
        /** Mes que cubre, formato «YYYY-MM». Nulo cuando lo fija el acta. */
        public ?string $mes = null,
    ) {
    }
}

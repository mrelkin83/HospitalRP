<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Entity;

/**
 * Una cuota o cubre un mes entero o cubre un pedazo.
 *
 * La PARCIAL existe porque los contratos casi nunca arrancan un día primero:
 * «UN primer pago de $583.333 correspondiente a los días del mes de julio».
 */
enum TipoCuota: string
{
    case COMPLETA = 'COMPLETA';
    case PARCIAL = 'PARCIAL';
}

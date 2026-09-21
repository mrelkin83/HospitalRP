<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Entity;

/**
 * Flujo de una cuenta de cobro (sección 5 del documento del módulo).
 *
 * BORRADOR → ENVIADA → APROBADA, con la vuelta atrás DEVUELTA cuando el
 * supervisor pide correcciones. ENTREGADA_FINANCIERA es una marca informativa
 * posterior a la aprobación; ANULADA solo la pone el administrador con motivo
 * y saca la cuenta de consecutivos y acumulados.
 */
enum EstadoCuenta: string
{
    case BORRADOR = 'BORRADOR';
    case ENVIADA = 'ENVIADA';
    // La revisión previa: un equipo de revisores verifica la cuenta y la
    // firma antes de que llegue al supervisor.
    case REVISADA = 'REVISADA';
    case DEVUELTA = 'DEVUELTA';
    case APROBADA = 'APROBADA';
    case ENTREGADA_FINANCIERA = 'ENTREGADA_FINANCIERA';
    case ANULADA = 'ANULADA';

    public function etiqueta(): string
    {
        return match ($this) {
            self::BORRADOR => 'Borrador',
            self::ENVIADA => 'Enviada (en revisión previa)',
            self::REVISADA => 'Revisada (donde el supervisor)',
            self::DEVUELTA => 'Devuelta',
            self::APROBADA => 'Aprobada',
            self::ENTREGADA_FINANCIERA => 'Entregada a Financiera',
            self::ANULADA => 'Anulada',
        };
    }
}

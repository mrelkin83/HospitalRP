<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Entity;

/**
 * Vida de un contrato dentro del módulo.
 *
 * EN_REVISION es el estado de carga: el administrador todavía puede corregir
 * lo que la minuta (o la extracción) trajo mal. Al ACTIVAR se exige que todo
 * cuadre (contratista con cédula, supervisor, al menos un CDP y cronograma
 * que sume el valor total) y a partir de ahí el contratista puede entrar.
 *
 * Nada se borra: un contrato mal cargado se ANULA y queda como constancia.
 */
enum EstadoContrato: string
{
    case EN_REVISION = 'EN_REVISION';
    case ACTIVO = 'ACTIVO';
    case SUSPENDIDO = 'SUSPENDIDO';
    case TERMINADO = 'TERMINADO';
    case LIQUIDADO = 'LIQUIDADO';
    case ANULADO = 'ANULADO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EN_REVISION => 'En revisión',
            self::ACTIVO => 'Activo',
            self::SUSPENDIDO => 'Suspendido',
            self::TERMINADO => 'Terminado',
            self::LIQUIDADO => 'Liquidado',
            self::ANULADO => 'Anulado',
        };
    }
}

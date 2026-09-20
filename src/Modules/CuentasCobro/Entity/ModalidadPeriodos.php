<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Entity;

/**
 * Cómo se cortan los periodos de las cuotas de un contrato.
 *
 * CALENDARIO: cada cuota completa va del día 1 al último día de su mes; la
 * parcial inicial, del acta de inicio al fin de ese mes. Es el caso normal
 * de las minutas de 2026 («al finalizar los meses de agosto, septiembre…»).
 *
 * ANIVERSARIO: la cuota N va de `acta + (N−1) meses` a `acta + N meses − 1
 * día`. Es el contrato 1060-2026 de la muestra: «del 28 de enero al 27 de
 * febrero».
 */
enum ModalidadPeriodos: string
{
    case CALENDARIO = 'CALENDARIO';
    case ANIVERSARIO = 'ANIVERSARIO';
}

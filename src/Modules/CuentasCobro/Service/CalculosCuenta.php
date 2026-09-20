<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Service;

use App\Modules\CuentasCobro\Entity\AportesSugeridos;
use App\Modules\CuentasCobro\Entity\Cuota;
use App\Modules\CuentasCobro\Entity\ModalidadPeriodos;
use App\Modules\CuentasCobro\Entity\ParametrosAnuales;
use App\Modules\CuentasCobro\Entity\PeriodoCuota;
use App\Modules\CuentasCobro\Entity\TipoCuota;

/**
 * Las reglas de cálculo del módulo (§8 del documento), en un solo sitio y
 * con aritmética entera: nada de float para dinero.
 *
 * Todo lo que el sistema pueda calcular, lo calcula: el contratista y el
 * administrador solo confirman. Cada regla está verificada contra los
 * documentos reales de la muestra (contratos 1060, 793, 1407, 1414 de 2026).
 */
final readonly class CalculosCuenta
{
    /**
     * Aterriza el cronograma pactado sobre el calendario real, a partir del
     * acta de inicio (§8.1).
     *
     * CALENDARIO: la parcial inicial va del acta al fin de ese mes; cada
     * completa, del 1 al último día de su mes (el suyo declarado o el
     * siguiente al de la cuota anterior). ANIVERSARIO: la cuota N va de
     * `acta + (N−1) meses` a `acta + N meses − 1 día`, con tope de fin de
     * mes para que un 31 no se desborde al mes siguiente.
     *
     * @param list<Cuota> $cuotas En orden de número de cuota.
     *
     * @return list<PeriodoCuota>
     */
    public static function cronograma(array $cuotas, ModalidadPeriodos $modalidad, \DateTimeImmutable $actaInicio): array
    {
        if ($modalidad === ModalidadPeriodos::ANIVERSARIO) {
            return self::cronogramaAniversario($cuotas, $actaInicio);
        }

        $periodos = [];
        $hastaAnterior = null;

        foreach ($cuotas as $cuota) {
            if ($cuota->tipo === TipoCuota::PARCIAL && $hastaAnterior === null) {
                // La cuota parcial inicial: del acta al fin de ese mes.
                $desde = $actaInicio;
                $hasta = $actaInicio->modify('last day of this month');
            } elseif ($cuota->mes !== null) {
                $desde = FechasCuenta::desdeMysql($cuota->mes . '-01');
                $hasta = $desde->modify('last day of this month');

                // Una parcial que no es la inicial (una terminación pactada
                // a mitad de mes) arranca donde terminó la anterior.
                if ($cuota->tipo === TipoCuota::PARCIAL && $hastaAnterior !== null) {
                    $desde = $hastaAnterior->modify('+1 day');
                }
            } else {
                // Completa sin mes declarado: el mes siguiente al de la
                // cuota anterior (o el del acta si es la primera).
                $desde = $hastaAnterior === null
                    ? $actaInicio->modify('first day of this month')
                    : $hastaAnterior->modify('+1 day');
                $hasta = $desde->modify('last day of this month');
            }

            $periodos[] = new PeriodoCuota(
                numeroCuota: $cuota->numero,
                desde: $desde,
                hasta: $hasta,
                dias: self::diasEntre($desde, $hasta),
                valor: $cuota->valor,
                descripcion: $cuota->descripcion,
            );
            $hastaAnterior = $hasta;
        }

        return $periodos;
    }

    /** @param list<Cuota> $cuotas @return list<PeriodoCuota> */
    private static function cronogramaAniversario(array $cuotas, \DateTimeImmutable $actaInicio): array
    {
        $periodos = [];

        foreach ($cuotas as $i => $cuota) {
            $desde = $i === 0 ? $actaInicio : self::sumarMeses($actaInicio, $i);
            $hasta = self::sumarMeses($actaInicio, $i + 1)->modify('-1 day');

            $periodos[] = new PeriodoCuota(
                numeroCuota: $cuota->numero,
                desde: $desde,
                hasta: $hasta,
                dias: self::diasEntre($desde, $hasta),
                valor: $cuota->valor,
                descripcion: $cuota->descripcion,
            );
        }

        return $periodos;
    }

    /**
     * La fecha de terminación del contrato es donde termina la última cuota
     * (§8.1); editable después por el administrador si la minuta dice otra
     * cosa, pero esta es la que cuadra con el cronograma.
     *
     * @param list<PeriodoCuota> $periodos
     */
    public static function fechaTerminacion(array $periodos): \DateTimeImmutable
    {
        if ($periodos === []) {
            throw new \InvalidArgumentException('Un cronograma sin cuotas no tiene fecha de terminación');
        }

        return $periodos[array_key_last($periodos)]->hasta;
    }

    /**
     * La suma de las cuotas debe ser exactamente el valor total del
     * contrato (más adiciones). Devuelve la diferencia en pesos para
     * mostrarla cuando no cuadra: 0 significa que se puede activar.
     *
     * @param list<Cuota> $cuotas
     */
    public static function diferenciaConTotal(array $cuotas, int $valorTotal): int
    {
        $suma = 0;

        foreach ($cuotas as $cuota) {
            $suma += $cuota->valor;
        }

        return $suma - $valorTotal;
    }

    /**
     * Prorrateo de una cuota que no cubre el mes completo (§8.2):
     * `valor_mensual / 30 × días`, redondeado al peso.
     * Verificado con la muestra: 2.350.000 / 30 × 10 = 783.333.
     */
    public static function prorrateo(int $valorMensual, int $dias): int
    {
        $numerador = $valorMensual * $dias;
        $cociente = intdiv($numerador, 30);

        return ($numerador % 30) * 2 >= 30 ? $cociente + 1 : $cociente;
    }

    /**
     * Los aportes PILA que el sistema sugiere (§8.3), con la aritmética de
     * la planilla: IBC = max(40 % de la cuota mensual, SMLV) y cada aporte
     * redondeado HACIA ARRIBA al múltiplo de 100.
     *
     * Verificado con el contrato 793-2026: cuota 2.350.000 y clase 3 dan
     * IBC 1.750.905, salud 218.900, pensión 280.200, ARL 42.700.
     *
     * @param int    $valorCuotaCompleta Valor de la cuota mensual completa
     *                                   (para parciales se usa la completa).
     * @param string $tarifaArl          Decimal de `cc_arl_clases` («0.02436»).
     */
    public static function aportesSugeridos(int $valorCuotaCompleta, string $tarifaArl, ParametrosAnuales $p): AportesSugeridos
    {
        $ibc = max(self::porcentaje($valorCuotaCompleta, $p->porcentajeIbc), $p->smlv);

        // Fondo de solidaridad pensional: 1 % desde 4 SMLV. El escalonado
        // adicional desde 16 SMLV existe en la norma pero ningún contrato
        // del hospital se acerca; queda documentado y sin implementar (§8.3).
        $fsp = $ibc >= 4 * $p->smlv
            ? self::aporteRedondeado($ibc, '0.01', $p->redondeoAportes)
            : 0;

        return new AportesSugeridos(
            ibc: $ibc,
            salud: self::aporteRedondeado($ibc, $p->tarifaSalud, $p->redondeoAportes),
            pension: self::aporteRedondeado($ibc, $p->tarifaPension, $p->redondeoAportes),
            fsp: $fsp,
            arl: self::aporteRedondeado($ibc, $tarifaArl, $p->redondeoAportes),
            tarifaArl: $tarifaArl,
        );
    }

    /**
     * El independiente cotiza mes vencido: para un periodo que termina en
     * julio, la planilla es de junio (§8.3). Devuelve «YYYY-MM», editable
     * por el contratista.
     */
    public static function mesPlanillaSugerido(\DateTimeImmutable $periodoHasta): string
    {
        return $periodoHasta->modify('first day of this month')->modify('-1 month')->format('Y-m');
    }

    /** Días entre dos fechas contando ambos extremos. */
    public static function diasEntre(\DateTimeImmutable $desde, \DateTimeImmutable $hasta): int
    {
        if ($hasta < $desde) {
            throw new \InvalidArgumentException('El periodo termina antes de empezar');
        }

        return $desde->diff($hasta)->days + 1;
    }

    /**
     * Suma meses sin desbordarse: 31 de enero + 1 mes = 28 de febrero,
     * no 3 de marzo. PHP desborda solo con `modify('+1 month')`.
     */
    public static function sumarMeses(\DateTimeImmutable $fecha, int $meses): \DateTimeImmutable
    {
        $dia = (int) $fecha->format('j');
        $primerDia = $fecha->modify('first day of this month')->modify(sprintf('+%d months', $meses));
        $tope = (int) $primerDia->format('t');

        return $primerDia->setDate(
            (int) $primerDia->format('Y'),
            (int) $primerDia->format('n'),
            min($dia, $tope),
        );
    }

    /** `valor × tarifa` redondeado al entero más cercano, sin float. */
    private static function porcentaje(int $valor, string $tarifa): int
    {
        [$num, $den] = self::fraccion($tarifa);
        $producto = $valor * $num;
        $cociente = intdiv($producto, $den);

        return ($producto % $den) * 2 >= $den ? $cociente + 1 : $cociente;
    }

    /** `valor × tarifa` redondeado HACIA ARRIBA al múltiplo dado (PILA). */
    private static function aporteRedondeado(int $valor, string $tarifa, int $multiplo): int
    {
        [$num, $den] = self::fraccion($tarifa);
        $producto = $valor * $num;
        $divisor = $den * $multiplo;
        $cociente = intdiv($producto, $divisor);

        return ($producto % $divisor > 0 ? $cociente + 1 : $cociente) * $multiplo;
    }

    /**
     * «0.02436» → [2436, 100000]. La tarifa nunca pasa por float: la
     * fracción exacta evita que un 0.1250 se convierta en 0.12499999….
     *
     * @return array{int, int}
     */
    private static function fraccion(string $tarifa): array
    {
        if (preg_match('/^(\d+)(?:\.(\d+))?$/', $tarifa, $m) !== 1) {
            throw new \InvalidArgumentException("Tarifa ilegible: «{$tarifa}»");
        }

        $decimales = $m[2] ?? '';
        $den = 10 ** strlen($decimales);

        return [(int) ($m[1] . $decimales), $den];
    }
}

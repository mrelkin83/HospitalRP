<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\CuentasCobro;

use App\Modules\CuentasCobro\Entity\Cuota;
use App\Modules\CuentasCobro\Entity\ModalidadPeriodos;
use App\Modules\CuentasCobro\Entity\ParametrosAnuales;
use App\Modules\CuentasCobro\Entity\TipoCuota;
use App\Modules\CuentasCobro\Service\CalculosCuenta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Las reglas de §8 verificadas contra los contratos de muestra de la
 * sección 13, incluidos los criterios de aceptación de la fase 1.
 */
#[CoversClass(CalculosCuenta::class)]
final class CalculosCuentaTest extends TestCase
{
    private static function parametros2026(): ParametrosAnuales
    {
        return new ParametrosAnuales(anio: 2026, smlv: 1750905);
    }

    /**
     * Criterio de aceptación: acta del 1407-2026 el 2026-08-01 produce
     * cinco periodos calendario agosto-diciembre y termina el 2026-12-31.
     */
    public function testCronogramaCalendario1407(): void
    {
        $cuotas = [];

        for ($n = 1; $n <= 5; $n++) {
            $cuotas[] = new Cuota($n, 'Cuota ' . $n, 2850000);
        }

        $periodos = CalculosCuenta::cronograma(
            $cuotas,
            ModalidadPeriodos::CALENDARIO,
            new \DateTimeImmutable('2026-08-01'),
        );

        self::assertCount(5, $periodos);
        self::assertSame('2026-08-01', $periodos[0]->desde->format('Y-m-d'));
        self::assertSame('2026-08-31', $periodos[0]->hasta->format('Y-m-d'));
        self::assertSame('2026-09-01', $periodos[1]->desde->format('Y-m-d'));
        self::assertSame('2026-12-01', $periodos[4]->desde->format('Y-m-d'));
        self::assertSame('2026-12-31', $periodos[4]->hasta->format('Y-m-d'));
        self::assertSame('2026-12-31', CalculosCuenta::fechaTerminacion($periodos)->format('Y-m-d'));
    }

    /**
     * Criterio de aceptación: acta del 1414-2026 el 2026-07-27 produce la
     * cuota 1 del 27 al 31 de julio ($583.333) y cinco cuotas de $3.500.000.
     */
    public function testCronogramaConParcialInicial1414(): void
    {
        $cuotas = [new Cuota(1, 'Días de julio de 2026', 583333, TipoCuota::PARCIAL)];

        for ($n = 2; $n <= 6; $n++) {
            $cuotas[] = new Cuota($n, 'Cuota ' . $n, 3500000);
        }

        $periodos = CalculosCuenta::cronograma(
            $cuotas,
            ModalidadPeriodos::CALENDARIO,
            new \DateTimeImmutable('2026-07-27'),
        );

        self::assertSame('2026-07-27', $periodos[0]->desde->format('Y-m-d'));
        self::assertSame('2026-07-31', $periodos[0]->hasta->format('Y-m-d'));
        self::assertSame(5, $periodos[0]->dias);
        self::assertSame('2026-08-01', $periodos[1]->desde->format('Y-m-d'));
        self::assertSame('2026-08-31', $periodos[1]->hasta->format('Y-m-d'));
        self::assertSame('2026-12-31', CalculosCuenta::fechaTerminacion($periodos)->format('Y-m-d'));

        // El valor de la parcial coincide con el prorrateo de la mensual.
        self::assertSame(583333, CalculosCuenta::prorrateo(3500000, 5));
    }

    /** 1305-2026: acta 2026-07-07, «los 25 días de julio». */
    public function testParcialInicialDe25Dias(): void
    {
        $cuotas = [new Cuota(1, '25 días de julio de 2026', 3333333, TipoCuota::PARCIAL)];

        for ($n = 2; $n <= 6; $n++) {
            $cuotas[] = new Cuota($n, 'Cuota ' . $n, 4000000);
        }

        $periodos = CalculosCuenta::cronograma(
            $cuotas,
            ModalidadPeriodos::CALENDARIO,
            new \DateTimeImmutable('2026-07-07'),
        );

        self::assertSame(25, $periodos[0]->dias);
        self::assertSame('2026-07-31', $periodos[0]->hasta->format('Y-m-d'));
    }

    /**
     * 1060-2026, modalidad ANIVERSARIO desde el 2026-01-28: «del 28 de
     * enero al 27 de febrero», y así hasta terminar el 2026-07-27.
     */
    public function testCronogramaAniversario1060(): void
    {
        $cuotas = [];

        for ($n = 1; $n <= 6; $n++) {
            $cuotas[] = new Cuota($n, 'Mes ' . $n, 2350000);
        }

        $periodos = CalculosCuenta::cronograma(
            $cuotas,
            ModalidadPeriodos::ANIVERSARIO,
            new \DateTimeImmutable('2026-01-28'),
        );

        self::assertSame('2026-01-28', $periodos[0]->desde->format('Y-m-d'));
        self::assertSame('2026-02-27', $periodos[0]->hasta->format('Y-m-d'));
        self::assertSame('2026-02-28', $periodos[1]->desde->format('Y-m-d'));
        self::assertSame('2026-03-27', $periodos[1]->hasta->format('Y-m-d'));
        self::assertSame('2026-06-28', $periodos[5]->desde->format('Y-m-d'));
        self::assertSame('2026-07-27', $periodos[5]->hasta->format('Y-m-d'));
    }

    /** Un cronograma que no suma el total no deja activar el contrato. */
    public function testDiferenciaConTotal(): void
    {
        $cuotas = [
            new Cuota(1, 'Única', 2350000),
            new Cuota(2, 'Otra', 2350000),
        ];

        self::assertSame(0, CalculosCuenta::diferenciaConTotal($cuotas, 4700000));
        self::assertSame(-300000, CalculosCuenta::diferenciaConTotal($cuotas, 5000000));
    }

    /** §8.2, verificado con la muestra real: 2.350.000 / 30 × 10 = 783.333. */
    public function testProrrateo(): void
    {
        self::assertSame(783333, CalculosCuenta::prorrateo(2350000, 10));
        self::assertSame(583333, CalculosCuenta::prorrateo(3500000, 5));
        self::assertSame(1483733, CalculosCuenta::prorrateo(5564000, 8));
        self::assertSame(2350000, CalculosCuenta::prorrateo(2350000, 30));
    }

    /**
     * Criterio de aceptación: cuota de 2.350.000 y clase 3 dan IBC
     * 1.750.905, salud 218.900, pensión 280.200, ARL 42.700 (contrato
     * 793-2026 de la muestra).
     */
    public function testAportesSugeridosConPisoDeSmlv(): void
    {
        $aportes = CalculosCuenta::aportesSugeridos(2350000, '0.02436', self::parametros2026());

        self::assertSame(1750905, $aportes->ibc);
        self::assertSame(218900, $aportes->salud);
        self::assertSame(280200, $aportes->pension);
        self::assertSame(42700, $aportes->arl);
        self::assertSame(0, $aportes->fsp);
        self::assertSame(0, $aportes->sena);
        self::assertSame(541800, $aportes->total());
    }

    /** Con el 40 % por encima del SMLV, el IBC es el 40 %. */
    public function testAportesSugeridosSobreElPiso(): void
    {
        $aportes = CalculosCuenta::aportesSugeridos(5564000, '0.00522', self::parametros2026());

        self::assertSame(2225600, $aportes->ibc);
        self::assertSame(278200, $aportes->salud);
        self::assertSame(356100, $aportes->pension);
        self::assertSame(11700, $aportes->arl);
        self::assertSame(0, $aportes->fsp);
    }

    /** Desde 4 SMLV de IBC entra el fondo de solidaridad pensional (1 %). */
    public function testFondoDeSolidaridadDesde4Smlv(): void
    {
        $aportes = CalculosCuenta::aportesSugeridos(20000000, '0.00522', self::parametros2026());

        self::assertSame(8000000, $aportes->ibc);
        self::assertSame(80000, $aportes->fsp);
    }

    /** El independiente cotiza mes vencido: periodo de julio, planilla de junio. */
    public function testMesPlanillaSugerido(): void
    {
        self::assertSame('2026-06', CalculosCuenta::mesPlanillaSugerido(new \DateTimeImmutable('2026-07-31')));
        self::assertSame('2025-12', CalculosCuenta::mesPlanillaSugerido(new \DateTimeImmutable('2026-01-27')));
    }

    /** 31 de enero + 1 mes no puede ser 3 de marzo. */
    public function testSumarMesesConTopeDeFinDeMes(): void
    {
        $resultado = CalculosCuenta::sumarMeses(new \DateTimeImmutable('2026-01-31'), 1);

        self::assertSame('2026-02-28', $resultado->format('Y-m-d'));
    }

    public function testDiasEntreCuentaAmbosExtremos(): void
    {
        self::assertSame(
            31,
            CalculosCuenta::diasEntre(new \DateTimeImmutable('2026-08-01'), new \DateTimeImmutable('2026-08-31')),
        );
    }

    public function testDiasEntreRechazaPeriodoInvertido(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CalculosCuenta::diasEntre(new \DateTimeImmutable('2026-08-31'), new \DateTimeImmutable('2026-08-01'));
    }

    public function testFechaTerminacionExigeCuotas(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CalculosCuenta::fechaTerminacion([]);
    }
}

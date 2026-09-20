<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\CuentasCobro;

use App\Modules\CuentasCobro\Service\NumeroALetras;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * §8.4 pide al menos 30 casos. Varios salen de los contratos reales de la
 * muestra (sección 13): si estos valores no cuadran letra por letra, el PDF
 * que firma el contratista dice una cifra distinta de la que cobra.
 */
#[CoversClass(NumeroALetras::class)]
final class NumeroALetrasTest extends TestCase
{
    /** @return array<string, array{int|string, string}> */
    public static function casosPesos(): array
    {
        return [
            'cero' => [0, 'CERO PESOS M/CTE ($ 0)'],
            'un peso' => [1, 'UN PESO M/CTE ($ 1)'],
            'quince' => [15, 'QUINCE PESOS M/CTE ($ 15)'],
            'dieciséis' => [16, 'DIECISÉIS PESOS M/CTE ($ 16)'],
            'veinte' => [20, 'VEINTE PESOS M/CTE ($ 20)'],
            'veintiún con apócope' => [21, 'VEINTIÚN PESOS M/CTE ($ 21)'],
            'veintiséis' => [26, 'VEINTISÉIS PESOS M/CTE ($ 26)'],
            'cien justo' => [100, 'CIEN PESOS M/CTE ($ 100)'],
            'ciento un' => [101, 'CIENTO UN PESOS M/CTE ($ 101)'],
            'ciento once' => [111, 'CIENTO ONCE PESOS M/CTE ($ 111)'],
            'quinientos' => [500, 'QUINIENTOS PESOS M/CTE ($ 500)'],
            'setecientos' => [700, 'SETECIENTOS PESOS M/CTE ($ 700)'],
            'novecientos noventa y nueve' => [999, 'NOVECIENTOS NOVENTA Y NUEVE PESOS M/CTE ($ 999)'],
            'mil, nunca un mil' => [1000, 'MIL PESOS M/CTE ($ 1.000)'],
            'veintiún mil' => [21000, 'VEINTIÚN MIL PESOS M/CTE ($ 21.000)'],
            'treinta y un mil' => [31000, 'TREINTA Y UN MIL PESOS M/CTE ($ 31.000)'],
            'aporte salud muestra' => [218900, 'DOSCIENTOS DIECIOCHO MIL NOVECIENTOS PESOS M/CTE ($ 218.900)'],
            'aporte arl muestra' => [42700, 'CUARENTA Y DOS MIL SETECIENTOS PESOS M/CTE ($ 42.700)'],
            'primer pago 1414' => [583333, 'QUINIENTOS OCHENTA Y TRES MIL TRESCIENTOS TREINTA Y TRES PESOS M/CTE ($ 583.333)'],
            'prorrateo muestra 1060' => [783333, 'SETECIENTOS OCHENTA Y TRES MIL TRESCIENTOS TREINTA Y TRES PESOS M/CTE ($ 783.333)'],
            'ibc antes del piso' => [940000, 'NOVECIENTOS CUARENTA MIL PESOS M/CTE ($ 940.000)'],
            'un millón exacto' => [1000000, 'UN MILLÓN DE PESOS M/CTE ($ 1.000.000)'],
            'primer pago 1409' => [1483733, 'UN MILLÓN CUATROCIENTOS OCHENTA Y TRES MIL SETECIENTOS TREINTA Y TRES PESOS M/CTE ($ 1.483.733)'],
            'smlv 2026' => [1750905, 'UN MILLÓN SETECIENTOS CINCUENTA MIL NOVECIENTOS CINCO PESOS M/CTE ($ 1.750.905)'],
            'cuota 1060' => [2350000, 'DOS MILLONES TRESCIENTOS CINCUENTA MIL PESOS M/CTE ($ 2.350.000)'],
            'cuota 1407' => [2850000, 'DOS MILLONES OCHOCIENTOS CINCUENTA MIL PESOS M/CTE ($ 2.850.000)'],
        ];
    }

    /** @return array<string, array{int|string, string}> */
    public static function casosPesosGrandes(): array
    {
        return [
            'primer pago 1305' => [3333333, 'TRES MILLONES TRESCIENTOS TREINTA Y TRES MIL TRESCIENTOS TREINTA Y TRES PESOS M/CTE ($ 3.333.333)'],
            'cuatro millones exactos' => [4000000, 'CUATRO MILLONES DE PESOS M/CTE ($ 4.000.000)'],
            'cuota 1409' => [5564000, 'CINCO MILLONES QUINIENTOS SESENTA Y CUATRO MIL PESOS M/CTE ($ 5.564.000)'],
            'contrato 1473' => [10600000, 'DIEZ MILLONES SEISCIENTOS MIL PESOS M/CTE ($ 10.600.000)'],
            'contrato 1060 total' => [14100000, 'CATORCE MILLONES CIEN MIL PESOS M/CTE ($ 14.100.000)'],
            'contrato 1407 total' => [14250000, 'CATORCE MILLONES DOSCIENTOS CINCUENTA MIL PESOS M/CTE ($ 14.250.000)'],
            'contrato 1414 total' => [18083333, 'DIECIOCHO MILLONES OCHENTA Y TRES MIL TRESCIENTOS TREINTA Y TRES PESOS M/CTE ($ 18.083.333)'],
            'veintiún millones exactos' => [21000000, 'VEINTIÚN MILLONES DE PESOS M/CTE ($ 21.000.000)'],
            'contrato 1305 total' => [23333333, 'VEINTITRÉS MILLONES TRESCIENTOS TREINTA Y TRES MIL TRESCIENTOS TREINTA Y TRES PESOS M/CTE ($ 23.333.333)'],
            'criterio de aceptación fase 1' => [29303733, 'VEINTINUEVE MILLONES TRESCIENTOS TRES MIL SETECIENTOS TREINTA Y TRES PESOS M/CTE ($ 29.303.733)'],
            'cdp global 1060' => [282000000, 'DOSCIENTOS OCHENTA Y DOS MILLONES DE PESOS M/CTE ($ 282.000.000)'],
            'mil millones' => [1000000000, 'MIL MILLONES DE PESOS M/CTE ($ 1.000.000.000)'],
            'con centavos' => ['2350000.50', 'DOS MILLONES TRESCIENTOS CINCUENTA MIL PESOS CON CINCUENTA CENTAVOS M/CTE ($ 2.350.000,50)'],
            'un solo decimal' => ['10.5', 'DIEZ PESOS CON CINCUENTA CENTAVOS M/CTE ($ 10,50)'],
            'un centavo' => ['10.01', 'DIEZ PESOS CON UN CENTAVO M/CTE ($ 10,01)'],
            'cadena sin decimales' => ['14250000', 'CATORCE MILLONES DOSCIENTOS CINCUENTA MIL PESOS M/CTE ($ 14.250.000)'],
        ];
    }

    #[DataProvider('casosPesos')]
    #[DataProvider('casosPesosGrandes')]
    public function testPesos(int|string $valor, string $esperado): void
    {
        self::assertSame($esperado, NumeroALetras::pesos($valor));
    }

    /** @return array<string, array{int, string}> */
    public static function casosDia(): array
    {
        return [
            'uno' => [1, 'Un (1)'],
            'cinco' => [5, 'Cinco (5)'],
            'dieciséis' => [16, 'Dieciséis (16)'],
            'veintiuno apocopado' => [21, 'Veintiún (21)'],
            'veintisiete, el del formato' => [27, 'Veintisiete (27)'],
            'treinta' => [30, 'Treinta (30)'],
            'treinta y uno apocopado' => [31, 'Treinta y un (31)'],
        ];
    }

    #[DataProvider('casosDia')]
    public function testDia(int $dia, string $esperado): void
    {
        self::assertSame($esperado, NumeroALetras::dia($dia));
    }

    public function testRechazaNegativos(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        NumeroALetras::pesos(-1);
    }

    public function testRechazaCadenasIlegibles(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        NumeroALetras::pesos('2.350.000');
    }

    public function testRechazaDiaFueraDeRango(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        NumeroALetras::dia(32);
    }
}

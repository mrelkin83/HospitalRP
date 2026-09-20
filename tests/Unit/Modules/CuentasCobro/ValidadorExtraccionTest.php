<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\CuentasCobro;

use App\Modules\CuentasCobro\Service\ValidadorExtraccion;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Las alertas puras del validador de extracciones (§10 paso 3), incluida
 * la del criterio de aceptación: el 1473-2026 con «4 meses» en el recuadro
 * y «2 meses» en la cláusula 5.
 */
#[CoversClass(ValidadorExtraccion::class)]
final class ValidadorExtraccionTest extends TestCase
{
    private ValidadorExtraccion $validador;

    protected function setUp(): void
    {
        // Solo se prueban las alertas de datos: la conexión no se usa y
        // una en memoria (sqlite) basta para construir el servicio.
        $this->validador = new ValidadorExtraccion(
            DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]),
        );
    }

    /** @return array<string, mixed> Una extracción sana del 1407-2026. */
    private static function sana(): array
    {
        return [
            'numero_contrato' => '1407-2026',
            'tipo_contrato' => 'PRESTACIÓN DE SERVICIOS DE APOYO A LA GESTIÓN',
            'objeto' => 'Apoyo a la gestión como tecnólogo en sistemas para el mantenimiento de los equipos de cómputo.',
            'valor_total' => 14250000,
            'valor_total_letras' => 'CATORCE MILLONES DOSCIENTOS CINCUENTA MIL PESOS M/CTE',
            'plazo_recuadro' => 'CINCO (5) MESES',
            'plazo_clausula_5' => 'CINCO (5) MESES',
            'cuotas' => array_map(
                static fn (int $n): array => ['numero' => $n, 'valor' => 2850000],
                range(1, 5),
            ),
            'obligaciones_especificas' => [
                'Realizar el mantenimiento preventivo y correctivo de los equipos de cómputo.',
            ],
        ];
    }

    public function testUnaExtraccionSanaNoDisparaAlertas(): void
    {
        self::assertSame([], $this->validador->alertasDeDatos(self::sana()));
    }

    /** Criterio de aceptación: el 1473-2026 y sus plazos contradictorios. */
    public function testDetectaElPlazoContradictorioDel1473(): void
    {
        $r = self::sana();
        $r['plazo_recuadro'] = 'CUATRO (4) MESES';
        $r['plazo_clausula_5'] = 'DOS (2) MESES';
        $r['cuotas'] = array_map(static fn (int $n): array => ['numero' => $n, 'valor' => 2650000], range(1, 4));
        $r['valor_total'] = 10600000;
        $r['valor_total_letras'] = 'DIEZ MILLONES SEISCIENTOS MIL PESOS M/CTE';

        $alertas = $this->validador->alertasDeDatos($r);

        self::assertNotEmpty(array_filter(
            $alertas,
            static fn (string $a): bool => str_contains($a, 'recuadro') && str_contains($a, 'cláusula 5'),
        ), 'No detectó el plazo contradictorio: ' . implode(' | ', $alertas));
    }

    public function testDetectaCuotasQueNoSumanElTotal(): void
    {
        $r = self::sana();
        $r['cuotas'][0]['valor'] = 2000000;

        $alertas = $this->validador->alertasDeDatos($r);

        self::assertNotEmpty(array_filter(
            $alertas,
            static fn (string $a): bool => str_contains($a, 'suma de las cuotas'),
        ));
    }

    public function testDetectaLetrasQueNoCoincidenConElNumero(): void
    {
        $r = self::sana();
        $r['valor_total_letras'] = 'QUINCE MILLONES DE PESOS M/CTE';

        $alertas = $this->validador->alertasDeDatos($r);

        self::assertNotEmpty(array_filter(
            $alertas,
            static fn (string $a): bool => str_contains($a, 'letras'),
        ));
    }

    public function testDetectaObjetoProfesionalConTipoApoyo(): void
    {
        $r = self::sana();
        $r['objeto'] = 'Prestación de servicios profesionales como ingeniero de sistemas para el mantenimiento de equipos.';

        $alertas = $this->validador->alertasDeDatos($r);

        self::assertNotEmpty(array_filter(
            $alertas,
            static fn (string $a): bool => str_contains($a, 'clasificación'),
        ));
    }

    public function testDetectaCuotasIncoherentesConElPlazo(): void
    {
        $r = self::sana();
        $r['cuotas'] = array_map(static fn (int $n): array => ['numero' => $n, 'valor' => 1425000], range(1, 10));

        $alertas = $this->validador->alertasDeDatos($r);

        self::assertNotEmpty(array_filter(
            $alertas,
            static fn (string $a): bool => str_contains($a, 'cronograma trae'),
        ));
    }
}

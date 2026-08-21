<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Tramites;

use App\Modules\Tramites\Service\FestivosColombia;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verificado contra el calendario oficial: si el algoritmo se equivoca aquí,
 * se equivocan los vencimientos legales de todos los trámites.
 */
#[CoversClass(FestivosColombia::class)]
final class FestivosColombiaTest extends TestCase
{
    public function testFestivos2026(): void
    {
        $festivos = FestivosColombia::delAnio(2026);

        // Fijos.
        self::assertArrayHasKey('2026-01-01', $festivos);
        self::assertArrayHasKey('2026-07-20', $festivos);
        self::assertArrayHasKey('2026-12-25', $festivos);

        // Emiliani: Reyes (6 de enero, martes) se corre al lunes 12.
        self::assertArrayHasKey('2026-01-12', $festivos);
        self::assertArrayNotHasKey('2026-01-06', $festivos);

        // San Pedro y San Pablo cae lunes 29 de junio: no se corre.
        self::assertArrayHasKey('2026-06-29', $festivos);

        // Pascua 2026: domingo 5 de abril → Jueves y Viernes Santo.
        self::assertSame('Jueves Santo', $festivos['2026-04-02'] ?? null);
        self::assertSame('Viernes Santo', $festivos['2026-04-03'] ?? null);

        // 18 festivos oficiales al año.
        self::assertCount(18, $festivos);
    }

    public function testFestivos2027(): void
    {
        $festivos = FestivosColombia::delAnio(2027);

        // Pascua 2027: domingo 28 de marzo.
        self::assertSame('Viernes Santo', $festivos['2027-03-26'] ?? null);
        // San Pedro (29 de junio, martes) se corre al lunes 5 de julio.
        self::assertArrayHasKey('2027-07-05', $festivos);
        // Todos los Santos (1 de noviembre) cae lunes: no se corre.
        self::assertArrayHasKey('2027-11-01', $festivos);
        self::assertCount(18, $festivos);
    }
}

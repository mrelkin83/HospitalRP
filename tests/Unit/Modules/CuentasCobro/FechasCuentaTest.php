<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\CuentasCobro;

use App\Modules\CuentasCobro\Service\FechasCuenta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Los tres giros de periodo son los que traen los documentos reales; en un
 * PDF que firma el supervisor no se admite «01/08/2026 - 31/08/2026».
 */
#[CoversClass(FechasCuenta::class)]
final class FechasCuentaTest extends TestCase
{
    public function testFechaLarga(): void
    {
        self::assertSame('28 de enero de 2026', FechasCuenta::larga(new \DateTimeImmutable('2026-01-28')));
        self::assertSame('5 de agosto de 2026', FechasCuenta::larga(new \DateTimeImmutable('2026-08-05')));
    }

    public function testPeriodoDentroDelMismoMes(): void
    {
        self::assertSame(
            'del 1 al 31 de agosto de 2026',
            FechasCuenta::periodo(new \DateTimeImmutable('2026-08-01'), new \DateTimeImmutable('2026-08-31')),
        );
    }

    public function testPeriodoEntreMesesDelMismoAnio(): void
    {
        self::assertSame(
            'del 28 de junio al 8 de julio de 2026',
            FechasCuenta::periodo(new \DateTimeImmutable('2026-06-28'), new \DateTimeImmutable('2026-07-08')),
        );
    }

    public function testPeriodoEntreAniosDistintos(): void
    {
        self::assertSame(
            'del 28 de diciembre de 2026 al 27 de enero de 2027',
            FechasCuenta::periodo(new \DateTimeImmutable('2026-12-28'), new \DateTimeImmutable('2027-01-27')),
        );
    }

    public function testMes(): void
    {
        self::assertSame('julio', FechasCuenta::mes(new \DateTimeImmutable('2026-07-15')));
    }

    public function testDesdeMysql(): void
    {
        $f = FechasCuenta::desdeMysql('2026-08-01');

        self::assertSame('2026-08-01 00:00:00', $f->format('Y-m-d H:i:s'));
    }

    public function testDesdeMysqlRechazaBasura(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        FechasCuenta::desdeMysql('01/08/2026');
    }
}

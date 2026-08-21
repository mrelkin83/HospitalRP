<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tramites;

use App\Modules\Tramites\Service\CalculadoraDiasHabiles;
use App\Modules\Tramites\Service\FestivosColombia;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosDePruebas;

/**
 * Criterio de la Fase 5: el vencimiento salta correctamente sobre fines de
 * semana y festivos.
 */
#[CoversClass(CalculadoraDiasHabiles::class)]
final class CalculadoraDiasHabilesTest extends TestCase
{
    private static Connection $conexion;
    private CalculadoraDiasHabiles $calculadora;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM festivos');

        foreach ([2026, 2027] as $anio) {
            foreach (FestivosColombia::delAnio($anio) as $fecha => $nombre) {
                self::$conexion->insert('festivos', ['fecha' => $fecha, 'nombre' => $nombre]);
            }
        }

        $this->calculadora = new CalculadoraDiasHabiles(self::$conexion);
    }

    public function testElTerminoEmpiezaElDiaSiguienteYSaltaElFinDeSemana(): void
    {
        // Viernes 21 de agosto de 2026 + 1 día hábil = lunes 24.
        $vence = $this->calculadora->vencimiento(new DateTimeImmutable('2026-08-21'), 1);

        self::assertSame('2026-08-24', $vence->format('Y-m-d'));
    }

    public function testSaltaLosFestivos(): void
    {
        // Miércoles 1 de abril de 2026 + 2 hábiles: jueves 2 y viernes 3 son
        // Semana Santa (festivos) → lunes 6 y martes 7.
        $vence = $this->calculadora->vencimiento(new DateTimeImmutable('2026-04-01'), 2);

        self::assertSame('2026-04-07', $vence->format('Y-m-d'));
    }

    public function testQuinceDiasHabilesDeUnaPeticionRealDeLey1755(): void
    {
        // Radicada el viernes 26 de junio de 2026. El lunes 29 es festivo
        // (San Pedro), el 20 de julio (lunes) es festivo.
        $vence = $this->calculadora->vencimiento(new DateTimeImmutable('2026-06-26'), 15);

        // Hábiles: jun 30, jul 1,2,3, 6,7,8,9,10, 13,14,15,16,17, 21 → 21 de julio.
        self::assertSame('2026-07-21', $vence->format('Y-m-d'));
    }

    public function testDiasCalendarioQueVencenEnInhabilCorrenAlHabilSiguiente(): void
    {
        // 10 días calendario desde el jueves 13 de agosto de 2026 = 23 (domingo)
        // → lunes 24.
        $vence = $this->calculadora->vencimiento(new DateTimeImmutable('2026-08-13'), 10, habiles: false);

        self::assertSame('2026-08-24', $vence->format('Y-m-d'));
    }

    public function testHabilesEntreCuentaLoTranscurrido(): void
    {
        // Del viernes 14 al viernes 21 de agosto de 2026: el lunes 17 es
        // festivo (Asunción trasladada) → 18, 19, 20 y 21 = 4 hábiles.
        self::assertSame(4, $this->calculadora->habilesEntre(
            new DateTimeImmutable('2026-08-14'),
            new DateTimeImmutable('2026-08-21'),
        ));
    }
}

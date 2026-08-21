<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tramites;

use App\Modules\Tramites\Service\GeneradorRadicado;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosDePruebas;

/**
 * Criterio de la Fase 5: el radicado es único bajo concurrencia. Se prueba
 * con procesos PHP REALES en paralelo, no con un mutex de mentira.
 */
#[CoversClass(GeneradorRadicado::class)]
final class GeneradorRadicadoTest extends TestCase
{
    private static Connection $conexion;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM radicado_secuencias');
    }

    public function testElFormatoYLaSecuenciaSonLosDelGlosario(): void
    {
        $generador = new GeneradorRadicado(self::$conexion);

        self::assertSame('HRPL-2099-000001', $generador->siguiente('HRPL', 2099));
        self::assertSame('HRPL-2099-000002', $generador->siguiente('HRPL', 2099));
        // Cada año y prefijo llevan su propia cuenta.
        self::assertSame('HRPL-2100-000001', $generador->siguiente('HRPL', 2100));
        self::assertSame('PQR-2099-000001', $generador->siguiente('PQR', 2099));
    }

    public function testEsUnicoBajoConcurrenciaReal(): void
    {
        $procesos = 4;
        $porProceso = 25;
        $php = PHP_BINARY;
        $script = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'genera-radicados.php';

        $entorno = array_merge($_SERVER, [
            'DB_HOST' => (string) ($_ENV['DB_HOST'] ?? '127.0.0.1'),
            'DB_PUERTO' => (string) ($_ENV['DB_PUERTO'] ?? '3306'),
            'DB_NOMBRE' => (string) ($_ENV['DB_NOMBRE'] ?? 'hrpl_pruebas'),
            'DB_USUARIO' => (string) ($_ENV['DB_USUARIO'] ?? 'root'),
        ]);
        $entorno = array_filter($entorno, is_string(...));

        $abiertos = [];

        for ($i = 0; $i < $procesos; $i++) {
            $proceso = proc_open(
                [$php, $script, (string) $porProceso],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $tuberias,
                null,
                $entorno,
            );
            self::assertIsResource($proceso);
            $abiertos[] = [$proceso, $tuberias];
        }

        $radicados = [];

        foreach ($abiertos as [$proceso, $tuberias]) {
            $salida = (string) stream_get_contents($tuberias[1]);
            $errores = (string) stream_get_contents($tuberias[2]);
            fclose($tuberias[1]);
            fclose($tuberias[2]);
            $codigo = proc_close($proceso);

            self::assertSame(0, $codigo, 'El trabajador falló: ' . $errores);

            foreach (array_filter(explode("\n", trim($salida))) as $linea) {
                $radicados[] = trim($linea);
            }
        }

        $total = $procesos * $porProceso;
        self::assertCount($total, $radicados);
        self::assertCount($total, array_unique($radicados), 'Hubo radicados duplicados bajo concurrencia.');

        // La secuencia final coincide con el total emitido: no hay huecos
        // fantasma ni saltos.
        $ultimo = (int) self::$conexion->fetchOne(
            "SELECT ultimo FROM radicado_secuencias WHERE prefijo = 'CONC' AND anio = 2099",
        );
        self::assertSame($total, $ultimo);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Config;

use App\Core\Config\ConfigDinamica;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosDePruebas;

#[CoversClass(ConfigDinamica::class)]
final class ConfigDinamicaTest extends TestCase
{
    private static Connection $conexion;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM configuracion');
    }

    public function testLeeUnValorYUsaElPorDefectoCuandoFalta(): void
    {
        self::$conexion->insert('configuracion', [
            'clave' => 'contacto.pbx',
            'valor' => '(605) 999 0000',
            'tipo' => 'texto',
            'grupo' => 'contacto',
            'etiqueta' => 'PBX',
        ]);

        $config = new ConfigDinamica(self::$conexion);

        self::assertSame('(605) 999 0000', $config->texto('contacto.pbx'));
        self::assertSame('sin dato', $config->texto('clave.inexistente', 'sin dato'));
    }

    /**
     * Un valor vacío guardado desde el panel no debe dejar un hueco en el
     * sitio: cae al por defecto igual que si la clave no existiera.
     */
    public function testUnValorVacioCaeAlPorDefecto(): void
    {
        self::$conexion->insert('configuracion', [
            'clave' => 'redes.facebook',
            'valor' => '',
            'tipo' => 'texto',
            'grupo' => 'redes',
            'etiqueta' => 'Facebook',
        ]);

        $config = new ConfigDinamica(self::$conexion);

        self::assertSame('—', $config->texto('redes.facebook', '—'));
    }

    public function testLeeBooleanos(): void
    {
        self::$conexion->insert('configuracion', [
            'clave' => 'sitio.modo_emergencia',
            'valor' => '1',
            'tipo' => 'booleano',
            'grupo' => 'sitio',
            'etiqueta' => 'Modo emergencia',
        ]);

        $config = new ConfigDinamica(self::$conexion);

        self::assertTrue($config->booleano('sitio.modo_emergencia'));
        self::assertFalse($config->booleano('sitio.otra_cosa', false));
    }
}

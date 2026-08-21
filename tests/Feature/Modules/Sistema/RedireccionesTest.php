<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Sistema;

use App\Modules\Sistema\Repository\RedireccionRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosDePruebas;

/**
 * Criterio de la Fase 7: una URL vieja del Joomla responde 301 hacia la
 * nueva. El exacto gana sobre el prefijo; entre prefijos gana el más largo.
 */
#[CoversClass(RedireccionRepository::class)]
final class RedireccionesTest extends TestCase
{
    private static Connection $conexion;
    private RedireccionRepository $redirecciones;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM redirecciones');
        $this->redirecciones = new RedireccionRepository(self::$conexion);
    }

    public function testElOrigenExactoGanaSobreElPrefijo(): void
    {
        $this->redirecciones->crearSiNoExiste('/sitio/index.php/es/transparencia', '/transparencia');
        $this->redirecciones->crearSiNoExiste('/sitio/*', '/');

        $destino = $this->redirecciones->destinoPara('/sitio/index.php/es/transparencia');

        self::assertNotNull($destino);
        self::assertSame('/transparencia', $destino['destino']);
        self::assertSame(301, $destino['codigo']);
    }

    public function testEntrePrefijosGanaElMasLargo(): void
    {
        $this->redirecciones->crearSiNoExiste('/sitio/*', '/');
        $this->redirecciones->crearSiNoExiste('/sitio/index.php/es/normativa/*', '/transparencia/normatividad');

        $destino = $this->redirecciones->destinoPara('/sitio/index.php/es/normativa/decretos-2019');

        self::assertNotNull($destino);
        self::assertSame('/transparencia/normatividad', $destino['destino']);
    }

    public function testUnaRutaSinRegistroNoRedirige(): void
    {
        $this->redirecciones->crearSiNoExiste('/sitio/*', '/');

        self::assertNull($this->redirecciones->destinoPara('/otra-cosa'));
    }

    public function testUnaRedireccionInactivaNoAplica(): void
    {
        $this->redirecciones->crearSiNoExiste('/vieja', '/nueva');
        self::$conexion->executeStatement('UPDATE redirecciones SET activa = 0');

        self::assertNull($this->redirecciones->destinoPara('/vieja'));
    }

    public function testElHitQuedaContado(): void
    {
        $this->redirecciones->crearSiNoExiste('/vieja', '/nueva');
        $destino = $this->redirecciones->destinoPara('/vieja');

        self::assertNotNull($destino);
        $this->redirecciones->registrarHit($destino['id']);
        $this->redirecciones->registrarHit($destino['id']);

        $fila = self::$conexion->fetchAssociative('SELECT hits, ultimo_hit FROM redirecciones WHERE id = ?', [$destino['id']]);
        self::assertNotFalse($fila);
        self::assertSame(2, (int) $fila['hits']);
        self::assertNotNull($fila['ultimo_hit']);
    }

    public function testLaSemillaEsIdempotenteYNoPisaLoEditado(): void
    {
        self::assertTrue($this->redirecciones->crearSiNoExiste('/vieja', '/nueva'));
        self::$conexion->executeStatement("UPDATE redirecciones SET destino = '/editada' WHERE origen = '/vieja'");

        self::assertFalse($this->redirecciones->crearSiNoExiste('/vieja', '/nueva'));

        $destino = $this->redirecciones->destinoPara('/vieja');
        self::assertNotNull($destino);
        self::assertSame('/editada', $destino['destino']);
    }
}

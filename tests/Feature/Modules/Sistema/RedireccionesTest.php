<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Sistema;

use App\Modules\Sistema\Command\SembrarRedireccionesCommand;
use App\Modules\Sistema\Repository\RedireccionRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
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

    /**
     * Defecto de docs/14 §5.2: la semilla mapeaba `/el-hospital`, `/prensa` y
     * `/servicios`, prefijos que NO existen en el Joomla. Estas URL son las
     * medidas en el rastreo del 2026-09-14; ninguna puede caer en el comodín
     * `/sitio/*` → `/`, porque eso pierde el destino específico.
     *
     * @return list<array{string, string}>
     */
    public static function urlRealesDelJoomla(): array
    {
        return [
            ['/sitio/index.php/es/nuestro-hospital/historia', '/el-hospital'],
            ['/sitio/index.php/es/nuestro-hospital/organigrama', '/el-hospital'],
            ['/sitio/index.php/es/prensahrpl/boletines-prensa', '/noticias'],
            ['/sitio/index.php/es/prensahrpl/galeria', '/noticias'],
            ['/sitio/index.php/es/servicio-al-ciudadano/servicios', '/servicios'],
            ['/sitio/index.php/es/servicio-al-ciudadano/glosario', '/atencion-ciudadania'],
            ['/sitio/index.php/es/rr-hh/manuales-de-funciones', '/transparencia'],
            ['/sitio/index.php/es/iamii/pasosiamii/paso-2', '/servicios'],
            ['/sitio/index.php/es/niveles-de-atencion', '/el-hospital/niveles-de-atencion'],
            ['/sitio/index.php/es/participa', '/participa'],
            ['/sitio/index.php/es/mapa-del-sitio', '/mapa-del-sitio'],
            ['/sitio/index.php/es/meci', '/transparencia'],
            ['/sitio/index.php/es/contratacion/licitaciones', '/transparencia/contratacion'],
            ['/sitio/index.php/es/peticiones-quejas-y-reclamos/299-peticiones', '/atencion-ciudadania/pqrsdf'],
        ];
    }

    #[DataProvider('urlRealesDelJoomla')]
    public function testCadaPrefijoRealDelJoomlaTieneDestinoEspecifico(string $origen, string $esperado): void
    {
        (new CommandTester(new SembrarRedireccionesCommand($this->redirecciones)))->execute([]);

        $destino = $this->redirecciones->destinoPara($origen);

        self::assertNotNull($destino, sprintf('%s quedó sin redirección', $origen));
        self::assertSame($esperado, $destino['destino'], sprintf('%s cayó en el destino equivocado', $origen));
        self::assertSame(301, $destino['codigo']);
    }
}

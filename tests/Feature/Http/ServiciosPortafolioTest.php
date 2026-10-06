<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Core\Config\ConfigAplicacion;
use App\Core\Config\Env;
use App\Http\Kernel;
use App\Http\Web\ServiciosPublicoController;
use App\Modules\Directorio\Entity\Servicio;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouteCollection;
use Tests\Support\BaseDatosDePruebas;

/**
 * Lo que el Portafolio de Servicios 2026 trajo a `/servicios`: la foto de cada
 * servicio y sus cifras («269 camas», «8 quirófanos»), que antes solo existían
 * dentro de un PDF de diapositivas.
 *
 * Lo que se vigila aquí es que esas dos cosas lleguen al HTML —la rejilla y el
 * detalle— y que la foto salga siempre con su texto alternativo: una imagen
 * sin `alt` en el sitio de un hospital es una barrera, no un detalle estético
 * (CLAUDE.md §2.5).
 */
#[CoversClass(ServiciosPublicoController::class)]
#[CoversClass(Servicio::class)]
final class ServiciosPortafolioTest extends TestCase
{
    private static Connection $conexion;
    private Kernel $kernel;
    private int $archivoId;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM servicio_sede');
        self::$conexion->executeStatement('DELETE FROM servicios');

        self::$conexion->insert('archivos', [
            'nombre_original' => 'hospitalizacion.webp',
            'ruta' => '2026/10/prueba-portafolio.webp',
            'mime' => 'image/webp',
            'extension' => 'webp',
            'tamano' => 1024,
            'hash_sha256' => str_repeat('a', 64),
            'ancho' => 1600,
            'alto' => 901,
            'texto_alt' => 'Unidad de cuidados intensivos neonatal con incubadoras y monitores',
            'publico' => 1,
        ]);
        $this->archivoId = (int) self::$conexion->lastInsertId();

        $raiz = dirname(__DIR__, 3);
        $env = Env::desdeSuperglobales();

        /** @var callable(Env): ContainerBuilder $fabrica */
        $fabrica = require $raiz . '/config/servicios.php';
        $contenedor = $fabrica($env);

        /** @var ConfigAplicacion $config */
        $config = $contenedor->get(ConfigAplicacion::class);

        /** @var RouteCollection $rutas */
        $rutas = require $raiz . '/config/rutas.php';

        $this->kernel = new Kernel($contenedor, $rutas, $config);
    }

    protected function tearDown(): void
    {
        self::$conexion->executeStatement('DELETE FROM servicio_sede');
        self::$conexion->executeStatement('DELETE FROM servicios');
        self::$conexion->executeStatement('DELETE FROM archivos WHERE id = ?', [$this->archivoId]);
    }

    public function testLaRejillaMuestraLaFotoConSuTextoAlternativo(): void
    {
        $this->crearServicio(conFoto: true);

        $cuerpo = $this->cuerpoDe('/servicios');

        self::assertStringContainsString('src="/archivos/' . $this->archivoId . '"', $cuerpo);
        self::assertStringContainsString('alt="Unidad de cuidados intensivos neonatal', $cuerpo);
    }

    public function testLaRejillaMuestraLasCifrasDelPortafolio(): void
    {
        $this->crearServicio(conFoto: true);

        $cuerpo = $this->cuerpoDe('/servicios');

        self::assertStringContainsString('269', $cuerpo);
        self::assertStringContainsString('camas habilitadas', $cuerpo);
    }

    public function testElDetalleTraeTodasLasCifras(): void
    {
        $this->crearServicio(conFoto: true);

        $cuerpo = $this->cuerpoDe('/servicios/hospitalizacion');

        self::assertStringContainsString('269', $cuerpo);
        self::assertStringContainsString('camas habilitadas', $cuerpo);
        self::assertStringContainsString('8', $cuerpo);
        self::assertStringContainsString('camas de UCI adultos', $cuerpo);
    }

    /**
     * Los servicios que el portafolio no ilustra —laboratorio de genética,
     * terapias— siguen sin foto. La rejilla no puede quedarse en blanco ni
     * pedir un archivo que no existe por ellos.
     */
    public function testUnServicioSinFotoSeSigueMostrando(): void
    {
        $this->crearServicio(conFoto: false);

        $cuerpo = $this->cuerpoDe('/servicios');

        self::assertStringContainsString('Servicios de hospitalización', $cuerpo);
        self::assertStringNotContainsString('src="/archivos/', $cuerpo);
    }

    public function testUnDestacadoMalGuardadoNoTumbaLaPagina(): void
    {
        $this->crearServicio(conFoto: true, destacados: '{"esto":"no es una lista"}');

        $respuesta = $this->kernel->manejar(Request::create('/servicios/hospitalizacion'));

        self::assertSame(Response::HTTP_OK, $respuesta->getStatusCode());
        self::assertStringContainsString('Servicios de hospitalización', (string) $respuesta->getContent());
    }

    private function crearServicio(bool $conFoto, ?string $destacados = null): void
    {
        self::$conexion->insert('servicios', [
            'nombre' => 'Servicios de hospitalización',
            'slug' => 'hospitalizacion',
            'descripcion' => 'Brindamos atención integral al paciente que requiere de internación.',
            'destacados' => $destacados ?? json_encode([
                ['cifra' => '269', 'texto' => 'camas habilitadas'],
                ['cifra' => '8', 'texto' => 'camas de UCI adultos'],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'imagen_id' => $conFoto ? $this->archivoId : null,
            'orden' => 1,
            'activo' => 1,
        ]);
    }

    private function cuerpoDe(string $ruta): string
    {
        $respuesta = $this->kernel->manejar(Request::create($ruta));

        self::assertSame(Response::HTTP_OK, $respuesta->getStatusCode());

        return (string) $respuesta->getContent();
    }
}

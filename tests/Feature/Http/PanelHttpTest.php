<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Core\Auth\Sesion;
use App\Core\Auth\Usuario;
use App\Core\Config\ConfigAplicacion;
use App\Core\Config\Env;
use App\Http\Kernel;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouteCollection;
use Tests\Support\BaseDatosDePruebas;

/**
 * El panel por HTTP con las rutas y el contenedor reales (Fase 0, paso 10):
 * login renderizado con el sistema de diseño, redirección sin sesión y
 * cascarón con sesión.
 */
#[CoversNothing]
final class PanelHttpTest extends TestCase
{
    private static Connection $conexion;
    private ContainerBuilder $contenedor;
    private Kernel $kernel;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        $raiz = dirname(__DIR__, 3);
        $env = Env::desdeSuperglobales();

        /** @var callable(Env): ContainerBuilder $fabricaContenedor */
        $fabricaContenedor = require $raiz . '/config/servicios.php';
        $this->contenedor = $fabricaContenedor($env);

        /** @var ConfigAplicacion $config */
        $config = $this->contenedor->get(ConfigAplicacion::class);

        /** @var RouteCollection $rutas */
        $rutas = require $raiz . '/config/rutas.php';

        $this->kernel = new Kernel($this->contenedor, $rutas, $config);
    }

    public function testElFormularioDeLoginTieneCsrfEtiquetasYAutocompletado(): void
    {
        $respuesta = $this->kernel->manejar(Request::create('/admin/entrar'));

        self::assertSame(Response::HTTP_OK, $respuesta->getStatusCode());

        $html = (string) $respuesta->getContent();
        self::assertStringContainsString('name="_token"', $html);
        self::assertStringContainsString('<label class="etiqueta" for="correo"', $html);
        self::assertStringContainsString('autocomplete="current-password"', $html);
        self::assertStringContainsString('/build/app.css', $html);
        // Página con sesión o formulario: jamás se cachea.
        self::assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));
    }

    public function testElPanelSinSesionRedirigeAlLogin(): void
    {
        $respuesta = $this->kernel->manejar(Request::create('/admin'));

        self::assertSame(Response::HTTP_SEE_OTHER, $respuesta->getStatusCode());
        self::assertSame('/admin/entrar', $respuesta->headers->get('Location'));
    }

    public function testConSesionElPanelMuestraElCascaron(): void
    {
        /** @var Sesion $sesion */
        $sesion = $this->contenedor->get(Sesion::class);
        $sesion->iniciar();
        $sesion->autenticar(new Usuario(
            id: 1,
            nombre: 'María Pérez',
            correo: 'maria@hospital.gov.co',
            hashPassword: '',
            rolId: 1,
            activo: true,
            intentosFallidos: 0,
            bloqueadoHasta: null,
            debeCambiarPassword: false,
        ));

        $respuesta = $this->kernel->manejar(Request::create('/admin'));

        self::assertSame(Response::HTTP_OK, $respuesta->getStatusCode());

        $html = (string) $respuesta->getContent();
        self::assertStringContainsString('María Pérez', $html);
        self::assertStringContainsString('Cerrar sesión', $html);
        self::assertStringContainsString('Saltar al contenido', $html);
        self::assertStringContainsString('action="/admin/salir"', $html);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Core\Config\ConfigAplicacion;
use App\Core\Config\Env;
use App\Http\Exception\ControladorInvalidoException;
use App\Http\Kernel;
use App\Http\Web\InicioController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

#[CoversClass(Kernel::class)]
#[CoversClass(InicioController::class)]
#[CoversClass(ControladorInvalidoException::class)]
final class KernelTest extends TestCase
{
    public function testRespondeLaRutaDeInicio(): void
    {
        $respuesta = $this->kernel()->manejar(Request::create('/'));

        self::assertSame(Response::HTTP_OK, $respuesta->getStatusCode());
        self::assertStringContainsString('<html lang="es">', (string) $respuesta->getContent());
    }

    public function testDevuelve404EnRutaDesconocida(): void
    {
        $respuesta = $this->kernel()->manejar(Request::create('/ruta-que-no-existe'));

        self::assertSame(Response::HTTP_NOT_FOUND, $respuesta->getStatusCode());
    }

    public function testDevuelve405CuandoElMetodoNoEstaPermitido(): void
    {
        $respuesta = $this->kernel()->manejar(Request::create('/', 'POST'));

        self::assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $respuesta->getStatusCode());
    }

    /**
     * Las páginas de error también son páginas del hospital: si no declaran
     * idioma ni tienen un h1, un lector de pantalla las lee mal (WCAG 2.1 AA).
     */
    public function testLasPaginasDeErrorSonAccesiblesYNoSeIndexan(): void
    {
        $contenido = (string) $this->kernel()->manejar(Request::create('/no-existe'))->getContent();

        self::assertStringContainsString('<html lang="es">', $contenido);
        self::assertStringContainsString('<h1>', $contenido);
        self::assertStringContainsString('name="robots" content="noindex"', $contenido);
    }

    /**
     * Estas cabeceras las exige CLAUDE.md §6 y el Anexo 3 de la Res. 1519.
     * Se verifican en una prueba para que nadie las quite sin enterarse.
     */
    public function testAplicaLasCabecerasDeSeguridadEnTodaRespuesta(): void
    {
        $cabeceras = $this->kernel()->manejar(Request::create('/'))->headers;

        self::assertSame('nosniff', $cabeceras->get('X-Content-Type-Options'));
        self::assertSame('DENY', $cabeceras->get('X-Frame-Options'));
        self::assertSame('strict-origin-when-cross-origin', $cabeceras->get('Referrer-Policy'));
        self::assertSame('same-origin', $cabeceras->get('Cross-Origin-Opener-Policy'));
        self::assertNotNull($cabeceras->get('Permissions-Policy'));

        $csp = (string) $cabeceras->get('Content-Security-Policy');
        self::assertStringContainsString("default-src 'self'", $csp);
        self::assertStringContainsString("frame-ancestors 'none'", $csp);
        self::assertStringContainsString("object-src 'none'", $csp);
        self::assertStringNotContainsString('unsafe-inline', $csp);
        self::assertStringNotContainsString('unsafe-eval', $csp);
    }

    public function testLasCabecerasDeSeguridadTambienViajanEnLosErrores(): void
    {
        $cabeceras = $this->kernel()->manejar(Request::create('/no-existe'))->headers;

        self::assertSame('nosniff', $cabeceras->get('X-Content-Type-Options'));
        self::assertNotNull($cabeceras->get('Content-Security-Policy'));
    }

    /**
     * HSTS por HTTP en desarrollo dejaría el dominio local inaccesible en el
     * navegador durante un año. En producción, en cambio, es obligatorio.
     */
    public function testHstsSoloSeEnviaEnProduccion(): void
    {
        $enLocal = $this->kernel()->manejar(Request::create('/'))->headers;
        self::assertFalse($enLocal->has('Strict-Transport-Security'));

        $enProduccion = $this->kernel('produccion')->manejar(Request::create('/'))->headers;
        self::assertStringContainsString(
            'max-age=31536000',
            (string) $enProduccion->get('Strict-Transport-Security'),
        );
    }

    /**
     * En producción el ciudadano no puede ver jamás la excepción: expondría
     * rutas del servidor, credenciales y estructura de tablas.
     */
    public function testEnProduccionElErrorNoFiltraDetallesTecnicos(): void
    {
        $rutas = new RouteCollection();
        $rutas->add('rota', new Route('/rota', ['_controlador' => ['App\NoExiste', 'index']]));

        $respuesta = $this->kernel('produccion', $rutas)->manejar(Request::create('/rota'));

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $respuesta->getStatusCode());

        $contenido = (string) $respuesta->getContent();
        self::assertStringNotContainsString('App\NoExiste', $contenido);
        self::assertStringNotContainsString('.php', $contenido);
        self::assertStringContainsString('Estamos presentando una falla técnica', $contenido);
    }

    public function testEnLocalElErrorSiMuestraElDetalleParaDepurar(): void
    {
        $rutas = new RouteCollection();
        $rutas->add('rota', new Route('/rota', ['_controlador' => ['App\NoExiste', 'index']]));

        $contenido = (string) $this->kernel('local', $rutas)
            ->manejar(Request::create('/rota'))
            ->getContent();

        self::assertStringContainsString('ControladorInvalidoException', $contenido);
    }

    /**
     * Monta el kernel con el contenedor y las rutas reales de `config/`, no con
     * un doble. Así la prueba falla si alguien rompe el cableado de producción.
     */
    private function kernel(string $entorno = 'local', ?RouteCollection $rutas = null): Kernel
    {
        $raiz = dirname(__DIR__, 3);

        // Las páginas públicas leen menús, configuración y avisos de la base:
        // las credenciales de la base de pruebas vienen de phpunit.xml.
        $env = new Env([
            'APP_ENTORNO' => $entorno,
            'APP_DEBUG' => 'true',
            'APP_URL' => 'http://localhost',
            'DB_HOST' => (string) ($_ENV['DB_HOST'] ?? '127.0.0.1'),
            'DB_PUERTO' => (string) ($_ENV['DB_PUERTO'] ?? '3306'),
            'DB_NOMBRE' => (string) ($_ENV['DB_NOMBRE'] ?? 'hrpl_pruebas'),
            'DB_USUARIO' => (string) ($_ENV['DB_USUARIO'] ?? 'root'),
        ]);

        /** @var callable(Env): ContainerBuilder $fabricaContenedor */
        $fabricaContenedor = require $raiz . '/config/servicios.php';
        $contenedor = $fabricaContenedor($env);

        /** @var ConfigAplicacion $config */
        $config = $contenedor->get(ConfigAplicacion::class);

        if ($rutas === null) {
            /** @var RouteCollection $rutas */
            $rutas = require $raiz . '/config/rutas.php';
        }

        return new Kernel($contenedor, $rutas, $config);
    }
}

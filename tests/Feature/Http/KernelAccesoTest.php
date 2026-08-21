<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Core\Auth\Sesion;
use App\Core\Auth\Usuario;
use App\Core\Config\ConfigAplicacion;
use App\Core\Config\Env;
use App\Http\Kernel;
use App\Http\Web\InicioController;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Tests\Support\BaseDatosDePruebas;

/**
 * Control de acceso declarado en las rutas (Fase 0, paso 8): sin sesión se
 * redirige al login; con sesión pero sin permiso se recibe 403.
 */
#[CoversClass(Kernel::class)]
final class KernelAccesoTest extends TestCase
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

        self::$conexion->executeStatement('DELETE FROM usuarios');
        self::$conexion->executeStatement("DELETE FROM roles WHERE slug LIKE 'prueba-%'");

        $raiz = dirname(__DIR__, 3);
        // Env con superglobales: incluye las DB_* de phpunit.xml, porque la
        // autorización consulta la base de pruebas de verdad.
        $env = Env::desdeSuperglobales();

        /** @var callable(Env): ContainerBuilder $fabricaContenedor */
        $fabricaContenedor = require $raiz . '/config/servicios.php';
        $this->contenedor = $fabricaContenedor($env);

        /** @var ConfigAplicacion $config */
        $config = $this->contenedor->get(ConfigAplicacion::class);

        $rutas = new RouteCollection();
        $rutas->add('privada', new Route('/privada', [
            '_controlador' => [InicioController::class, 'index'],
            '_acceso' => 'privado',
        ]));
        $rutas->add('restringida', new Route('/restringida', [
            '_controlador' => [InicioController::class, 'index'],
            '_acceso' => 'privado',
            '_permiso' => 'sistema.usuarios.ver',
        ]));

        $this->kernel = new Kernel($this->contenedor, $rutas, $config);
    }

    public function testSinSesionRedirigeAlLogin(): void
    {
        $respuesta = $this->kernel->manejar(Request::create('/privada'));

        self::assertSame(Response::HTTP_SEE_OTHER, $respuesta->getStatusCode());
        self::assertSame('/admin/entrar', $respuesta->headers->get('Location'));
    }

    public function testConSesionPeroSinElPermisoRecibe403(): void
    {
        $this->autenticar($this->crearUsuarioConPermisos(['contenido.*']));

        $respuesta = $this->kernel->manejar(Request::create('/restringida'));

        self::assertSame(Response::HTTP_FORBIDDEN, $respuesta->getStatusCode());
        self::assertStringContainsString('Acceso denegado', (string) $respuesta->getContent());
    }

    public function testConElPermisoEntra(): void
    {
        $this->autenticar($this->crearUsuarioConPermisos(['sistema.usuarios.ver']));

        $respuesta = $this->kernel->manejar(Request::create('/restringida'));

        self::assertSame(Response::HTTP_OK, $respuesta->getStatusCode());
    }

    public function testUnaRutaPrivadaSinPermisoDeclaradoSoloExigeSesion(): void
    {
        $this->autenticar($this->crearUsuarioConPermisos([]));

        $respuesta = $this->kernel->manejar(Request::create('/privada'));

        self::assertSame(Response::HTTP_OK, $respuesta->getStatusCode());
    }

    // --- soporte ---

    private function autenticar(int $usuarioId): void
    {
        /** @var Sesion $sesion */
        $sesion = $this->contenedor->get(Sesion::class);
        $sesion->iniciar();
        $sesion->autenticar(new Usuario(
            id: $usuarioId,
            nombre: 'Usuario de prueba',
            correo: 'prueba@hospital.gov.co',
            hashPassword: '',
            rolId: 0,
            activo: true,
            intentosFallidos: 0,
            bloqueadoHasta: null,
            debeCambiarPassword: false,
        ));
    }

    /**
     * @param list<string> $permisos
     */
    private function crearUsuarioConPermisos(array $permisos): int
    {
        self::$conexion->insert('roles', [
            'nombre' => 'Rol de prueba',
            'slug' => 'prueba-' . bin2hex(random_bytes(6)),
            'permisos' => json_encode($permisos, JSON_THROW_ON_ERROR),
        ]);

        self::$conexion->insert('usuarios', [
            'nombre' => 'Usuario de prueba',
            'correo' => bin2hex(random_bytes(6)) . '@hospital.gov.co',
            'hash_password' => 'irrelevante-para-esta-prueba',
            'rol_id' => (int) self::$conexion->lastInsertId(),
            'activo' => 1,
        ]);

        return (int) self::$conexion->lastInsertId();
    }
}

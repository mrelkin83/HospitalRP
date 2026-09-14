<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Core\Auth\Sesion;
use App\Core\Auth\Usuario;
use App\Core\Config\ConfigAplicacion;
use App\Core\Config\Env;
use App\Http\Admin\Soporte\PantallaAdmin;
use App\Http\Kernel;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouteCollection;
use Tests\Support\BaseDatosDePruebas;

/**
 * Pantallas del panel de la Fase 9: convocatorias, eventos, encuestas y
 * servidores públicos.
 *
 * Se prueban las rutas REALES del sitio, no rutas de mentira: el riesgo de
 * cuatro módulos añadidos de golpe es que alguno quede sin `_acceso` privado
 * y el panel salga al aire abierto.
 */
#[CoversClass(Kernel::class)]
#[CoversClass(PantallaAdmin::class)]
final class PanelFase9Test extends TestCase
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

    /** @return list<array{string, string}> ruta y permiso que la abre */
    public static function pantallasDelPanel(): array
    {
        return [
            ['/admin/convocatorias', 'convocatorias.ver'],
            ['/admin/eventos', 'eventos.ver'],
            ['/admin/encuestas', 'encuestas.ver'],
            ['/admin/servidores-publicos', 'directorio.ver'],
        ];
    }

    #[DataProvider('pantallasDelPanel')]
    public function testNingunaPantallaNuevaEsPublica(string $ruta, string $permiso): void
    {
        $respuesta = $this->kernel->manejar(Request::create($ruta));

        self::assertSame(Response::HTTP_SEE_OTHER, $respuesta->getStatusCode(), $ruta . ' quedó sin exigir sesión');
        self::assertSame('/admin/entrar', $respuesta->headers->get('Location'));
    }

    #[DataProvider('pantallasDelPanel')]
    public function testConSesionPeroSinElPermisoSeRecibe403(string $ruta, string $permiso): void
    {
        $this->autenticar($this->crearUsuarioConPermisos(['contenido.ver']));

        $respuesta = $this->kernel->manejar(Request::create($ruta));

        self::assertSame(Response::HTTP_FORBIDDEN, $respuesta->getStatusCode(), $ruta . ' no exigió permiso');
    }

    #[DataProvider('pantallasDelPanel')]
    public function testConElPermisoLaPantallaAbre(string $ruta, string $permiso): void
    {
        $this->autenticar($this->crearUsuarioConPermisos([$permiso]));

        $respuesta = $this->kernel->manejar(Request::create($ruta));

        self::assertSame(Response::HTTP_OK, $respuesta->getStatusCode(), $ruta . ' no abrió con su permiso');
    }

    /**
     * Un POST sin token no puede escribir. Es la prueba que justifica haber
     * extraído `PantallaAdmin`: cuatro copias del mismo CSRF eran cuatro
     * oportunidades de olvidarlo.
     */
    public function testUnPostSinTokenCsrfNoCreaNada(): void
    {
        $this->autenticar($this->crearUsuarioConPermisos(['eventos.editar', 'eventos.ver']));

        $antes = (int) self::$conexion->fetchOne('SELECT COUNT(*) FROM eventos');

        $respuesta = $this->kernel->manejar(Request::create('/admin/eventos', 'POST', [
            'titulo' => 'Evento colado sin token',
            'inicio' => '2026-10-01T09:00',
            'publicado' => '1',
        ]));

        self::assertSame(Response::HTTP_FORBIDDEN, $respuesta->getStatusCode());
        self::assertSame($antes, (int) self::$conexion->fetchOne('SELECT COUNT(*) FROM eventos'));
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

    /** @param list<string> $permisos */
    private function crearUsuarioConPermisos(array $permisos): int
    {
        self::$conexion->insert('roles', [
            'nombre' => 'Rol de prueba',
            'slug' => 'prueba-' . bin2hex(random_bytes(6)),
            'permisos' => json_encode($permisos, JSON_THROW_ON_ERROR),
        ]);
        $rolId = (int) self::$conexion->lastInsertId();

        self::$conexion->insert('usuarios', [
            'nombre' => 'Usuario de prueba',
            'correo' => 'prueba-' . bin2hex(random_bytes(4)) . '@hospital.gov.co',
            'hash_password' => password_hash('x', PASSWORD_DEFAULT),
            'rol_id' => $rolId,
            'activo' => 1,
        ]);

        return (int) self::$conexion->lastInsertId();
    }
}

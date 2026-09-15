<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Core\Auth\AutorizacionService;
use App\Core\Auth\Sesion;
use App\Core\Auth\Usuario;
use App\Core\Config\ConfigAplicacion;
use App\Core\Config\Env;
use App\Http\Kernel;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouteCollection;
use Tests\Support\BaseDatosDePruebas;

/**
 * Un rol acotado no puede entrar donde no le corresponde.
 *
 * La regla es de mínimo privilegio: quien alimenta el sitio con noticias no
 * tiene por qué poder crear usuarios, repartir permisos ni cambiar el NIT de
 * la entidad. Se prueba por HTTP y no llamando a `AutorizacionService`
 * directamente, porque lo que importa es que el Kernel corte la petición: un
 * permiso bien calculado que ninguna ruta exige no protege nada.
 */
#[CoversClass(AutorizacionService::class)]
final class PermisosPanelTest extends TestCase
{
    private const string CORREO = 'redactor-de-prueba@hospital.test';

    private static Connection $conexion;
    private Kernel $kernel;
    private ContainerBuilder $contenedor;
    private int $usuarioRedactor;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        $raiz = dirname(__DIR__, 3);
        $env = Env::desdeSuperglobales();

        /** @var callable(Env): ContainerBuilder $fabrica */
        $fabrica = require $raiz . '/config/servicios.php';
        $this->contenedor = $fabrica($env);

        /** @var ConfigAplicacion $config */
        $config = $this->contenedor->get(ConfigAplicacion::class);

        /** @var RouteCollection $rutas */
        $rutas = require $raiz . '/config/rutas.php';

        $this->kernel = new Kernel($this->contenedor, $rutas, $config);
        $this->usuarioRedactor = $this->crearRedactor();
    }

    protected function tearDown(): void
    {
        self::$conexion->executeStatement('DELETE FROM usuarios WHERE correo = ?', [self::CORREO]);
        self::$conexion->executeStatement("DELETE FROM roles WHERE slug = 'redactor-de-prueba'");
    }

    public function testElRedactorEntraALoSuyo(): void
    {
        $this->entrarComoRedactor();

        self::assertSame(Response::HTTP_OK, $this->estadoDe('/admin'));
        self::assertSame(Response::HTTP_OK, $this->estadoDe('/admin/noticias'));
    }

    /**
     * El caso que motiva todo esto: quien alimenta el sitio no administra el
     * sistema. `sistema.usuarios` es además la llave maestra —con ella se
     * fabrica cualquier otro permiso—, así que es la primera que se vigila.
     */
    public function testElRedactorNoEntraALaAdministracionDelSistema(): void
    {
        $this->entrarComoRedactor();

        foreach (['/admin/usuarios', '/admin/roles', '/admin/configuracion', '/admin/auditoria'] as $ruta) {
            self::assertSame(
                Response::HTTP_FORBIDDEN,
                $this->estadoDe($ruta),
                $ruta . ' debería estar cerrada para un redactor.',
            );
        }
    }

    /** Tampoco puede abrir la bandeja de PQRSDF, que trae datos del ciudadano. */
    public function testElRedactorNoEntraALaBandejaDeTramites(): void
    {
        $this->entrarComoRedactor();

        self::assertSame(Response::HTTP_FORBIDDEN, $this->estadoDe('/admin/tramites'));
    }

    /**
     * El menú no puede ofrecer lo que la ruta va a negar: un enlace que
     * responde 403 al pulsarlo no es seguridad, es una trampa.
     */
    public function testElMenuNoOfreceLoQueElRedactorNoPuedeAbrir(): void
    {
        $this->entrarComoRedactor();

        $respuesta = $this->kernel->manejar(Request::create('/admin'));
        $html = (string) $respuesta->getContent();

        self::assertStringContainsString('/admin/noticias', $html);
        self::assertStringNotContainsString('href="/admin/usuarios"', $html);
        self::assertStringNotContainsString('href="/admin/configuracion"', $html);
        self::assertStringNotContainsString('href="/admin/tramites"', $html);
    }

    // --- soporte ---

    private function estadoDe(string $ruta): int
    {
        return $this->kernel->manejar(Request::create($ruta))->getStatusCode();
    }

    private function entrarComoRedactor(): void
    {
        /** @var Sesion $sesion */
        $sesion = $this->contenedor->get(Sesion::class);
        $sesion->iniciar();
        $sesion->autenticar(new Usuario(
            id: $this->usuarioRedactor,
            nombre: 'Redactor de prueba',
            correo: self::CORREO,
            hashPassword: '',
            rolId: 0,
            activo: true,
            intentosFallidos: 0,
            bloqueadoHasta: null,
            debeCambiarPassword: false,
        ));
    }

    private function crearRedactor(): int
    {
        self::$conexion->executeStatement('DELETE FROM usuarios WHERE correo = ?', [self::CORREO]);
        self::$conexion->executeStatement("DELETE FROM roles WHERE slug = 'redactor-de-prueba'");

        self::$conexion->insert('roles', [
            'nombre' => 'Redactor de prueba',
            'slug' => 'redactor-de-prueba',
            'descripcion' => 'Solo contenido.',
            'permisos' => json_encode(['contenido.ver', 'contenido.editar'], JSON_THROW_ON_ERROR),
            'protegido' => 0,
        ]);

        $rolId = (int) self::$conexion->lastInsertId();

        self::$conexion->insert('usuarios', [
            'nombre' => 'Redactor de prueba',
            'correo' => self::CORREO,
            'hash_password' => password_hash('irrelevante-para-esta-prueba', PASSWORD_DEFAULT),
            'rol_id' => $rolId,
            'activo' => 1,
        ]);

        return (int) self::$conexion->lastInsertId();
    }
}

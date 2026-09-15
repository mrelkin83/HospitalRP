<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Core\Auth\Sesion;
use App\Core\Auth\Usuario;
use App\Core\Config\ConfigAplicacion;
use App\Core\Config\Env;
use App\Http\Admin\MenusController;
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
 * La pantalla de Menús, desde que también edita la portada.
 *
 * Las ocho tarjetas de accesos rápidos son items del menú «accesos» desde el
 * 2026-09-15: lo que se escriba en esta pantalla acaba en un `href` de la
 * página que más gente abre. Eso cambia el peso del campo «dirección», y de
 * ahí salen las dos cosas que se vigilan aquí:
 *
 * - que una dirección con `javascript:` no llegue a guardarse, y
 * - que la frase y el icono de una tarjeta sí se guarden.
 */
#[CoversClass(MenusController::class)]
final class MenusAdminTest extends TestCase
{
    private const string SLUG_MENU = 'menu-de-prueba-accesos';

    private static Connection $conexion;
    private ContainerBuilder $contenedor;
    private Kernel $kernel;
    private int $menuId;
    private int $itemId;

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

        $this->limpiar();

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

        self::$conexion->insert('menus', [
            'nombre' => 'Accesos de prueba',
            'slug' => self::SLUG_MENU,
            'ubicacion' => 'accesos',
        ]);
        $this->menuId = (int) self::$conexion->lastInsertId();

        self::$conexion->insert('menu_items', [
            'menu_id' => $this->menuId,
            'etiqueta' => 'Citas',
            'url' => '/citas',
            'orden' => 0,
            'visible' => 1,
        ]);
        $this->itemId = (int) self::$conexion->lastInsertId();

        $this->autenticar($this->crearUsuarioConPermisos(['sistema.menus']));
    }

    protected function tearDown(): void
    {
        $this->limpiar();
    }

    /**
     * Una tarjeta administrable es, al final, un `href` de la portada. Con
     * `javascript:` ahí dentro, cualquiera que edite menús ejecuta código en
     * el navegador de todo el que entre al sitio: escapar el valor no lo
     * impide, porque no hay nada que escapar.
     *
     * @return list<array{string}>
     */
    public static function direccionesQueNoSeGuardan(): array
    {
        return [
            ['javascript:alert(1)'],
            ['JaVaScRiPt:alert(1)'],
            ['data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=='],
            ['vbscript:msgbox(1)'],
            // El navegador lee «//otro.com» como dominio ajeno, no como ruta.
            ['//evil.example.com/citas'],
            [''],
        ];
    }

    #[DataProvider('direccionesQueNoSeGuardan')]
    public function testUnaDireccionPeligrosaNoLlegaAGuardarse(string $url): void
    {
        $this->post('/admin/menus/items/' . $this->itemId, [
            'etiqueta' => 'Citas',
            'url' => $url,
            'orden' => '0',
            'visible' => '1',
        ]);

        self::assertSame('/citas', $this->urlDelItem($this->itemId), 'Se guardó «' . $url . '».');
    }

    #[DataProvider('direccionesQueNoSeGuardan')]
    public function testTampocoSeCreaUnEnlaceNuevoConUnaDireccionPeligrosa(string $url): void
    {
        $antes = $this->cuantosItems();

        $this->post('/admin/menus/' . $this->menuId . '/items', [
            'etiqueta' => 'Tarjeta colada',
            'url' => $url,
            'orden' => '9',
        ]);

        self::assertSame($antes, $this->cuantosItems());
    }

    /** @return list<array{string}> */
    public static function direccionesQueSiSeGuardan(): array
    {
        return [
            ['/tramites/consulta'],
            ['#contenido'],
            ['https://www.hrplopez.gov.co/pqrsdf'],
            ['mailto:contacto@hrplopez.gov.co'],
            ['tel:+576055712339'],
        ];
    }

    #[DataProvider('direccionesQueSiSeGuardan')]
    public function testLasDireccionesNormalesSiguenGuardandose(string $url): void
    {
        $this->post('/admin/menus/items/' . $this->itemId, [
            'etiqueta' => 'Citas',
            'url' => $url,
            'orden' => '0',
            'visible' => '1',
        ]);

        self::assertSame($url, $this->urlDelItem($this->itemId));
    }

    /**
     * Lo que distingue una tarjeta de un enlace de menú corriente: la frase
     * que va bajo el título y el icono elegido del catálogo. Sin esto, la
     * pantalla de Menús serviría para todo menos para lo que se amplió.
     */
    public function testLaFraseYElIconoDeUnaTarjetaSeGuardan(): void
    {
        $this->post('/admin/menus/items/' . $this->itemId, [
            'etiqueta' => 'Citas médicas',
            'descripcion' => 'Agenda, consulta o cancela tu cita',
            'icono' => 'calendario',
            'url' => '/citas',
            'orden' => '0',
            'visible' => '1',
        ]);

        $fila = self::$conexion->fetchAssociative(
            'SELECT etiqueta, descripcion, icono FROM menu_items WHERE id = ?',
            [$this->itemId],
        );

        self::assertIsArray($fila);
        self::assertSame('Citas médicas', $fila['etiqueta']);
        self::assertSame('Agenda, consulta o cancela tu cita', $fila['descripcion']);
        self::assertSame('calendario', $fila['icono']);
    }

    // --- soporte ---

    /** @param array<string, string> $campos */
    private function post(string $ruta, array $campos): Response
    {
        /** @var Sesion $sesion */
        $sesion = $this->contenedor->get(Sesion::class);
        $sesion->iniciar();

        $campos['_token'] = $sesion->tokenCsrf();

        return $this->kernel->manejar(Request::create($ruta, 'POST', $campos));
    }

    private function urlDelItem(int $id): string
    {
        return (string) self::$conexion->fetchOne('SELECT url FROM menu_items WHERE id = ?', [$id]);
    }

    private function cuantosItems(): int
    {
        return (int) self::$conexion->fetchOne(
            'SELECT COUNT(*) FROM menu_items WHERE menu_id = ?',
            [$this->menuId],
        );
    }

    private function limpiar(): void
    {
        self::$conexion->executeStatement(
            'DELETE FROM menu_items WHERE menu_id IN (SELECT id FROM menus WHERE slug = ?)',
            [self::SLUG_MENU],
        );
        self::$conexion->executeStatement('DELETE FROM menus WHERE slug = ?', [self::SLUG_MENU]);
        self::$conexion->executeStatement('DELETE FROM usuarios');
        self::$conexion->executeStatement("DELETE FROM roles WHERE slug LIKE 'prueba-%'");
    }

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

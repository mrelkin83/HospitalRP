<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Contenido;

use App\Core\Auth\Sesion;
use App\Core\Auth\Usuario;
use App\Core\Config\ConfigAplicacion;
use App\Core\Config\Env;
use App\Http\Admin\PopupsController;
use App\Http\Kernel;
use App\Modules\Contenido\Repository\PopupRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouteCollection;
use Tests\Support\BaseDatosDePruebas;

#[CoversClass(PopupsController::class)]
#[CoversClass(PopupRepository::class)]
final class PopupsTest extends TestCase
{
    private static Connection $conexion;
    private ContainerBuilder $contenedor;
    private Kernel $kernel;
    private PopupRepository $popups;

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

        self::$conexion->executeStatement('DELETE FROM popups');
        self::$conexion->executeStatement('DELETE FROM usuarios');
        self::$conexion->executeStatement("DELETE FROM roles WHERE slug LIKE 'popup-%'");

        $raiz = dirname(__DIR__, 4);
        $env = Env::desdeSuperglobales();

        /** @var callable(Env): ContainerBuilder $fabrica */
        $fabrica = require $raiz . '/config/servicios.php';
        $this->contenedor = $fabrica($env);

        /** @var ConfigAplicacion $config */
        $config = $this->contenedor->get(ConfigAplicacion::class);

        /** @var RouteCollection $rutas */
        $rutas = require $raiz . '/config/rutas.php';

        $this->kernel = new Kernel($this->contenedor, $rutas, $config);
        $this->popups = new PopupRepository(self::$conexion);
    }

    // --- Qué popup ve el visitante ---

    public function testSoloSeMuestraElActivoYDentroDeSuVentana(): void
    {
        $this->crear(['titulo' => 'Apagado', 'activo' => 0]);
        $this->crear(['titulo' => 'Caducado', 'hasta' => '2020-01-01 00:00:00']);
        $this->crear(['titulo' => 'Todavía no', 'desde' => '2090-01-01 00:00:00']);

        self::assertNull($this->popups->vigente(true));

        $this->crear(['titulo' => 'Vigente']);

        self::assertSame('Vigente', $this->popups->vigente(true)?->titulo);
    }

    /**
     * Dos ventanas emergentes a la vez, una encima de otra, echan al
     * visitante. Aunque haya varias vigentes, sale una sola.
     */
    public function testConVariosVigentesGanaElDeMasPrioridad(): void
    {
        $this->crear(['titulo' => 'Secundario', 'prioridad' => 1]);
        $this->crear(['titulo' => 'Principal', 'prioridad' => 50]);
        $this->crear(['titulo' => 'Otro más', 'prioridad' => 10]);

        self::assertSame('Principal', $this->popups->vigente(true)?->titulo);
    }

    public function testElDePortadaNoSaleEnElRestoDelSitio(): void
    {
        $this->crear(['titulo' => 'Solo portada', 'ubicacion' => 'portada']);

        self::assertSame('Solo portada', $this->popups->vigente(true)?->titulo);
        self::assertNull($this->popups->vigente(false));
    }

    public function testElDeTodasLasPaginasSaleTambienEnLaPortada(): void
    {
        $this->crear(['titulo' => 'En todo el sitio', 'ubicacion' => 'todas']);

        self::assertSame('En todo el sitio', $this->popups->vigente(true)?->titulo);
        self::assertSame('En todo el sitio', $this->popups->vigente(false)?->titulo);
    }

    /**
     * La clave del «ya lo cerré» lleva dentro una huella de la última edición.
     * Sin eso, corregir una fecha equivocada no llegaría a nadie que hubiera
     * cerrado la versión anterior.
     */
    public function testAlEditarElPopupCambiaLaClaveQueRecuerdaElNavegador(): void
    {
        $id = $this->crear(['titulo' => 'Con la fecha mal']);
        $antes = $this->popups->vigente(true)?->clave();

        // `actualizado_en` se mueve solo con ON UPDATE, pero dentro del mismo
        // segundo el valor no cambia y la prueba no probaría nada.
        self::$conexion->update(
            'popups',
            ['titulo' => 'Con la fecha corregida', 'actualizado_en' => date('Y-m-d H:i:s', time() + 60)],
            ['id' => $id],
        );

        self::assertNotSame($antes, $this->popups->vigente(true)?->clave());
    }

    // --- El panel ---

    public function testSeCreaSeEditaYSeEliminaDesdeElPanel(): void
    {
        $this->autenticar($this->crearUsuarioConPermisos(['contenido.*']));
        $token = $this->token();

        $crear = $this->kernel->manejar(Request::create('/admin/popups', 'POST', [
            '_token' => $token,
            'titulo' => 'Jornada de vacunación',
            'mensaje' => 'El esquema del PAI es gratuito.',
            'ubicacion' => 'todas',
            'frecuencia' => 'diaria',
            'activo' => '1',
            'prioridad' => '5',
        ]));

        self::assertSame(Response::HTTP_SEE_OTHER, $crear->getStatusCode());

        $fila = self::$conexion->fetchAssociative('SELECT * FROM popups');
        self::assertIsArray($fila);
        self::assertSame('Jornada de vacunación', $fila['titulo']);
        self::assertSame('todas', $fila['ubicacion']);
        self::assertSame('diaria', $fila['frecuencia']);
        self::assertSame(1, (int) $fila['activo']);

        $id = (int) $fila['id'];

        $this->kernel->manejar(Request::create('/admin/popups/' . $id, 'POST', [
            '_token' => $token,
            'titulo' => 'Jornada de vacunación (aplazada)',
            'mensaje' => 'El esquema del PAI es gratuito.',
            'ubicacion' => 'portada',
            'frecuencia' => 'sesion',
            'prioridad' => '5',
        ]));

        $editado = self::$conexion->fetchAssociative('SELECT * FROM popups WHERE id = ?', [$id]);
        self::assertIsArray($editado);
        self::assertSame('Jornada de vacunación (aplazada)', $editado['titulo']);
        // La casilla no viaja cuando está desmarcada: eso es apagarlo.
        self::assertSame(0, (int) $editado['activo']);

        $this->kernel->manejar(Request::create('/admin/popups/' . $id . '/eliminar', 'POST', [
            '_token' => $token,
        ]));

        self::assertSame(0, (int) self::$conexion->fetchOne('SELECT COUNT(*) FROM popups'));
    }

    /** Un ENUM no puede quedar a merced de lo que llegue en el formulario. */
    public function testUnaUbicacionInventadaCaeEnElValorMasConservador(): void
    {
        $this->autenticar($this->crearUsuarioConPermisos(['contenido.*']));

        $this->kernel->manejar(Request::create('/admin/popups', 'POST', [
            '_token' => $this->token(),
            'titulo' => 'Con datos manipulados',
            'mensaje' => 'Mensaje.',
            'ubicacion' => 'toda-la-internet',
            'frecuencia' => 'cada-segundo',
        ]));

        $fila = self::$conexion->fetchAssociative('SELECT * FROM popups');
        self::assertIsArray($fila);
        self::assertSame('portada', $fila['ubicacion']);
        self::assertSame('sesion', $fila['frecuencia']);
    }

    public function testSinTituloNoSeCrea(): void
    {
        $this->autenticar($this->crearUsuarioConPermisos(['contenido.*']));

        $respuesta = $this->kernel->manejar(Request::create('/admin/popups', 'POST', [
            '_token' => $this->token(),
            'titulo' => '   ',
            'mensaje' => 'Mensaje sin título.',
        ]));

        self::assertStringContainsString('invalido', (string) $respuesta->headers->get('Location'));
        self::assertSame(0, (int) self::$conexion->fetchOne('SELECT COUNT(*) FROM popups'));
    }

    public function testSinCsrfNoSeCrea(): void
    {
        $this->autenticar($this->crearUsuarioConPermisos(['contenido.*']));

        $respuesta = $this->kernel->manejar(Request::create('/admin/popups', 'POST', [
            '_token' => 'token-de-otro',
            'titulo' => 'Intento sin token',
            'mensaje' => 'Mensaje.',
        ]));

        self::assertSame(Response::HTTP_FORBIDDEN, $respuesta->getStatusCode());
        self::assertSame(0, (int) self::$conexion->fetchOne('SELECT COUNT(*) FROM popups'));
    }

    public function testSinSesionElPanelDePopupsRedirigeAlLogin(): void
    {
        $respuesta = $this->kernel->manejar(Request::create('/admin/popups'));

        self::assertSame(Response::HTTP_SEE_OTHER, $respuesta->getStatusCode());
        self::assertSame('/admin/entrar', $respuesta->headers->get('Location'));
    }

    /** Poder editar no da derecho a borrar. */
    public function testQuienSoloPuedeEditarNoPuedeEliminar(): void
    {
        $id = $this->crear(['titulo' => 'No se toca']);
        $this->autenticar($this->crearUsuarioConPermisos(['contenido.ver', 'contenido.editar']));

        $respuesta = $this->kernel->manejar(Request::create('/admin/popups/' . $id . '/eliminar', 'POST', [
            '_token' => $this->token(),
        ]));

        self::assertSame(Response::HTTP_FORBIDDEN, $respuesta->getStatusCode());
        self::assertSame(1, (int) self::$conexion->fetchOne('SELECT COUNT(*) FROM popups'));
    }

    // --- soporte ---

    /** @param array<string, mixed> $campos */
    private function crear(array $campos): int
    {
        self::$conexion->insert('popups', $campos + [
            'titulo' => 'Popup de prueba',
            'mensaje' => 'Mensaje de prueba.',
            'ubicacion' => 'todas',
            'frecuencia' => 'sesion',
            'activo' => 1,
            'prioridad' => 0,
        ]);

        return (int) self::$conexion->lastInsertId();
    }

    private function token(): string
    {
        /** @var Sesion $sesion */
        $sesion = $this->contenedor->get(Sesion::class);
        $sesion->iniciar();

        return $sesion->tokenCsrf();
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
            'slug' => 'popup-' . bin2hex(random_bytes(6)),
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

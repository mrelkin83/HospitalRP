<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Core\Auth\Sesion;
use App\Core\Auth\Usuario;
use App\Core\Config\ConfigAplicacion;
use App\Core\Config\Env;
use App\Core\Storage\AlmacenArchivos;
use App\Http\Admin\MediosController;
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
 * Borrar de la biblioteca de medios sin romper lo publicado.
 *
 * Ocho de las once claves foráneas que apuntan a `archivos` son
 * `ON DELETE SET NULL`: borrar una imagen en uso no falla, deja en blanco la
 * portada de una campaña publicada y nadie se entera hasta que alguien mira
 * el sitio. Las otras tres son `RESTRICT` y revientan con un error de base de
 * datos. Por eso se pregunta antes, y por eso esto se prueba.
 */
#[CoversClass(MediosController::class)]
#[CoversClass(AlmacenArchivos::class)]
final class MediosTest extends TestCase
{
    private const string CORREO = 'medios-admin@hospital.test';

    private static Connection $conexion;
    private Kernel $kernel;
    private ContainerBuilder $contenedor;
    private int $usuarioId;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM campanas');
        self::$conexion->executeStatement("DELETE FROM archivos WHERE nombre_original LIKE 'prueba-medios-%'");

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
        $this->usuarioId = $this->crearAdministrador();
        $this->entrar();
    }

    protected function tearDown(): void
    {
        $this->limpiarAdministrador();
    }

    public function testLaBibliotecaOfreceBorrarLoQueNadieUsa(): void
    {
        $this->crearArchivo('prueba-medios-libre.png');

        $cuerpo = (string) $this->kernel->manejar(Request::create('/admin/medios'))->getContent();

        self::assertStringContainsString('Borrar de la biblioteca', $cuerpo);
    }

    public function testBorrarUnaImagenLibreLaQuitaDeLaBiblioteca(): void
    {
        $id = $this->crearArchivo('prueba-medios-libre.png');

        $respuesta = $this->borrar($id);

        self::assertSame(Response::HTTP_SEE_OTHER, $respuesta->getStatusCode());
        self::assertSame('0', (string) self::$conexion->fetchOne('SELECT COUNT(*) FROM archivos WHERE id = ?', [$id]));
    }

    /**
     * El caso que importa: la imagen sostiene una campaña publicada. No se
     * borra, y el aviso dice dónde está en uso para que se pueda resolver.
     */
    public function testNoBorraUnaImagenQueSostieneContenidoPublicado(): void
    {
        $id = $this->crearArchivo('prueba-medios-en-uso.png');

        self::$conexion->insert('campanas', [
            'slug' => 'campana-con-imagen',
            'titulo' => 'Campaña con imagen',
            'resumen' => 'Resumen.',
            'contenido' => '<p>Contenido.</p>',
            'imagen_id' => $id,
            'estado_aprobacion' => 'publicado',
        ]);

        $respuesta = $this->borrar($id);

        self::assertSame(Response::HTTP_CONFLICT, $respuesta->getStatusCode());
        self::assertStringContainsString('está en uso', (string) $respuesta->getContent());
        self::assertSame('1', (string) self::$conexion->fetchOne('SELECT COUNT(*) FROM archivos WHERE id = ?', [$id]));

        // Y la campaña conserva su imagen: nada de SET NULL por la puerta de atrás.
        self::assertSame(
            (string) $id,
            (string) self::$conexion->fetchOne("SELECT imagen_id FROM campanas WHERE slug = 'campana-con-imagen'"),
        );
    }

    /** Sin el token no se borra nada, aunque la imagen esté libre. */
    public function testSinTokenCsrfNoSeBorra(): void
    {
        $id = $this->crearArchivo('prueba-medios-libre.png');

        $respuesta = $this->kernel->manejar(Request::create('/admin/medios/' . $id . '/eliminar', 'POST'));

        self::assertSame(Response::HTTP_FORBIDDEN, $respuesta->getStatusCode());
        self::assertSame('1', (string) self::$conexion->fetchOne('SELECT COUNT(*) FROM archivos WHERE id = ?', [$id]));
    }

    // --- soporte ---

    /**
     * Borra como lo haría el navegador: pide la página, toma el token del
     * formulario y lo envía. Leer el token del servicio de sesión daría uno
     * distinto del que se pintó, y la prueba mediría el CSRF en vez del
     * borrado.
     */
    private function borrar(int $id): Response
    {
        $formulario = (string) $this->kernel->manejar(Request::create('/admin/medios'))->getContent();
        preg_match('/name="_token" value="([^"]+)"/', $formulario, $token);

        return $this->kernel->manejar(Request::create(
            '/admin/medios/' . $id . '/eliminar',
            'POST',
            ['_token' => $token[1] ?? ''],
        ));
    }

    /**
     * El permiso se resuelve por el rol del usuario EN LA BASE, no por el
     * objeto de sesión: sin fila de usuario, el panel responde «sin permiso» y
     * la prueba mediría otra cosa.
     */
    private function crearAdministrador(): int
    {
        $this->limpiarAdministrador();

        self::$conexion->insert('roles', [
            'nombre' => 'Administrador de medios (prueba)',
            'slug' => 'medios-admin',
            'permisos' => json_encode(['*'], JSON_THROW_ON_ERROR),
            'protegido' => 0,
        ]);

        self::$conexion->insert('usuarios', [
            'nombre' => 'Administrador de prueba',
            'correo' => self::CORREO,
            'hash_password' => password_hash('irrelevante', PASSWORD_DEFAULT),
            'rol_id' => (int) self::$conexion->lastInsertId(),
            'activo' => 1,
        ]);

        return (int) self::$conexion->lastInsertId();
    }

    private function limpiarAdministrador(): void
    {
        self::$conexion->executeStatement('DELETE FROM usuarios WHERE correo = ?', [self::CORREO]);
        self::$conexion->executeStatement('DELETE FROM roles WHERE slug = ?', ['medios-admin']);
    }

    private function entrar(): void
    {
        /** @var Sesion $sesion */
        $sesion = $this->contenedor->get(Sesion::class);
        $sesion->iniciar();
        $sesion->autenticar(new Usuario(
            id: $this->usuarioId,
            nombre: 'Administrador de prueba',
            correo: self::CORREO,
            hashPassword: '',
            rolId: 1,
            activo: true,
            intentosFallidos: 0,
            bloqueadoHasta: null,
            debeCambiarPassword: false,
        ));
    }

    private function crearArchivo(string $nombre): int
    {
        self::$conexion->insert('archivos', [
            'nombre_original' => $nombre,
            'ruta' => '2026/09/' . bin2hex(random_bytes(8)) . '.png',
            'mime' => 'image/png',
            'extension' => 'png',
            'tamano' => 128,
            'hash_sha256' => hash('sha256', $nombre . random_bytes(4)),
            'publico' => 1,
        ]);

        return (int) self::$conexion->lastInsertId();
    }
}

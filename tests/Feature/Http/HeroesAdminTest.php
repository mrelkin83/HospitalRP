<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Core\Auth\Sesion;
use App\Core\Auth\Usuario;
use App\Core\Config\ConfigAplicacion;
use App\Core\Config\Env;
use App\Http\Admin\HeroesController;
use App\Http\Kernel;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\BaseDatosDePruebas;

/**
 * La pantalla que crea y edita los héroes de la portada.
 *
 * Lo que se guarda aquí es lo primero que ve quien entra al sitio, así que se
 * vigilan las tres cosas que lo dejarían roto sin avisar: una dirección que
 * ejecuta código, una vigencia imposible que esconde el héroe para siempre, y
 * la tarjeta «Rosario Digital» repetida en dos diapositivas.
 */
#[CoversClass(HeroesController::class)]
final class HeroesAdminTest extends TestCase
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

        $this->limpiar();

        $raiz = dirname(__DIR__, 3);
        $env = Env::desdeSuperglobales();

        /** @var callable(Env): ContainerBuilder $fabricaContenedor */
        $fabricaContenedor = require $raiz . '/config/servicios.php';
        $this->contenedor = $fabricaContenedor($env);

        /** @var ConfigAplicacion $config */
        $config = $this->contenedor->get(ConfigAplicacion::class);

        $rutas = require $raiz . '/config/rutas.php';

        $this->kernel = new Kernel($this->contenedor, $rutas, $config);
    }

    protected function tearDown(): void
    {
        $this->limpiar();
    }

    public function testLaPantallaExigeSesion(): void
    {
        $respuesta = $this->kernel->manejar(Request::create('/admin/heroes'));

        self::assertSame(Response::HTTP_SEE_OTHER, $respuesta->getStatusCode());
        self::assertSame('/admin/entrar', $respuesta->headers->get('Location'));
    }

    public function testConSesionPeroSinPermisoDeContenidoSeRecibe403(): void
    {
        $this->autenticar($this->crearUsuarioConPermisos(['eventos.ver']));

        self::assertSame(
            Response::HTTP_FORBIDDEN,
            $this->kernel->manejar(Request::create('/admin/heroes'))->getStatusCode(),
        );
    }

    /**
     * Que la pantalla ABRA, con filas dentro y con una de ellas con imagen.
     *
     * Sin esto, las demás pruebas solo hacen POST y la plantilla del listado no
     * llegaría a ejecutarse nunca: un error de PHP ahí dentro se descubriría en
     * producción, delante de quien va a editar la portada.
     */
    public function testLaPantallaAbreYPintaLosHeroesQueHay(): void
    {
        $this->autenticarEditor();

        self::$conexion->insert('heroes', [
            'titulo' => 'Héroe con degradado',
            'titulo_degradado' => 'y su continuación',
            'visible' => 1,
            'orden' => 0,
            'mostrar_tarjeta' => 1,
        ]);
        self::$conexion->insert('heroes', [
            'titulo' => 'Héroe con fotografía',
            'imagen_id' => $this->crearArchivoDeImagen(),
            'visible' => 0,
            'orden' => 1,
        ]);

        $respuesta = $this->kernel->manejar(Request::create('/admin/heroes'));
        $cuerpo = (string) $respuesta->getContent();

        self::assertSame(Response::HTTP_OK, $respuesta->getStatusCode());
        self::assertStringContainsString('Héroe con degradado', $cuerpo);
        self::assertStringContainsString('Héroe con fotografía', $cuerpo);
        // La fila con imagen enseña su miniatura; la otra, la opción de subir.
        self::assertStringContainsString('Quitar esta imagen', $cuerpo);
        self::assertStringContainsString('Subir imagen', $cuerpo);
        // El formulario sube archivos: sin esto la imagen nunca llegaría.
        self::assertStringContainsString('enctype="multipart/form-data"', $cuerpo);
        self::assertStringContainsString('Oculto', $cuerpo, 'No se distingue el héroe apagado.');
    }

    public function testUnPostSinTokenCsrfNoCreaNada(): void
    {
        $this->autenticar($this->crearUsuarioConPermisos(['contenido.ver', 'contenido.editar']));

        $respuesta = $this->kernel->manejar(Request::create('/admin/heroes', 'POST', [
            'titulo' => 'Héroe colado sin token',
            'visible' => '1',
        ]));

        self::assertSame(Response::HTTP_FORBIDDEN, $respuesta->getStatusCode());
        self::assertSame(0, $this->cuantos());
    }

    public function testSeCreaUnHeroeConSuTextoYSusBotones(): void
    {
        $this->autenticarEditor();

        $this->post('/admin/heroes', [
            'pildora' => 'Jornada de septiembre',
            'titulo' => 'Vacúnate',
            'titulo_degradado' => 'sin cita previa',
            'eslogan' => 'Puesto de vacunación en la entrada de urgencias.',
            'boton1_texto' => 'Ver el cronograma',
            'boton1_url' => '/campanas',
            'orden' => '3',
            'visible' => '1',
        ]);

        $fila = self::$conexion->fetchAssociative('SELECT * FROM heroes WHERE titulo = ?', ['Vacúnate']);

        self::assertIsArray($fila);
        self::assertSame('Jornada de septiembre', $fila['pildora']);
        self::assertSame('sin cita previa', $fila['titulo_degradado']);
        self::assertSame('Ver el cronograma', $fila['boton1_texto']);
        self::assertSame('/campanas', $fila['boton1_url']);
        self::assertSame(3, (int) $fila['orden']);
        self::assertSame(1, (int) $fila['visible']);
    }

    public function testUnHeroeSinTituloNoSeGuarda(): void
    {
        $this->autenticarEditor();

        $this->post('/admin/heroes', ['titulo' => '   ', 'visible' => '1']);

        self::assertSame(0, $this->cuantos());
    }

    /**
     * Lo que se escriba aquí acaba en un `href` de la portada.
     *
     * @return list<array{string}>
     */
    public static function direccionesQueNoSeGuardan(): array
    {
        return [
            ['javascript:alert(1)'],
            ['data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=='],
            ['//evil.example.com/citas'],
        ];
    }

    #[DataProvider('direccionesQueNoSeGuardan')]
    public function testUnBotonConDireccionPeligrosaNoGuardaElHeroe(string $url): void
    {
        $this->autenticarEditor();

        $this->post('/admin/heroes', [
            'titulo' => 'Héroe con trampa',
            'boton1_texto' => 'Pulsa aquí',
            'boton1_url' => $url,
            'visible' => '1',
        ]);

        self::assertSame(0, $this->cuantos(), 'Se guardó un héroe con «' . $url . '».');
    }

    /** Medio botón no es un botón: sin dirección no se puede pintar el enlace. */
    public function testUnBotonConTextoYSinDireccionNoGuardaElHeroe(): void
    {
        $this->autenticarEditor();

        $this->post('/admin/heroes', [
            'titulo' => 'Héroe a medias',
            'boton1_texto' => 'Pulsa aquí',
            'boton1_url' => '',
            'visible' => '1',
        ]);

        self::assertSame(0, $this->cuantos());
    }

    /**
     * Una vigencia que termina antes de empezar deja el héroe invisible para
     * siempre y sin una sola pista de por qué.
     */
    public function testUnaVigenciaAlRevesNoSeGuarda(): void
    {
        $this->autenticarEditor();

        $this->post('/admin/heroes', [
            'titulo' => 'Héroe imposible',
            'inicio' => '2026-10-10',
            'fin' => '2026-10-01',
            'visible' => '1',
        ]);

        self::assertSame(0, $this->cuantos());
    }

    /**
     * Lo que pidió el hospital: la tarjeta «Rosario Digital» en el primero y
     * en ninguno más. Al marcarla en otro, el anterior la suelta.
     */
    public function testLaTarjetaSeMudaYNoSeDuplica(): void
    {
        $this->autenticarEditor();

        $this->post('/admin/heroes', ['titulo' => 'El primero', 'mostrar_tarjeta' => '1', 'orden' => '0', 'visible' => '1']);
        $this->post('/admin/heroes', ['titulo' => 'El segundo', 'mostrar_tarjeta' => '1', 'orden' => '1', 'visible' => '1']);

        self::assertSame(1, (int) self::$conexion->fetchOne('SELECT COUNT(*) FROM heroes WHERE mostrar_tarjeta = 1'));
        self::assertSame('El segundo', (string) self::$conexion->fetchOne('SELECT titulo FROM heroes WHERE mostrar_tarjeta = 1'));
    }

    public function testEditarUnHeroeCambiaSusTextos(): void
    {
        $this->autenticarEditor();

        self::$conexion->insert('heroes', ['titulo' => 'Antes', 'visible' => 1, 'orden' => 0]);
        $id = (int) self::$conexion->lastInsertId();

        $this->post('/admin/heroes/' . $id, ['titulo' => 'Después', 'orden' => '0', 'visible' => '1']);

        self::assertSame('Después', (string) self::$conexion->fetchOne('SELECT titulo FROM heroes WHERE id = ?', [$id]));
        self::assertSame(1, $this->cuantos(), 'Editar creó una fila nueva en vez de actualizar.');
    }

    /** Sin la casilla marcada, el héroe se apaga: es cómo se retira del sitio. */
    public function testDesmarcarVisibleApagaElHeroe(): void
    {
        $this->autenticarEditor();

        self::$conexion->insert('heroes', ['titulo' => 'Encendido', 'visible' => 1, 'orden' => 0]);
        $id = (int) self::$conexion->lastInsertId();

        $this->post('/admin/heroes/' . $id, ['titulo' => 'Encendido', 'orden' => '0']);

        self::assertSame(0, (int) self::$conexion->fetchOne('SELECT visible FROM heroes WHERE id = ?', [$id]));
    }

    public function testEliminarQuitaElHeroe(): void
    {
        $this->autenticarEditor();

        self::$conexion->insert('heroes', ['titulo' => 'Para borrar', 'visible' => 1, 'orden' => 0]);
        $id = (int) self::$conexion->lastInsertId();

        $this->post('/admin/heroes/' . $id . '/eliminar', []);

        self::assertSame(0, $this->cuantos());
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

    /**
     * Fila de `archivos` para poder darle imagen a un héroe. No hace falta que
     * el archivo exista en disco: la pantalla solo pinta `/archivos/{id}`.
     */
    private function crearArchivoDeImagen(): int
    {
        self::$conexion->insert('archivos', [
            'nombre_original' => 'heroe-admin-de-prueba.jpg',
            'ruta' => 'pruebas/heroe-admin-de-prueba.jpg',
            'mime' => 'image/jpeg',
            'extension' => 'jpg',
            'tamano' => 1024,
            'hash_sha256' => str_repeat('b', 64),
            'ancho' => 1600,
            'alto' => 900,
            'texto_alt' => 'Fachada del hospital',
            'publico' => 1,
        ]);

        return (int) self::$conexion->lastInsertId();
    }

    private function cuantos(): int
    {
        return (int) self::$conexion->fetchOne('SELECT COUNT(*) FROM heroes');
    }

    private function autenticarEditor(): void
    {
        $this->autenticar($this->crearUsuarioConPermisos(['contenido.ver', 'contenido.editar']));
    }

    private function limpiar(): void
    {
        self::$conexion->executeStatement('DELETE FROM heroes');
        self::$conexion->executeStatement("DELETE FROM archivos WHERE nombre_original = 'heroe-admin-de-prueba.jpg'");
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

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
use Symfony\Component\HttpFoundation\Cookie;
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

        // El cascarón carga el guion que cierra los desplegables al pulsar
        // fuera. Que las secciones lleven `name` no se comprueba aquí: este
        // usuario no tiene permisos, el menú se filtra entero y no queda ni un
        // <details> que mirar. De eso se ocupa VistasSanasTest, sobre la
        // plantilla, que es donde vive la regla.
        self::assertStringContainsString('/js/menu.js', $html);
    }

    public function testElLoginTraeLogoCasillasYBotonDeVerContrasena(): void
    {
        $respuesta = $this->kernel->manejar(Request::create('/admin/entrar'));

        $html = (string) $respuesta->getContent();
        self::assertStringContainsString('/img/logo_compacto.jpg', $html);
        self::assertStringContainsString('name="recordar"', $html);
        self::assertStringContainsString('name="persistente"', $html);
        // El ojo nace oculto y lo destapa /js/login.js: sin el script no debe
        // quedar en pantalla un botón que no hace nada.
        self::assertStringContainsString('data-ver-clave="password"', $html);
        self::assertMatchesRegularExpression('/<button[^>]*class="campo-clave-ojo"[^>]*\shidden/', $html);
        self::assertStringContainsString('/js/login.js', $html);
    }

    public function testLaCuentaRecordadaRellenaElCorreoYDejaLaCasillaMarcada(): void
    {
        $peticion = Request::create('/admin/entrar');
        $peticion->cookies->set('hrpl_correo', 'recordada@hospital.gov.co');

        $html = (string) $this->kernel->manejar($peticion)->getContent();

        self::assertStringContainsString('value="recordada@hospital.gov.co"', $html);
        self::assertMatchesRegularExpression('/id="recordar"[^>]*checked/s', $html);
    }

    /**
     * La cookie la escribe el navegador y el usuario puede editarla. Si trae
     * cualquier cosa, el campo sale vacío en vez de pintarla: es entrada de
     * fuera, no un dato del servidor.
     */
    public function testUnaCookieDeCorreoInvalidaSeIgnora(): void
    {
        $peticion = Request::create('/admin/entrar');
        $peticion->cookies->set('hrpl_correo', '"><script>alert(1)</script>');

        $html = (string) $this->kernel->manejar($peticion)->getContent();

        self::assertStringNotContainsString('alert(1)', $html);
        self::assertStringContainsString('value=""', $html);
    }

    public function testAlEntrarConRecordarSeGuardaElCorreoYAlDesmarcarloSeBorra(): void
    {
        $correo = $this->crearUsuarioQuePuedeEntrar('Clave-De-Prueba-123');

        $conRecuerdo = $this->cookieDeRespuesta(
            $this->entrar($correo, 'Clave-De-Prueba-123', ['recordar' => '1']),
            'hrpl_correo',
        );

        self::assertNotNull($conRecuerdo);
        self::assertSame($correo, $conRecuerdo->getValue());
        self::assertTrue($conRecuerdo->isHttpOnly());
        self::assertSame('strict', $conRecuerdo->getSameSite());
        self::assertGreaterThan(time(), $conRecuerdo->getExpiresTime());

        $sinRecuerdo = $this->cookieDeRespuesta($this->entrar($correo, 'Clave-De-Prueba-123'), 'hrpl_correo');

        self::assertNotNull($sinRecuerdo, 'Desmarcar la casilla tiene que BORRAR la cookie, no dejarla como estaba.');
        self::assertSame('', $sinRecuerdo->getValue());
        self::assertLessThan(time(), $sinRecuerdo->getExpiresTime());
    }

    /**
     * La marca que alarga la sesión.
     *
     * Se comprueba la MARCA y no la caducidad de verdad porque `iniciar()`
     * sale sin hacer nada si ya hay sesión abierta —y en una prueba todo
     * ocurre dentro del mismo proceso, con la sesión abierta desde el POST—.
     * Simular la segunda petición exigiría cerrar y reabrir la sesión, y
     * `session_start()` con la salida de PHPUnit ya emitida lanza un aviso que
     * `failOnWarning="true"` convierte en fallo. La marca es exactamente el
     * dato del que `iniciar()` lee la ventana de inactividad.
     */
    public function testMantenerLaSesionIniciadaSoloSeMarcaSiLaCasillaViene(): void
    {
        $correo = $this->crearUsuarioQuePuedeEntrar('Clave-De-Prueba-456');

        $this->entrar($correo, 'Clave-De-Prueba-456', ['persistente' => '1']);
        self::assertTrue($_SESSION['_persistente'] ?? null);

        $this->entrar($correo, 'Clave-De-Prueba-456');
        self::assertFalse($_SESSION['_persistente'] ?? null);
    }

    // --- soporte ---

    /** @param array<string, string> $extras */
    private function entrar(string $correo, string $password, array $extras = []): Response
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        /** @var Sesion $sesion */
        $sesion = $this->contenedor->get(Sesion::class);
        $sesion->iniciar();

        return $this->kernel->manejar(Request::create('/admin/entrar', 'POST', $extras + [
            '_token' => $sesion->tokenCsrf(),
            'correo' => $correo,
            'password' => $password,
        ]));
    }

    private function cookieDeRespuesta(Response $respuesta, string $nombre): ?Cookie
    {
        foreach ($respuesta->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $nombre) {
                return $cookie;
            }
        }

        return null;
    }

    /** @return string el correo del usuario creado */
    private function crearUsuarioQuePuedeEntrar(string $password): string
    {
        self::$conexion->insert('roles', [
            'nombre' => 'Rol de prueba',
            'slug' => 'login-' . bin2hex(random_bytes(6)),
            'permisos' => json_encode([], JSON_THROW_ON_ERROR),
        ]);

        $correo = bin2hex(random_bytes(6)) . '@hospital.gov.co';

        self::$conexion->insert('usuarios', [
            'nombre' => 'Usuario de prueba',
            'correo' => $correo,
            'hash_password' => password_hash($password, PASSWORD_ARGON2ID),
            'rol_id' => (int) self::$conexion->lastInsertId(),
            'activo' => 1,
            // La columna viene con DEFAULT 1: sin esto todo intento de entrar
            // acabaría en la pantalla de cambio obligatorio de contraseña.
            'debe_cambiar_pass' => 0,
        ]);

        return $correo;
    }
}

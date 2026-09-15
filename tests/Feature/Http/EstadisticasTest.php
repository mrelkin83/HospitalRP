<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Core\Auth\Sesion;
use App\Core\Auth\Usuario;
use App\Core\Config\ConfigAplicacion;
use App\Core\Config\Env;
use App\Core\Support\Slugs;
use App\Http\Admin\IndicadoresAdminController;
use App\Http\Kernel;
use App\Http\Web\EstadisticasController;
use App\Modules\Indicadores\Repository\IndicadorRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouteCollection;
use Tests\Support\BaseDatosDePruebas;

/**
 * Información estadística: página pública y carga desde el panel.
 *
 * Lo que se vigila aquí es que una cifra a medio cargar NO salga al aire. En
 * la página de estadísticas de un hospital público, un borrador que se escapa
 * es peor que la página vacía: nadie sabe que era un borrador.
 */
#[CoversClass(EstadisticasController::class)]
#[CoversClass(IndicadoresAdminController::class)]
#[CoversClass(IndicadorRepository::class)]
final class EstadisticasTest extends TestCase
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

        self::$conexion->executeStatement('DELETE FROM indicadores');
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

    public function testSinIndicadoresLaPaginaAbreYAvisaEnLugarDeMostrarCifras(): void
    {
        $respuesta = $this->kernel->manejar(Request::create('/estadisticas'));

        self::assertSame(Response::HTTP_OK, $respuesta->getStatusCode());
        self::assertStringContainsString('Todavía no hay indicadores publicados', (string) $respuesta->getContent());
    }

    public function testUnIndicadorSinPublicarNoLlegaAlPublico(): void
    {
        $this->cargar('ocupacion', 'Camas ocupadas', '2026-07-01', 84.0, publicado: 0);

        $cuerpo = (string) $this->kernel->manejar(Request::create('/estadisticas'))->getContent();

        self::assertStringNotContainsString('Camas ocupadas', $cuerpo);
        self::assertStringContainsString('Todavía no hay indicadores publicados', $cuerpo);
    }

    public function testUnIndicadorPublicadoSaleConSuPeriodoYSuFuente(): void
    {
        $this->cargar('ocupacion', 'Camas ocupadas', '2026-07-01', 84.0);

        $cuerpo = (string) $this->kernel->manejar(Request::create('/estadisticas'))->getContent();

        self::assertStringContainsString('Camas ocupadas', $cuerpo);
        self::assertStringContainsString('Ocupación hospitalaria', $cuerpo);
        self::assertStringContainsString('julio de 2026', $cuerpo);
        self::assertStringContainsString('Oficina de Planeación', $cuerpo);
    }

    /**
     * Cargar agosto no debe duplicar la tarjeta de julio: el ciudadano ve la
     * cifra vigente y la anterior queda en la serie.
     */
    public function testSoloSeMuestraElUltimoPeriodoDeCadaIndicador(): void
    {
        $this->cargar('ocupacion', 'Camas ocupadas', '2026-07-01', 84.0);
        $this->cargar('ocupacion', 'Camas ocupadas', '2026-08-01', 91.0);

        $ultimos = $this->repositorio()->ultimosPorGrupo('ocupacion');

        self::assertCount(1, $ultimos);
        self::assertSame('2026-08-01', (string) $ultimos[0]['periodo']);
        self::assertCount(2, $this->repositorio()->serie('camas-ocupadas'));
    }

    /** La serie va de lo más antiguo a lo más reciente, que es como se lee. */
    public function testLaSerieLlegaEnOrdenCronologico(): void
    {
        $this->cargar('vitales', 'Nacimientos atendidos', '2026-08-01', 205.0);
        $this->cargar('vitales', 'Nacimientos atendidos', '2026-06-01', 198.0);
        $this->cargar('vitales', 'Nacimientos atendidos', '2026-07-01', 211.0);

        $serie = $this->repositorio()->serie('nacimientos-atendidos');

        self::assertSame(['2026-06-01', '2026-07-01', '2026-08-01'], array_column($serie, 'periodo'));
        self::assertSame([198.0, 211.0, 205.0], array_column($serie, 'valor'));
    }

    /**
     * Volver a cargar el mismo mes corrige el dato: ni crea una segunda fila
     * ni revienta contra la clave única.
     */
    public function testCargarDosVecesElMismoMesCorrigeElDato(): void
    {
        $repositorio = $this->repositorio();
        $base = [
            'grupo' => 'contratacion',
            'nombre' => 'Contratos suscritos',
            'periodo' => '2026-07-01',
            'publicado' => 1,
        ];

        $primero = $repositorio->guardar($base + ['valor' => 300.0]);
        $segundo = $repositorio->guardar($base + ['valor' => 312.0]);

        self::assertSame($primero, $segundo);
        self::assertSame(1, (int) self::$conexion->fetchOne('SELECT COUNT(*) FROM indicadores'));
        self::assertSame(312.0, (float) self::$conexion->fetchOne('SELECT valor FROM indicadores'));
    }

    public function testLaPantallaDelPanelNoEsPublica(): void
    {
        $respuesta = $this->kernel->manejar(Request::create('/admin/indicadores'));

        self::assertSame(Response::HTTP_SEE_OTHER, $respuesta->getStatusCode());
        self::assertSame('/admin/entrar', $respuesta->headers->get('Location'));
    }

    public function testSinElPermisoLaPantallaDelPanelResponde403(): void
    {
        $this->autenticar($this->crearUsuarioConPermisos(['contenido.ver']));

        self::assertSame(
            Response::HTTP_FORBIDDEN,
            $this->kernel->manejar(Request::create('/admin/indicadores'))->getStatusCode(),
        );
    }

    public function testConElPermisoLaPantallaDelPanelAbre(): void
    {
        $this->autenticar($this->crearUsuarioConPermisos(['indicadores.ver']));

        self::assertSame(
            Response::HTTP_OK,
            $this->kernel->manejar(Request::create('/admin/indicadores'))->getStatusCode(),
        );
    }

    public function testUnPostSinTokenCsrfNoCargaNingunIndicador(): void
    {
        $this->autenticar($this->crearUsuarioConPermisos(['indicadores.ver', 'indicadores.editar']));

        $respuesta = $this->kernel->manejar(Request::create('/admin/indicadores', 'POST', [
            'nombre' => 'Indicador colado sin token',
            'grupo' => 'contratacion',
            'periodo' => '2026-07',
            'valor' => '999',
            'publicado' => '1',
        ]));

        self::assertSame(Response::HTTP_FORBIDDEN, $respuesta->getStatusCode());
        self::assertSame(0, (int) self::$conexion->fetchOne('SELECT COUNT(*) FROM indicadores'));
    }

    // --- soporte ---

    private function repositorio(): IndicadorRepository
    {
        return new IndicadorRepository(self::$conexion);
    }

    private function cargar(
        string $grupo,
        string $nombre,
        string $periodo,
        float $valor,
        int $publicado = 1,
    ): void {
        self::$conexion->insert('indicadores', [
            'grupo' => $grupo,
            'nombre' => $nombre,
            'slug' => Slugs::desde($nombre, 200),
            'periodo' => $periodo,
            'valor' => $valor,
            'unidad' => 'camas',
            'fuente' => 'Oficina de Planeación',
            'publicado' => $publicado,
        ]);
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

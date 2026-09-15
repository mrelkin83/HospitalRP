<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Core\Config\ConfigAplicacion;
use App\Core\Config\Env;
use App\Http\Kernel;
use App\Http\Web\CampanasPublicoController;
use App\Modules\Contenido\Repository\CampanaRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouteCollection;
use Tests\Support\BaseDatosDePruebas;

/**
 * Las dos audiencias de «Campañas y comunicados».
 *
 * La regla que se vigila: la audiencia decide DÓNDE sale cada pieza, nunca si
 * sale. Un comunicado al personal es público —es una E.S.E., no una empresa
 * privada—, pero no tiene por qué aparecer entre las campañas de salud de la
 * ciudadanía, ni al revés.
 */
#[CoversClass(CampanasPublicoController::class)]
#[CoversClass(CampanaRepository::class)]
final class CampanasPublicasTest extends TestCase
{
    private static Connection $conexion;
    private Kernel $kernel;

    /** @var list<string> claves de configuración que hay que devolver a su sitio */
    private array $ajustesTocados = [];

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM campanas');

        $raiz = dirname(__DIR__, 3);
        $env = Env::desdeSuperglobales();

        /** @var callable(Env): ContainerBuilder $fabrica */
        $fabrica = require $raiz . '/config/servicios.php';
        $contenedor = $fabrica($env);

        /** @var ConfigAplicacion $config */
        $config = $contenedor->get(ConfigAplicacion::class);

        /** @var RouteCollection $rutas */
        $rutas = require $raiz . '/config/rutas.php';

        $this->kernel = new Kernel($contenedor, $rutas, $config);
    }

    protected function tearDown(): void
    {
        foreach ($this->ajustesTocados as $clave) {
            self::$conexion->executeStatement('DELETE FROM configuracion WHERE clave = ?', [$clave]);
        }

        $this->ajustesTocados = [];
    }

    public function testElListadoCiudadanoSoloTraeCampanasDeComunidad(): void
    {
        $this->crearCampana('Jornada de vacunación infantil', 'comunidad');
        $this->crearCampana('Convocatoria para auxiliares de enfermería', 'personal');

        $cuerpo = $this->cuerpoDe('/campanas');

        self::assertStringContainsString('Jornada de vacunación infantil', $cuerpo);
        self::assertStringNotContainsString('Convocatoria para auxiliares de enfermería', $cuerpo);
    }

    public function testElPortalDeEmpleadosSoloTraeComunicadosAlPersonal(): void
    {
        $this->crearCampana('Jornada de vacunación infantil', 'comunidad');
        $this->crearCampana('Convocatoria para auxiliares de enfermería', 'personal');

        $cuerpo = $this->cuerpoDe('/trabajadores-y-contratistas');

        self::assertStringContainsString('Convocatoria para auxiliares de enfermería', $cuerpo);
        self::assertStringNotContainsString('Jornada de vacunación infantil', $cuerpo);
    }

    /** El portal del personal es público y existe aunque no haya comunicados. */
    public function testElPortalDeEmpleadosRespondeSinComunicados(): void
    {
        $respuesta = $this->kernel->manejar(Request::create('/trabajadores-y-contratistas'));

        self::assertSame(Response::HTTP_OK, $respuesta->getStatusCode());
        self::assertStringContainsString('Convocatorias', (string) $respuesta->getContent());
    }

    public function testElDetalleDeUnaCampanaPublicadaSeSirve(): void
    {
        $this->crearCampana('Acompañamiento a la lactancia', 'comunidad', slug: 'acompanamiento-lactancia');

        $respuesta = $this->kernel->manejar(Request::create('/campanas/acompanamiento-lactancia'));

        self::assertSame(Response::HTTP_OK, $respuesta->getStatusCode());
        self::assertStringContainsString('Acompañamiento a la lactancia', (string) $respuesta->getContent());
    }

    /**
     * Adivinar el slug de un borrador no puede destaparlo: el detalle aplica
     * las mismas condiciones que el listado.
     */
    public function testElDetalleDeUnBorradorEs404(): void
    {
        $this->crearCampana('Campaña a medio redactar', 'comunidad', slug: 'a-medio-redactar', estado: 'borrador');

        $respuesta = $this->kernel->manejar(Request::create('/campanas/a-medio-redactar'));

        self::assertSame(Response::HTTP_NOT_FOUND, $respuesta->getStatusCode());
    }

    public function testUnaCampanaVencidaTampocoTieneDetalle(): void
    {
        $this->crearCampana('Jornada que ya terminó', 'comunidad', slug: 'jornada-vencida', fin: '2026-01-31');

        $respuesta = $this->kernel->manejar(Request::create('/campanas/jornada-vencida'));

        self::assertSame(Response::HTTP_NOT_FOUND, $respuesta->getStatusCode());
    }

    /**
     * Lo último que publica Comunicaciones sale primero, aunque comparta
     * fecha de inicio con lo anterior.
     *
     * `inicio` es un DATE: todo lo que arranca hoy empata, y sin desempate
     * MySQL devuelve el orden que le convenga. Con el LIMIT de la portada eso
     * dejaba fuera campañas recién publicadas —pasó de verdad el 2026-09-15—
     * y desde el panel no había forma de entender por qué.
     */
    public function testLaCampanaMasNuevaSaleAntesAunqueEmpateLaFechaDeInicio(): void
    {
        $hoy = date('Y-m-d');

        $this->crearCampana('Campaña vieja del mismo día', 'comunidad', slug: 'vieja-mismo-dia', inicio: $hoy);
        $this->crearCampana('Campaña nueva del mismo día', 'comunidad', slug: 'nueva-mismo-dia', inicio: $hoy);

        $cuerpo = $this->cuerpoDe('/');

        self::assertLessThan(
            strpos($cuerpo, 'Campaña vieja del mismo día'),
            strpos($cuerpo, 'Campaña nueva del mismo día'),
            'La más reciente debe ir delante cuando las dos empiezan el mismo día.',
        );
    }

    /**
     * Cuántas campañas, cada cuánto rotan y en qué orden: lo decide el
     * hospital desde Ajustes del sitio, no una constante de PHP. Cambiar de 7
     * a 10 segundos no puede exigir un despliegue.
     */
    public function testElCarruselDeLaPortadaObedeceALaConfiguracion(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            $this->crearCampana('Campaña de ajuste ' . $i, 'comunidad', slug: 'ajuste-' . $i);
        }

        $this->ajustar('portada.campanas_cantidad', '2');
        $this->ajustar('portada.campanas_segundos', '10');

        $cuerpo = $this->cuerpoDe('/');

        self::assertSame(2, substr_count($cuerpo, 'class="comunicaciones-diapositiva"'));
        self::assertStringContainsString('data-rotacion-ms="10000"', $cuerpo);
    }

    /** Cero segundos apaga la rotación automática, sin tocar código. */
    public function testConCeroSegundosElCarruselNoRotaSolo(): void
    {
        $this->crearCampana('Campaña quieta', 'comunidad', slug: 'quieta');
        $this->ajustar('portada.campanas_segundos', '0');

        self::assertStringContainsString('data-rotacion-ms="0"', $this->cuerpoDe('/'));
    }

    /**
     * El otro orden disponible: primero lo que está por vencer. Útil cuando
     * hay jornadas con plazo; una campaña sin fecha de fin no urge y va al
     * final.
     */
    public function testElOrdenPorVencimientoPoneDelanteLoQueTerminaAntes(): void
    {
        $this->crearCampana('Termina pronto', 'comunidad', slug: 'termina-pronto', fin: date('Y-m-d', strtotime('+3 days')));
        $this->crearCampana('Termina tarde', 'comunidad', slug: 'termina-tarde', fin: date('Y-m-d', strtotime('+90 days')));
        $this->ajustar('portada.campanas_orden', 'vencen_antes');

        $cuerpo = $this->cuerpoDe('/');

        self::assertLessThan(
            strpos($cuerpo, 'Termina tarde'),
            strpos($cuerpo, 'Termina pronto'),
            'Con «vencen_antes» manda la fecha de cierre más cercana.',
        );
    }

    /**
     * La portada enseña las dos pestañas y cada una con lo suyo. Es la sección
     * que el 2026-09-14 quedó muda por usar Alpine sin cargarlo.
     */
    public function testLaPortadaMuestraLasDosPestanasConSuContenido(): void
    {
        $this->crearCampana('Jornada de vacunación infantil', 'comunidad');
        $this->crearCampana('Convocatoria para auxiliares de enfermería', 'personal');

        $respuesta = $this->kernel->manejar(Request::create('/'));
        $cuerpo = (string) $respuesta->getContent();

        self::assertSame(Response::HTTP_OK, $respuesta->getStatusCode());
        self::assertStringContainsString('Salud y Prevención', $cuerpo);
        self::assertStringContainsString('Portal de Empleados', $cuerpo);
        self::assertStringContainsString('Jornada de vacunación infantil', $cuerpo);
        self::assertStringContainsString('Convocatoria para auxiliares de enfermería', $cuerpo);
    }

    /**
     * Las directivas de Alpine de esa sección solo funcionan si Alpine se
     * carga en la portada: sin el script, `x-show` no esconde nada y las dos
     * pestañas se ven a la vez, que fue exactamente el fallo anterior.
     */
    public function testLaPortadaCargaAlpineYSuComponente(): void
    {
        $this->crearCampana('Jornada de vacunación infantil', 'comunidad');

        $cuerpo = $this->cuerpoDe('/');

        self::assertStringContainsString('/js/alpine.js', $cuerpo);
        self::assertStringContainsString('/js/comunicaciones.js', $cuerpo);
        self::assertStringContainsString('x-data="comunicaciones"', $cuerpo);
    }

    // --- soporte ---

    /** Deja un ajuste como lo dejaría el funcionario en el panel. */
    private function ajustar(string $clave, string $valor): void
    {
        self::$conexion->executeStatement(
            "INSERT INTO configuracion (clave, valor, tipo, grupo, etiqueta, orden, editable)
                  VALUES (?, ?, 'texto', 'portada', ?, 0, 1)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)",
            [$clave, $valor, $clave],
        );

        $this->ajustesTocados[] = $clave;
    }

    private function cuerpoDe(string $ruta): string
    {
        $respuesta = $this->kernel->manejar(Request::create($ruta));

        self::assertSame(Response::HTTP_OK, $respuesta->getStatusCode());

        return (string) $respuesta->getContent();
    }

    private function crearCampana(
        string $titulo,
        string $audiencia,
        ?string $slug = null,
        string $estado = 'publicado',
        ?string $fin = null,
        ?string $inicio = null,
    ): void {
        self::$conexion->insert('campanas', [
            'slug' => $slug ?? 'prueba-' . bin2hex(random_bytes(6)),
            'titulo' => $titulo,
            'resumen' => 'Resumen de prueba.',
            'contenido' => '<p>Contenido de prueba.</p>',
            'audiencia' => $audiencia,
            'estado_aprobacion' => $estado,
            'inicio' => $inicio,
            'fin' => $fin,
        ]);
    }
}

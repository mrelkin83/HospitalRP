<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Core\Config\ConfigAplicacion;
use App\Core\Config\Env;
use App\Http\Kernel;
use App\Http\Web\TransparenciaPublicoController;
use App\Modules\CuentasCobro\Service\EjecucionContractualPublica;
use App\Modules\Tramites\Service\InformeAccesoInformacion;
use App\Modules\Transparencia\Repository\EnteVigilanciaRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouteCollection;
use Tests\Support\BaseDatosDePruebas;

/**
 * Las pantallas que cierran las pérdidas del ITA 2026: ejecución contractual
 * (5.3), entes que vigilan (3.13) e informe de acceso a la información (6.10).
 *
 * Lo que más importa verificar aquí es la aritmética de la ejecución: un
 * porcentaje mal calculado en la página de contratación de un hospital
 * intervenido no es un error cosmético.
 */
#[CoversClass(TransparenciaPublicoController::class)]
#[CoversClass(EjecucionContractualPublica::class)]
#[CoversClass(InformeAccesoInformacion::class)]
#[CoversClass(EnteVigilanciaRepository::class)]
final class TransparenciaIta2026Test extends TestCase
{
    private static Connection $conexion;
    private ContainerBuilder $contenedor;
    private Kernel $kernel;
    private EjecucionContractualPublica $ejecucion;
    private int $cuota = 0;
    private int $usuarioId;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::limpiar();

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
        $this->ejecucion = new EjecucionContractualPublica(self::$conexion);
        $this->cuota = 0;

        // `cc_contrato_novedades.creado_por` exige un usuario real.
        self::$conexion->insert('roles', [
            'nombre' => 'Rol ITA', 'slug' => 'prueba-ita-' . bin2hex(random_bytes(5)), 'permisos' => '[]',
        ]);
        self::$conexion->insert('usuarios', [
            'nombre' => 'Usuario ITA',
            'correo' => 'prueba-ita@ejemplo.test',
            'hash_password' => 'x',
            'rol_id' => (int) self::$conexion->lastInsertId(),
        ]);
        $this->usuarioId = (int) self::$conexion->lastInsertId();
    }

    protected function tearDown(): void
    {
        self::limpiar();
    }

    /**
     * Los seis literales del criterio 5.3, sobre un contrato de 12.000.000
     * con 3.000.000 ya aprobados: 25 % ejecutado y 9.000.000 pendientes.
     */
    public function testLaEjecucionContractualCalculaPorcentajeYPendiente(): void
    {
        $contratoId = $this->sembrarContrato('CT-TEST-1', 12_000_000);
        $this->sembrarCuenta($contratoId, 2_000_000, 'APROBADA');
        $this->sembrarCuenta($contratoId, 1_000_000, 'ENTREGADA_FINANCIERA');

        // Un borrador no es un desembolso: no debe entrar en la cifra.
        $this->sembrarCuenta($contratoId, 5_000_000, 'BORRADOR');

        $contratos = $this->ejecucion->contratos();

        self::assertCount(1, $contratos);
        self::assertSame(3_000_000.0, (float) $contratos[0]['pagado']);
        self::assertSame(9_000_000.0, (float) $contratos[0]['pendiente']);
        self::assertSame(25.0, (float) $contratos[0]['porcentaje']);
    }

    public function testLasAdicionesSeCuentanConSuMonto(): void
    {
        $contratoId = $this->sembrarContrato('CT-TEST-2', 10_000_000);

        self::$conexion->insert('cc_contrato_novedades', [
            'contrato_id' => $contratoId, 'tipo' => 'ADICION',
            'fecha' => '2026-03-01', 'valor' => 2_000_000, 'descripcion' => 'Prueba', 'creado_por' => $this->usuarioId,
        ]);
        self::$conexion->insert('cc_contrato_novedades', [
            'contrato_id' => $contratoId, 'tipo' => 'PRORROGA',
            'fecha' => '2026-04-01', 'valor' => null, 'descripcion' => 'Prueba', 'creado_por' => $this->usuarioId,
        ]);
        // Una suspensión no es adición ni prórroga: no la cuenta el criterio.
        self::$conexion->insert('cc_contrato_novedades', [
            'contrato_id' => $contratoId, 'tipo' => 'SUSPENSION',
            'fecha' => '2026-05-01', 'valor' => null, 'descripcion' => 'Prueba', 'creado_por' => $this->usuarioId,
        ]);

        $contratos = $this->ejecucion->contratos();

        self::assertSame(2, (int) $contratos[0]['novedades']);
        self::assertSame(2_000_000.0, (float) $contratos[0]['valor_adiciones']);
    }

    /** Un contrato en revisión es un borrador: no se publica. */
    public function testElContratoEnRevisionNoSePublica(): void
    {
        $this->sembrarContrato('CT-TEST-3', 5_000_000, 'EN_REVISION');
        $this->sembrarContrato('CT-TEST-4', 5_000_000, 'ANULADO');

        self::assertSame([], $this->ejecucion->contratos());
    }

    /** Un contrato sin valor no puede tumbar la página con una división por cero. */
    public function testUnContratoSinValorReportaCeroPorCientoYNoRompe(): void
    {
        $this->sembrarContrato('CT-TEST-5', 0);

        $contratos = $this->ejecucion->contratos();

        self::assertSame(0.0, (float) $contratos[0]['porcentaje']);
        self::assertSame(0.0, (float) $contratos[0]['pendiente']);
    }

    /**
     * El criterio 5.3 no pide la identidad del contratista, y publicarla aquí
     * la duplicaría respecto del directorio de servidores públicos.
     */
    public function testLaEjecucionNoPublicaElNombreDelContratista(): void
    {
        $this->sembrarContrato('CT-TEST-6', 1_000_000);

        $html = $this->contenido('/transparencia/ejecucion-contractual');

        self::assertStringContainsString('CT-TEST-6', $html);
        self::assertStringNotContainsString('Nombrecontratista', $html);
        self::assertStringNotContainsString('Apellidocontratista', $html);
    }

    public function testLosEntesDeVigilanciaPublicanLosSieteLiteralesDelCriterio(): void
    {
        self::$conexion->insert('entes_vigilancia', [
            'nombre' => 'Ente de prueba',
            'tipo_control' => 'fiscal',
            'direccion' => 'Calle 1 n.º 2-3, Valledupar',
            'telefono' => '+57 605 000 0000',
            'correo' => 'ente@ejemplo.test',
            'url' => 'https://ejemplo.test',
            'mecanismos' => 'Auditoría anual y requerimientos.',
            'publicado' => 1,
        ]);

        $html = $this->contenido('/transparencia/entes-de-control');

        self::assertStringContainsString('Ente de prueba', $html);
        self::assertStringContainsString('Control fiscal', $html);
        self::assertStringContainsString('Calle 1 n.º 2-3, Valledupar', $html);
        self::assertStringContainsString('+57 605 000 0000', $html);
        self::assertStringContainsString('ente@ejemplo.test', $html);
        self::assertStringContainsString('https://ejemplo.test', $html);
        self::assertStringContainsString('Auditoría anual y requerimientos.', $html);
    }

    public function testElEnteSinPublicarNoApareceEnElSitio(): void
    {
        self::$conexion->insert('entes_vigilancia', [
            'nombre' => 'Ente oculto', 'tipo_control' => 'otro', 'publicado' => 0,
        ]);

        self::assertStringNotContainsString('Ente oculto', $this->contenido('/transparencia/entes-de-control'));
    }

    /**
     * El informe de acceso responde aunque no haya ni una solicitud radicada:
     * una página de transparencia que da error 500 es peor que una vacía.
     */
    public function testElInformeDeAccesoRespondeYSeDescargaEnCsv(): void
    {
        $this->contenido('/transparencia/informe-de-acceso-a-la-informacion');

        $csv = $this->kernel->manejar(
            Request::create('/transparencia/informe-de-acceso-a-la-informacion.csv'),
        );

        self::assertSame(Response::HTTP_OK, $csv->getStatusCode());
        self::assertStringContainsString('text/csv', (string) $csv->headers->get('Content-Type'));
        self::assertStringContainsString('Solicitudes con acceso negado', (string) $csv->getContent());
    }

    public function testLaEjecucionContractualSeDescargaEnCsvConSusSeisLiterales(): void
    {
        $this->sembrarContrato('CT-TEST-7', 4_000_000);

        $csv = (string) $this->kernel->manejar(
            Request::create('/transparencia/ejecucion-contractual.csv'),
        )->getContent();

        foreach ([
            'Fecha de inicio', 'Fecha de finalización', 'Valor del contrato',
            'Porcentaje de ejecución', 'Recursos desembolsados',
            'Recursos pendientes de ejecutar', 'Cantidad de otrosíes y adiciones',
        ] as $encabezado) {
            self::assertStringContainsString($encabezado, $csv, "Falta la columna «{$encabezado}».");
        }

        self::assertStringContainsString('CT-TEST-7', $csv);
    }

    // --- soporte ---

    private function sembrarContrato(string $numero, int $valor, string $estado = 'ACTIVO'): int
    {
        self::$conexion->insert('cc_contratistas', [
            'numero_documento' => '10999999' . substr(md5($numero), 0, 2),
            'lugar_expedicion' => 'Valledupar',
            'nombres' => 'Nombrecontratista',
            'apellidos' => 'Apellidocontratista',
            'celular' => '3000000000',
            'correo' => 'prueba-ita@ejemplo.test',
            'banco' => 'Banco de Prueba',
            'tipo_cuenta' => 'AHORROS',
            'numero_cuenta' => '000000000',
            'clase_riesgo_arl' => 1,
        ]);
        $contratistaId = (int) self::$conexion->lastInsertId();

        self::$conexion->insert('cc_contratos', [
            'numero' => $numero,
            'contratista_id' => $contratistaId,
            'fecha_suscripcion' => '2026-01-15',
            'fecha_acta_inicio' => '2026-02-01',
            'fecha_terminacion' => '2026-12-31',
            'objeto' => 'Objeto de prueba del contrato.',
            'valor_total' => $valor,
            'valor_total_letras' => 'VALOR EN LETRAS',
            'plazo_texto' => 'ONCE (11) MESES',
            'plazo_meses' => 11,
            'estado' => $estado,
        ]);

        return (int) self::$conexion->lastInsertId();
    }

    /** Una cuenta cuelga de una cuota del plan de pagos, así que va primero. */
    private function sembrarCuenta(int $contratoId, int $valor, string $estado): void
    {
        self::$conexion->insert('cc_contrato_pagos', [
            'contrato_id' => $contratoId,
            'numero_cuota' => ++$this->cuota,
            'descripcion' => 'Cuota de prueba',
            'valor' => $valor,
        ]);
        $pagoId = (int) self::$conexion->lastInsertId();

        self::$conexion->insert('cc_cuentas', [
            'contrato_id' => $contratoId,
            'pago_id' => $pagoId,
            'consecutivo' => $this->cuota,
            'periodo_desde' => '2026-02-01',
            'periodo_hasta' => '2026-02-28',
            'dias_periodo' => 28,
            'fecha_cobro' => '2026-03-01',
            'valor' => $valor,
            'valor_letras' => 'VALOR EN LETRAS',
            'estado' => $estado,
        ]);
    }

    private function contenido(string $ruta): string
    {
        $respuesta = $this->kernel->manejar(Request::create($ruta));

        self::assertSame(Response::HTTP_OK, $respuesta->getStatusCode(), $ruta . ' no respondió 200.');

        $html = $respuesta->getContent();
        self::assertIsString($html);

        return $html;
    }

    private static function limpiar(): void
    {
        // Orden inverso al de creación, por las llaves foráneas.
        $deContratosDePrueba = "SELECT id FROM (SELECT id FROM cc_contratos WHERE numero LIKE 'CT-TEST-%') t";

        self::$conexion->executeStatement("DELETE FROM cc_contrato_novedades WHERE contrato_id IN ({$deContratosDePrueba})");
        self::$conexion->executeStatement("DELETE FROM cc_cuentas WHERE contrato_id IN ({$deContratosDePrueba})");
        self::$conexion->executeStatement("DELETE FROM cc_contrato_pagos WHERE contrato_id IN ({$deContratosDePrueba})");
        self::$conexion->executeStatement("DELETE FROM cc_contratos WHERE numero LIKE 'CT-TEST-%'");
        self::$conexion->executeStatement("DELETE FROM cc_contratistas WHERE correo = 'prueba-ita@ejemplo.test'");
        self::$conexion->executeStatement("DELETE FROM usuarios WHERE correo = 'prueba-ita@ejemplo.test'");
        self::$conexion->executeStatement("DELETE FROM roles WHERE slug LIKE 'prueba-ita-%'");
        self::$conexion->executeStatement('DELETE FROM entes_vigilancia');
    }
}

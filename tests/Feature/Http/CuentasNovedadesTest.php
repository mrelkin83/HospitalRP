<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Core\Auth\Sesion;
use App\Core\Auth\Usuario;
use App\Core\Config\ConfigAplicacion;
use App\Core\Config\Env;
use App\Http\Kernel;
use App\Modules\CuentasCobro\Command\RecordatoriosCuentasCommand;
use App\Modules\CuentasCobro\Repository\CuentaRepository;
use App\Modules\CuentasCobro\Repository\ParametrosCuentasRepository;
use App\Modules\CuentasCobro\Service\ArchivosCuentas;
use App\Modules\CuentasCobro\Service\GeneradorDocumentosCuenta;
use App\Modules\CuentasCobro\Service\NovedadesContratoService;
use App\Modules\Tramites\Service\CalculadoraDiasHabiles;
use App\Ports\NotificadorCorreo;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouteCollection;
use Tests\Support\BaseDatosDePruebas;

/**
 * La fase 4 del módulo contra sus criterios de aceptación (§12):
 *
 * - adición de $2.850.000 y un mes al 1407-2026 → cuota 6 de enero de
 *   2027, y VALOR ADICIÓN / VALOR CONTRATO + ADICIÓN en el informe de
 *   supervisión;
 * - suspensión del 10 al 20 de septiembre → la cuota de septiembre se
 *   prorratea a 20 días y las siguientes se desplazan, sin tocar ninguna
 *   cuenta ya aprobada; y
 * - el reporte de tiempos por supervisor exporta CSV.
 */
#[CoversClass(NovedadesContratoService::class)]
final class CuentasNovedadesTest extends TestCase
{
    private static Connection $conexion;
    private ContainerBuilder $contenedor;
    private Kernel $kernel;
    private int $contratoId;
    private int $contratistaId;
    private int $supervisorId;

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

        self::limpiar();

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

        self::$conexion->insert('roles', [
            'nombre' => 'Rol admin cc',
            'slug' => 'prueba-' . bin2hex(random_bytes(6)),
            'permisos' => json_encode(['cuentas.administrar'], JSON_THROW_ON_ERROR),
        ]);
        self::$conexion->insert('usuarios', [
            'nombre' => 'Admin de cuentas',
            'correo' => 'prueba-admincc@hospital.gov.co',
            'hash_password' => 'x',
            'rol_id' => (int) self::$conexion->lastInsertId(),
        ]);
        $usuarioId = (int) self::$conexion->lastInsertId();

        self::$conexion->insert('cc_contratistas', [
            'numero_documento' => '1166666601',
            'nombres' => 'Prueba',
            'apellidos' => 'Novedades',
            'correo' => 'contratista4@ejemplo.test',
        ]);
        $this->contratistaId = (int) self::$conexion->lastInsertId();

        self::$conexion->insert('cc_supervisores', [
            'usuario_id' => $usuarioId,
            'nombre_completo' => 'SUPERVISOR NOVEDADES',
            'cargo' => 'PROFESIONAL UNIVERSITARIO',
        ]);
        $this->supervisorId = (int) self::$conexion->lastInsertId();

        // El 1407-2026 del criterio de aceptación: 5 × 2.850.000, agosto a
        // diciembre, acta del 2026-08-01, terminación 2026-12-31.
        self::$conexion->insert('cc_contratos', [
            'numero' => '1407-2026',
            'contratista_id' => $this->contratistaId,
            'supervisor_id' => $this->supervisorId,
            'fecha_suscripcion' => '2026-08-01',
            'objeto' => 'Apoyo a la gestión como tecnólogo en sistemas.',
            'valor_total' => 14250000,
            'valor_total_letras' => 'CATORCE MILLONES DOSCIENTOS CINCUENTA MIL PESOS M/CTE',
            'plazo_texto' => 'CINCO (5) MESES',
            'estado' => 'ACTIVO',
            'fecha_acta_inicio' => '2026-08-01',
            'fecha_terminacion' => '2026-12-31',
        ]);
        $this->contratoId = (int) self::$conexion->lastInsertId();

        foreach ([
            [1, '2026-08-01', '2026-08-31', 31],
            [2, '2026-09-01', '2026-09-30', 30],
            [3, '2026-10-01', '2026-10-31', 31],
            [4, '2026-11-01', '2026-11-30', 30],
            [5, '2026-12-01', '2026-12-31', 31],
        ] as [$n, $desde, $hasta, $dias]) {
            self::$conexion->insert('cc_contrato_pagos', [
                'contrato_id' => $this->contratoId,
                'numero_cuota' => $n,
                'descripcion' => 'Mensualidad ' . $n,
                'tipo' => 'COMPLETA',
                'mes' => substr($desde, 0, 7),
                'periodo_desde' => $desde,
                'periodo_hasta' => $hasta,
                'dias' => $dias,
                'valor' => 2850000,
            ]);
        }

        $this->autenticar($usuarioId);
    }

    protected function tearDown(): void
    {
        self::limpiar();
    }

    private static function limpiar(): void
    {
        // Solo la base DE PRUEBAS, en orden inverso a las FK.
        foreach ([
            'cc_descarga_tokens', 'cc_cuenta_firmas', 'cc_cuenta_historial', 'cc_cuenta_adjuntos',
            'cc_cuenta_seguridad_social', 'cc_cuenta_actividades', 'cc_cuentas',
            'cc_extracciones', 'cc_otp', 'cc_contrato_pagos', 'cc_contrato_obligaciones',
            'cc_contrato_imputaciones', 'cc_contrato_novedades', 'cc_contratos',
            'cc_supervisores', 'cc_contratistas',
        ] as $tabla) {
            self::$conexion->executeStatement('DELETE FROM ' . $tabla);
        }

        self::$conexion->executeStatement('DELETE FROM usuarios');
        self::$conexion->executeStatement("DELETE FROM roles WHERE slug LIKE 'prueba-%'");
    }

    /**
     * Criterio de aceptación: adición de $2.850.000 y un mes → cuota 6 de
     * enero de 2027, y los renglones de adición en el informe GJ-FR-004.
     */
    public function testAdicionAgregaLaCuotaDeEnero(): void
    {
        $respuesta = $this->post('/admin/cuentas-cobro/contratos/' . $this->contratoId . '/novedades', [
            'tipo' => 'ADICION',
            'fecha' => '2026-12-15',
            'valor' => '2850000',
            'plazo_meses' => '1',
            'plazo_dias' => '0',
            'descripcion' => 'Adición de un mes',
        ]);
        self::assertSame('/admin/cuentas-cobro/contratos/' . $this->contratoId, $respuesta->headers->get('Location'));

        $cuota6 = self::$conexion->fetchAssociative(
            'SELECT * FROM cc_contrato_pagos WHERE contrato_id = ? AND numero_cuota = 6',
            [$this->contratoId],
        );
        self::assertNotFalse($cuota6, 'La adición no creó la cuota 6.');
        self::assertSame('2027-01-01', $cuota6['periodo_desde']);
        self::assertSame('2027-01-31', $cuota6['periodo_hasta']);
        self::assertSame(2850000, (int) explode('.', (string) $cuota6['valor'], 2)[0]);

        self::assertSame(
            '2027-01-31',
            self::$conexion->fetchOne('SELECT fecha_terminacion FROM cc_contratos WHERE id = ?', [$this->contratoId]),
        );

        // El cronograma completo suma valor + adición.
        self::assertSame(
            17100000,
            (int) explode('.', (string) self::$conexion->fetchOne(
                'SELECT SUM(valor) FROM cc_contrato_pagos WHERE contrato_id = ?',
                [$this->contratoId],
            ), 2)[0],
        );

        // Y el informe de supervisión de una cuenta del contrato trae
        // VALOR ADICIÓN y VALOR CONTRATO + ADICIÓN.
        $cuentaId = $this->crearCuentaBorrador(1);
        $supervision = $this->generador()->vistaPrevia('INFORME_SUPERVISION', $cuentaId);
        self::assertStringContainsString('VALOR ADICIÓN', $supervision);
        self::assertStringContainsString('$ 2.850.000', $supervision);
        self::assertStringContainsString('$ 17.100.000', $supervision, 'Falta VALOR CONTRATO + ADICIÓN.');
    }

    /**
     * Criterio de aceptación: suspensión del 10 al 20 de septiembre → la
     * cuota de septiembre queda de 20 días, las siguientes se desplazan y
     * la cuenta aprobada de agosto no cambia.
     */
    public function testSuspensionProrrateaYDesplazaSinTocarLoAprobado(): void
    {
        // Una cuenta APROBADA sobre la cuota de agosto, que debe quedar intacta.
        $agostoId = $this->crearCuentaBorrador(1);
        self::$conexion->update('cc_cuentas', [
            'estado' => 'APROBADA',
            'radicado' => 'CC-2026-000901',
            'pagos_anteriores' => 0,
            'saldo_por_ejecutar' => 11400000,
        ], ['id' => $agostoId]);

        $respuesta = $this->post('/admin/cuentas-cobro/contratos/' . $this->contratoId . '/novedades', [
            'tipo' => 'SUSPENSION',
            'fecha_desde' => '2026-09-10',
            'fecha_hasta' => '2026-09-20',
            'descripcion' => 'Suspensión de mutuo acuerdo',
        ]);
        self::assertSame('/admin/cuentas-cobro/contratos/' . $this->contratoId, $respuesta->headers->get('Location'));

        $pagos = self::$conexion->fetchAllAssociative(
            'SELECT * FROM cc_contrato_pagos WHERE contrato_id = ? ORDER BY numero_cuota',
            [$this->contratoId],
        );

        // Septiembre: prorrateada a 20 días → 2.850.000 − 950.000 = 1.900.000.
        self::assertSame(20, (int) $pagos[1]['dias']);
        self::assertSame(1900000, (int) explode('.', (string) $pagos[1]['valor'], 2)[0]);

        // Octubre y siguientes: desplazadas 10 días.
        self::assertSame('2026-10-11', $pagos[2]['periodo_desde']);
        self::assertSame('2026-11-10', $pagos[2]['periodo_hasta']);
        self::assertSame('2026-12-11', $pagos[4]['periodo_desde']);
        self::assertSame('2027-01-10', $pagos[4]['periodo_hasta']);

        // El remanente recupera lo descontado: la suma total no cambia.
        self::assertCount(6, $pagos);
        self::assertSame(950000, (int) explode('.', (string) $pagos[5]['valor'], 2)[0]);
        self::assertSame(
            14250000,
            (int) explode('.', (string) self::$conexion->fetchOne(
                'SELECT SUM(valor) FROM cc_contrato_pagos WHERE contrato_id = ?',
                [$this->contratoId],
            ), 2)[0],
        );

        // Agosto (cuota y cuenta aprobada) quedó como estaba, y el contrato SUSPENDIDO.
        self::assertSame('2026-08-31', $pagos[0]['periodo_hasta']);
        self::assertSame('APROBADA', self::$conexion->fetchOne('SELECT estado FROM cc_cuentas WHERE id = ?', [$agostoId]));
        self::assertSame('SUSPENDIDO', self::$conexion->fetchOne('SELECT estado FROM cc_contratos WHERE id = ?', [$this->contratoId]));

        // El reinicio lo devuelve a ACTIVO.
        $this->post('/admin/cuentas-cobro/contratos/' . $this->contratoId . '/novedades', [
            'tipo' => 'REINICIO',
            'fecha' => '2026-09-20',
        ]);
        self::assertSame('ACTIVO', self::$conexion->fetchOne('SELECT estado FROM cc_contratos WHERE id = ?', [$this->contratoId]));
    }

    /** Terminación anticipada: prorratea la cuota del corte y borra las futuras. */
    public function testTerminacionAnticipadaProrratea(): void
    {
        $this->post('/admin/cuentas-cobro/contratos/' . $this->contratoId . '/novedades', [
            'tipo' => 'TERMINACION_ANTICIPADA',
            'fecha' => '2026-10-15',
            'descripcion' => 'Terminación de mutuo acuerdo',
        ]);

        $pagos = self::$conexion->fetchAllAssociative(
            'SELECT * FROM cc_contrato_pagos WHERE contrato_id = ? ORDER BY numero_cuota',
            [$this->contratoId],
        );

        self::assertCount(3, $pagos, 'Noviembre y diciembre debían desaparecer.');
        self::assertSame('2026-10-15', $pagos[2]['periodo_hasta']);
        self::assertSame(15, (int) $pagos[2]['dias']);
        self::assertSame(1425000, (int) explode('.', (string) $pagos[2]['valor'], 2)[0]);

        $contrato = self::$conexion->fetchAssociative('SELECT estado, fecha_terminacion FROM cc_contratos WHERE id = ?', [$this->contratoId]);
        self::assertSame('TERMINADO', $contrato['estado']);
        self::assertSame('2026-10-15', $contrato['fecha_terminacion']);
    }

    /** Anular libera la cuota y el consecutivo se reutiliza (§5 y §8.5). */
    public function testAnularCuentaLiberaCuotaYConsecutivo(): void
    {
        $cuentaId = $this->crearCuentaBorrador(1);

        $this->post('/admin/cuentas-cobro/cuentas/' . $cuentaId . '/anular', [
            'motivo' => 'Se radicó con el periodo equivocado.',
        ]);

        $anulada = self::$conexion->fetchAssociative('SELECT * FROM cc_cuentas WHERE id = ?', [$cuentaId]);
        self::assertSame('ANULADA', $anulada['estado']);
        self::assertGreaterThan(1, (int) $anulada['consecutivo'], 'La anulada debía correr su consecutivo.');

        // La reemplazante vuelve a ser la N° 1.
        $nuevaId = $this->crearCuentaBorrador(1);
        self::assertSame(1, (int) self::$conexion->fetchOne('SELECT consecutivo FROM cc_cuentas WHERE id = ?', [$nuevaId]));

        // Y el historial guarda la anulación con su motivo.
        self::assertSame(
            1,
            (int) self::$conexion->fetchOne(
                "SELECT COUNT(*) FROM cc_cuenta_historial WHERE cuenta_id = ? AND estado_nuevo = 'ANULADA' AND rol = 'ADMIN'",
                [$cuentaId],
            ),
        );
    }

    /** Criterio de aceptación: el reporte de tiempos por supervisor exporta CSV. */
    public function testReporteDeTiemposExportaCsv(): void
    {
        // Una cuenta aprobada con tiempos conocidos (2 días exactos).
        $cuentaId = $this->crearCuentaBorrador(1);
        self::$conexion->update('cc_cuentas', [
            'estado' => 'APROBADA',
            'radicado' => 'CC-2026-000902',
            'enviada_en' => '2026-09-01 08:00:00',
            'aprobada_en' => '2026-09-03 08:00:00',
            'aprobada_por' => $this->supervisorId,
        ], ['id' => $cuentaId]);

        $respuesta = $this->kernel->manejar(Request::create('/admin/cuentas-cobro/reportes?csv=tiempos'));

        self::assertSame(Response::HTTP_OK, $respuesta->getStatusCode());
        self::assertStringContainsString('text/csv', (string) $respuesta->headers->get('Content-Type'));
        $contenido = (string) $respuesta->getContent();
        self::assertStringContainsString('SUPERVISOR NOVEDADES', $contenido);
        self::assertStringContainsString('dias_promedio', $contenido);
        self::assertStringContainsString('2.0', $contenido, 'El promedio de 2 días no aparece.');
    }

    /** Los recordatorios salen al contratista moroso; el correo caído no frena. */
    public function testRecordatorios(): void
    {
        // Cuota vencida ayer sin cuenta (día 1 → toca recordar).
        self::$conexion->executeStatement(
            'UPDATE cc_contrato_pagos SET periodo_hasta = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE contrato_id = ? AND numero_cuota = 1',
            [$this->contratoId],
        );

        $correo = new class implements NotificadorCorreo {
            /** @var list<string> */
            public array $asuntos = [];

            public function enviar(string $destinatario, string $asunto, string $textoPlano): void
            {
                $this->asuntos[] = $asunto;
            }
        };

        $comando = new RecordatoriosCuentasCommand(
            self::$conexion,
            $correo,
            new CalculadoraDiasHabiles(self::$conexion),
        );
        $tester = new CommandTester($comando);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertNotEmpty(
            array_filter($correo->asuntos, static fn (string $a): bool => str_contains($a, 'Ya puede presentar')),
            'No salió el recordatorio al contratista.',
        );
    }

    // ------------------------------------------------------------------
    // Ayudas
    // ------------------------------------------------------------------

    /** Crea un borrador directo sobre una cuota, sin pasar por el portal. */
    private function crearCuentaBorrador(int $numeroCuota): int
    {
        $pago = self::$conexion->fetchAssociative(
            'SELECT * FROM cc_contrato_pagos WHERE contrato_id = ? AND numero_cuota = ?',
            [$this->contratoId, $numeroCuota],
        );
        self::assertNotFalse($pago);

        return (new CuentaRepository(self::$conexion))->crearBorrador(
            $this->contratoId,
            $pago,
            'VALOR EN LETRAS DE PRUEBA',
        );
    }

    private function generador(): GeneradorDocumentosCuenta
    {
        $raiz = dirname(__DIR__, 3);

        return new GeneradorDocumentosCuenta(
            self::$conexion,
            new ParametrosCuentasRepository(self::$conexion),
            new ArchivosCuentas($raiz . '/storage/cuentas'),
            $raiz . '/templates/pdf',
            $raiz . '/public/img/logo.png',
        );
    }

    private function autenticar(int $usuarioId): void
    {
        /** @var Sesion $sesion */
        $sesion = $this->contenedor->get(Sesion::class);
        $sesion->iniciar();
        $sesion->autenticar(new Usuario(
            id: $usuarioId,
            nombre: 'Admin de cuentas',
            correo: 'prueba-admincc@hospital.gov.co',
            hashPassword: '',
            rolId: 0,
            activo: true,
            intentosFallidos: 0,
            bloqueadoHasta: null,
            debeCambiarPassword: false,
        ));
    }

    /** @param array<string, mixed> $campos */
    private function post(string $ruta, array $campos): Response
    {
        /** @var Sesion $sesion */
        $sesion = $this->contenedor->get(Sesion::class);
        $sesion->iniciar();
        $campos['_token'] = $sesion->tokenCsrf();

        return $this->kernel->manejar(Request::create($ruta, 'POST', $campos));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Core\Auth\Sesion;
use App\Core\Auth\Usuario;
use App\Core\Config\ConfigAplicacion;
use App\Core\Config\Env;
use App\Http\Admin\CuentasSupervisionController;
use App\Http\Kernel;
use App\Modules\CuentasCobro\Repository\ParametrosCuentasRepository;
use App\Modules\CuentasCobro\Service\ArchivosCuentas;
use App\Modules\CuentasCobro\Service\GeneradorDocumentosCuenta;
use App\Modules\CuentasCobro\Service\SesionContratista;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouteCollection;
use Tests\Support\BaseDatosDePruebas;

/**
 * La fase 3 del módulo contra sus criterios de aceptación (§12):
 *
 * - aprobar la N° 1 y crear la N° 2: los «pagos anteriores acumulados» de
 *   la N° 2 son el valor de la N° 1, y el saldo cuadra;
 * - devolver: el contratista corrige y reenvía, y el historial muestra las
 *   tres transiciones;
 * - el informe de supervisión lleva LAS MISMAS obligaciones y
 *   descripciones que el informe de actividades; y
 * - ningún campo del avance financiero queda en blanco.
 */
#[CoversClass(CuentasSupervisionController::class)]
final class CuentasSupervisionTest extends TestCase
{
    private const string PNG_MINIMO = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private static Connection $conexion;
    private ContainerBuilder $contenedor;
    private Kernel $kernel;
    private int $contratistaId;
    private int $contratoId;
    private int $supervisorId;
    private int $usuarioSupervisorId;

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

        $dirFirmas = $raiz . '/storage/cuentas/firmas';

        if (!is_dir($dirFirmas)) {
            mkdir($dirFirmas, 0755, true);
        }

        file_put_contents($dirFirmas . '/prueba-firma.png', base64_decode(self::PNG_MINIMO, true));

        // Usuario supervisor con el permiso de la bandeja.
        self::$conexion->insert('roles', [
            'nombre' => 'Rol supervisor',
            'slug' => 'prueba-' . bin2hex(random_bytes(6)),
            'permisos' => json_encode(['cuentas.supervisar'], JSON_THROW_ON_ERROR),
        ]);
        self::$conexion->insert('usuarios', [
            'nombre' => 'Supervisor de fase 3',
            'correo' => 'prueba-sup3@hospital.gov.co',
            'hash_password' => 'x',
            'rol_id' => (int) self::$conexion->lastInsertId(),
        ]);
        $this->usuarioSupervisorId = (int) self::$conexion->lastInsertId();

        self::$conexion->insert('cc_contratistas', [
            'numero_documento' => '1177777701',
            'lugar_expedicion' => 'Valledupar',
            'nombres' => 'Prueba',
            'apellidos' => 'Supervisión',
            'celular' => '3000000002',
            'correo' => 'contratista3@ejemplo.test',
            'banco' => 'Banco de Prueba',
            'tipo_cuenta' => 'AHORROS',
            'numero_cuenta' => '987654321',
            'clase_riesgo_arl' => 3,
            'firma_archivo' => 'firmas/prueba-firma.png',
            'cedula_archivo' => 'firmas/prueba-firma.png',
            'rut_archivo' => 'firmas/prueba-firma.png',
            'datos_completos' => 1,
            'acepto_tratamiento_datos_en' => date('Y-m-d H:i:s'),
        ]);
        $this->contratistaId = (int) self::$conexion->lastInsertId();

        self::$conexion->insert('cc_supervisores', [
            'usuario_id' => $this->usuarioSupervisorId,
            'nombre_completo' => 'KENNYS MELGAREJO ZULETA',
            'cargo' => 'PROFESIONAL UNIVERSITARIO (FACTURACIÓN)',
            'firma_archivo' => 'firmas/prueba-firma.png',
        ]);
        $this->supervisorId = (int) self::$conexion->lastInsertId();

        self::$conexion->insert('cc_contratos', [
            'numero' => '1407-2026',
            'contratista_id' => $this->contratistaId,
            'supervisor_id' => $this->supervisorId,
            'fecha_suscripcion' => '2026-08-01',
            'objeto' => 'Prestación de servicios de apoyo a la gestión como tecnólogo en sistemas.',
            'valor_total' => 14250000,
            'valor_total_letras' => 'CATORCE MILLONES DOSCIENTOS CINCUENTA MIL PESOS M/CTE ($ 14.250.000)',
            'plazo_texto' => 'CINCO (5) MESES',
            'estado' => 'ACTIVO',
            'fecha_acta_inicio' => '2026-08-01',
            'fecha_terminacion' => '2026-12-31',
        ]);
        $this->contratoId = (int) self::$conexion->lastInsertId();

        // Dos cuotas ya cobrables: la segunda termina hoy para que la
        // N° 2 pueda crearse en cuanto se apruebe la N° 1.
        foreach ([[1, '2026-08-01', '2026-08-31'], [2, '2026-09-01', date('Y-m-d')]] as [$n, $desde, $hasta]) {
            self::$conexion->insert('cc_contrato_pagos', [
                'contrato_id' => $this->contratoId,
                'numero_cuota' => $n,
                'descripcion' => 'Mensualidad ' . $n,
                'tipo' => 'COMPLETA',
                'periodo_desde' => $desde,
                'periodo_hasta' => $hasta,
                'dias' => 30,
                'valor' => 2850000,
            ]);
        }

        foreach ([1, 2] as $orden) {
            self::$conexion->insert('cc_contrato_obligaciones', [
                'contrato_id' => $this->contratoId,
                'orden' => $orden,
                'grupo' => 'ESPECIFICA',
                'texto' => 'Obligación específica número ' . $orden . ' del contrato supervisado.',
            ]);
        }

        // Sesiones: contratista en el portal y supervisor en el panel,
        // sobre la misma sesión PHP (claves separadas).
        /** @var SesionContratista $sesionPortal */
        $sesionPortal = $this->contenedor->get(SesionContratista::class);
        $sesionPortal->abrir($this->contratistaId, $this->contratoId);

        /** @var Sesion $sesion */
        $sesion = $this->contenedor->get(Sesion::class);
        $sesion->autenticar(new Usuario(
            id: $this->usuarioSupervisorId,
            nombre: 'Supervisor de fase 3',
            correo: 'prueba-sup3@hospital.gov.co',
            hashPassword: '',
            rolId: 0,
            activo: true,
            intentosFallidos: 0,
            bloqueadoHasta: null,
            debeCambiarPassword: false,
        ));
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
     * Criterio de aceptación: al aprobar la N° 1 y crear y aprobar la
     * N° 2, los «pagos anteriores acumulados» de la N° 2 son el valor de
     * la N° 1 y el saldo cuadra. Y el paquete es un PDF de verdad.
     */
    public function testAprobarCongelaLosMontosYArmaElPaquete(): void
    {
        $primeraId = $this->enviarCuentaDelPortal();

        $respuesta = $this->post('/admin/cuentas-cobro/bandeja/' . $primeraId . '/aprobar', [
            'fecha_informe' => '2026-09-05',
            'observacion' => '',
        ]);
        self::assertSame('/admin/cuentas-cobro/bandeja/' . $primeraId, $respuesta->headers->get('Location'));

        $primera = self::$conexion->fetchAssociative('SELECT * FROM cc_cuentas WHERE id = ?', [$primeraId]);
        self::assertSame('APROBADA', $primera['estado']);
        self::assertSame(0, (int) explode('.', (string) $primera['pagos_anteriores'], 2)[0]);
        self::assertSame(11400000, (int) explode('.', (string) $primera['saldo_por_ejecutar'], 2)[0]);

        $raiz = dirname(__DIR__, 3) . '/storage/cuentas/';

        foreach (['pdf_informe_supervision', 'pdf_paquete'] as $columna) {
            self::assertNotNull($primera[$columna], "No se generó {$columna}.");
            $contenido = (string) file_get_contents($raiz . $primera[$columna]);
            self::assertStringStartsWith('%PDF', $contenido, "{$columna} no es un PDF.");
        }

        // El paquete trae las páginas de los tres documentos y la planilla.
        $paquete = (string) file_get_contents($raiz . $primera['pdf_paquete']);
        self::assertGreaterThanOrEqual(4, preg_match_all('#/Type /Page\b#', $paquete), 'El paquete quedó con muy pocas páginas.');

        // La firma del supervisor quedó como evidencia.
        $firma = self::$conexion->fetchAssociative(
            "SELECT * FROM cc_cuenta_firmas WHERE cuenta_id = ? AND firmante_rol = 'SUPERVISOR'",
            [$primeraId],
        );
        self::assertNotFalse($firma, 'No quedó la evidencia de firma del supervisor.');
        self::assertSame($this->usuarioSupervisorId, (int) $firma['usuario_id']);

        // La N° 2: sus pagos anteriores son el valor de la N° 1.
        $segundaId = $this->enviarCuentaDelPortal();
        $segunda = self::$conexion->fetchAssociative('SELECT * FROM cc_cuentas WHERE id = ?', [$segundaId]);
        self::assertSame(2, (int) $segunda['consecutivo']);

        $this->post('/admin/cuentas-cobro/bandeja/' . $segundaId . '/aprobar', [
            'fecha_informe' => '2026-10-05',
            'observacion' => '',
        ]);

        $segunda = self::$conexion->fetchAssociative('SELECT * FROM cc_cuentas WHERE id = ?', [$segundaId]);
        self::assertSame('APROBADA', $segunda['estado']);
        self::assertSame(2850000, (int) explode('.', (string) $segunda['pagos_anteriores'], 2)[0], 'Los pagos anteriores de la N° 2 no son el valor de la N° 1.');
        self::assertSame(8550000, (int) explode('.', (string) $segunda['saldo_por_ejecutar'], 2)[0]);
    }

    /**
     * Criterio de aceptación: devolver → corregir → reenviar, con las tres
     * transiciones en el historial y el radicado intacto.
     */
    public function testDevolverCorregirYReenviar(): void
    {
        $cuentaId = $this->enviarCuentaDelPortal();
        $radicado = self::$conexion->fetchOne('SELECT radicado FROM cc_cuentas WHERE id = ?', [$cuentaId]);

        $this->post('/admin/cuentas-cobro/bandeja/' . $cuentaId . '/devolver', [
            'observacion' => 'La descripción de la obligación 2 no corresponde al periodo. Corríjala.',
        ]);

        $cuenta = self::$conexion->fetchAssociative('SELECT * FROM cc_cuentas WHERE id = ?', [$cuentaId]);
        self::assertSame('DEVUELTA', $cuenta['estado']);
        self::assertStringContainsString('obligación 2', (string) $cuenta['observacion_supervisor']);

        // El contratista reenvía desde el portal (la cuenta devuelta es la editable).
        $this->postPortal('/cuentas/nueva/enviar', ['confirmo' => '1']);

        $cuenta = self::$conexion->fetchAssociative('SELECT * FROM cc_cuentas WHERE id = ?', [$cuentaId]);
        self::assertSame('ENVIADA', $cuenta['estado']);
        self::assertSame($radicado, $cuenta['radicado'], 'El radicado cambió al reenviar: es la misma cuenta.');

        $transiciones = self::$conexion->fetchAllAssociative(
            'SELECT estado_anterior, estado_nuevo FROM cc_cuenta_historial WHERE cuenta_id = ? ORDER BY id',
            [$cuentaId],
        );
        self::assertSame(
            [['BORRADOR', 'ENVIADA'], ['ENVIADA', 'DEVUELTA'], ['DEVUELTA', 'ENVIADA']],
            array_map(static fn (array $t): array => [(string) $t['estado_anterior'], (string) $t['estado_nuevo']], $transiciones),
        );
    }

    /**
     * Criterio de aceptación: el informe de supervisión lleva exactamente
     * las mismas obligaciones y descripciones que el de actividades, y
     * ningún campo del avance financiero queda en blanco.
     */
    public function testInformeDeSupervisionCoincideConElDeActividades(): void
    {
        $cuentaId = $this->enviarCuentaDelPortal();
        $generador = $this->generador();

        $actividades = $generador->vistaPrevia('INFORME_ACTIVIDADES', $cuentaId);
        $supervision = $generador->vistaPrevia('INFORME_SUPERVISION', $cuentaId);

        foreach (self::$conexion->fetchAllAssociative(
            'SELECT o.texto, a.descripcion_ejecucion FROM cc_cuenta_actividades a JOIN cc_contrato_obligaciones o ON o.id = a.obligacion_id WHERE a.cuenta_id = ?',
            [$cuentaId],
        ) as $fila) {
            self::assertStringContainsString((string) $fila['texto'], $actividades);
            self::assertStringContainsString((string) $fila['texto'], $supervision);
            self::assertStringContainsString((string) $fila['descripcion_ejecucion'], $actividades);
            self::assertStringContainsString((string) $fila['descripcion_ejecucion'], $supervision);
        }

        // Avance financiero: todos los conceptos con valor, ninguno en blanco.
        self::assertStringContainsString('$ 14.250.000', $supervision);   // valor inicial y total
        self::assertStringContainsString('N. A.', $supervision);          // adición
        self::assertStringContainsString('$ 0', $supervision);            // anticipo y pagos anteriores
        self::assertStringContainsString('$ 11.400.000', $supervision);   // saldo por ejecutar
        self::assertStringContainsString('$ 2.850.000', $supervision);    // pago autorizado

        // El periodo, idéntico en la certificación (aparece más de una vez).
        self::assertGreaterThanOrEqual(2, substr_count($supervision, 'del 1 al 31 de agosto de 2026'));
    }

    /** Marcar No o Parcial sin observación no aprueba (§7.2). */
    public function testNoAprobadaSinObservacionCuandoHayReparo(): void
    {
        $cuentaId = $this->enviarCuentaDelPortal();
        $obligacionId = (int) self::$conexion->fetchOne(
            'SELECT obligacion_id FROM cc_cuenta_actividades WHERE cuenta_id = ? LIMIT 1',
            [$cuentaId],
        );

        $this->post('/admin/cuentas-cobro/bandeja/' . $cuentaId . '/aprobar', [
            'fecha_informe' => '2026-09-05',
            'observacion' => '',
            'cumplimiento' => [(string) $obligacionId => 'PARCIAL'],
        ]);

        self::assertSame(
            'ENVIADA',
            self::$conexion->fetchOne('SELECT estado FROM cc_cuentas WHERE id = ?', [$cuentaId]),
            'Aprobó con un Parcial sin observación.',
        );
    }

    // ------------------------------------------------------------------
    // Ayudas
    // ------------------------------------------------------------------

    /** Lleva una cuenta del portal hasta ENVIADA y devuelve su id. */
    private function enviarCuentaDelPortal(): int
    {
        $this->postPortal('/cuentas/nueva/1', []);
        $cuentaId = (int) self::$conexion->fetchOne(
            "SELECT id FROM cc_cuentas WHERE contrato_id = ? AND estado = 'BORRADOR' ORDER BY id DESC LIMIT 1",
            [$this->contratoId],
        );

        foreach (self::$conexion->fetchFirstColumn('SELECT obligacion_id FROM cc_cuenta_actividades WHERE cuenta_id = ?', [$cuentaId]) as $oid) {
            self::$conexion->update('cc_cuenta_actividades', [
                'descripcion_ejecucion' => 'Actividades ejecutadas del periodo frente a la obligación ' . $oid . '.',
            ], ['cuenta_id' => $cuentaId, 'obligacion_id' => $oid]);
        }

        $this->postPortal('/cuentas/nueva/3', [
            'numero_planilla' => '555444333',
            'mes_salud' => '2026-07',
            'mes_pension' => '2026-07',
            'ibc' => '1750905',
            'aporte_salud' => '218900',
            'aporte_pension' => '280200',
            'aporte_arl' => '42700',
            'aporte_fsp' => '0',
        ], ['planilla' => $this->archivoSubido('planilla.png')]);

        $this->postPortal('/cuentas/nueva/enviar', ['confirmo' => '1']);

        self::assertSame('ENVIADA', self::$conexion->fetchOne('SELECT estado FROM cc_cuentas WHERE id = ?', [$cuentaId]));

        return $cuentaId;
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

    private function archivoSubido(string $nombre): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'cc');
        file_put_contents((string) $ruta, base64_decode(self::PNG_MINIMO, true));

        return new UploadedFile((string) $ruta, $nombre, 'image/png', null, true);
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

    /** @param array<string, mixed> $campos @param array<string, UploadedFile> $archivos */
    private function postPortal(string $ruta, array $campos, array $archivos = []): Response
    {
        /** @var SesionContratista $sesion */
        $sesion = $this->contenedor->get(SesionContratista::class);
        $campos['_token'] = $sesion->tokenCsrf();

        return $this->kernel->manejar(Request::create($ruta, 'POST', $campos, [], $archivos));
    }
}

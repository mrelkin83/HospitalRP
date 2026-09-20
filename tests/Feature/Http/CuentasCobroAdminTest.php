<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Core\Auth\Sesion;
use App\Core\Auth\Usuario;
use App\Core\Config\ConfigAplicacion;
use App\Core\Config\Env;
use App\Http\Admin\CuentasContratosController;
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
 * La administración de contratos del módulo de cuentas de cobro, contra los
 * criterios de aceptación de la fase 1:
 *
 * - el acta de inicio del 1407-2026 (2026-08-01) produce cinco periodos
 *   calendario agosto–diciembre y termina el 2026-12-31;
 * - el acta del 1414-2026 (2026-07-27) deja la cuota 1 del 27 al 31 de
 *   julio; y
 * - un cronograma que no suma el valor total NO deja activar, porque ese
 *   descuadre es exactamente el error que hoy se copia tres veces a mano.
 */
#[CoversClass(CuentasContratosController::class)]
final class CuentasCobroAdminTest extends TestCase
{
    private static Connection $conexion;
    private ContainerBuilder $contenedor;
    private Kernel $kernel;
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

        $usuarioId = $this->crearUsuarioConPermisos(['cuentas.administrar']);

        self::$conexion->insert('cc_contratistas', [
            'numero_documento' => '1199999901',
            'nombres' => 'Prueba',
            'apellidos' => 'Aceptación',
        ]);
        $this->contratistaId = (int) self::$conexion->lastInsertId();

        self::$conexion->insert('cc_supervisores', [
            'usuario_id' => $usuarioId,
            'nombre_completo' => 'SUPERVISOR DE ACEPTACIÓN',
            'cargo' => 'PROFESIONAL UNIVERSITARIO',
        ]);
        $this->supervisorId = (int) self::$conexion->lastInsertId();

        $this->autenticar($usuarioId);
    }

    protected function tearDown(): void
    {
        self::limpiar();
    }

    private static function limpiar(): void
    {
        // En orden inverso a las FK; las tablas cc_ solo las usa esta suite,
        // y un residuo en cc_supervisores rompería la limpieza de usuarios
        // de las demás suites. Todo esto corre contra la base DE PRUEBAS.
        foreach ([
            'cc_cuenta_firmas', 'cc_cuenta_historial', 'cc_cuenta_adjuntos',
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
     * Criterio de aceptación: acta del 1407-2026 con fecha 2026-08-01 →
     * cinco periodos calendario agosto–diciembre, terminación 2026-12-31.
     */
    public function testActaDeInicioCalendarioLlenaLosPeriodos(): void
    {
        $contratoId = $this->crearContrato('1407-2026', 14250000, array_fill(0, 5, ['Mensualidad', 'COMPLETA', null, 2850000]));

        $respuesta = $this->post('/admin/cuentas-cobro/contratos/' . $contratoId . '/acta', [
            'fecha_acta_inicio' => '2026-08-01',
        ]);

        self::assertSame(Response::HTTP_SEE_OTHER, $respuesta->getStatusCode());

        $pagos = self::$conexion->fetchAllAssociative(
            'SELECT periodo_desde, periodo_hasta, dias FROM cc_contrato_pagos WHERE contrato_id = ? ORDER BY numero_cuota',
            [$contratoId],
        );

        self::assertSame('2026-08-01', $pagos[0]['periodo_desde']);
        self::assertSame('2026-08-31', $pagos[0]['periodo_hasta']);
        self::assertSame('2026-12-01', $pagos[4]['periodo_desde']);
        self::assertSame('2026-12-31', $pagos[4]['periodo_hasta']);

        $contrato = self::$conexion->fetchAssociative('SELECT fecha_acta_inicio, fecha_terminacion FROM cc_contratos WHERE id = ?', [$contratoId]);
        self::assertSame('2026-08-01', $contrato['fecha_acta_inicio']);
        self::assertSame('2026-12-31', $contrato['fecha_terminacion']);
    }

    /**
     * Criterio de aceptación: acta del 1414-2026 con fecha 2026-07-27 →
     * cuota 1 del 27 al 31 de julio ($583.333) y cinco de $3.500.000.
     */
    public function testActaConCuotaParcialInicial(): void
    {
        $contratoId = $this->crearContrato('1414-2026', 18083333, [
            ['Días de julio de 2026', 'PARCIAL', '2026-07', 583333],
            ['Agosto de 2026', 'COMPLETA', '2026-08', 3500000],
            ['Septiembre de 2026', 'COMPLETA', '2026-09', 3500000],
            ['Octubre de 2026', 'COMPLETA', '2026-10', 3500000],
            ['Noviembre de 2026', 'COMPLETA', '2026-11', 3500000],
            ['Diciembre de 2026', 'COMPLETA', '2026-12', 3500000],
        ]);

        $this->post('/admin/cuentas-cobro/contratos/' . $contratoId . '/acta', [
            'fecha_acta_inicio' => '2026-07-27',
        ]);

        $primera = self::$conexion->fetchAssociative(
            'SELECT periodo_desde, periodo_hasta, dias FROM cc_contrato_pagos WHERE contrato_id = ? AND numero_cuota = 1',
            [$contratoId],
        );

        self::assertSame('2026-07-27', $primera['periodo_desde']);
        self::assertSame('2026-07-31', $primera['periodo_hasta']);
        self::assertSame(5, (int) $primera['dias']);

        $contrato = self::$conexion->fetchAssociative('SELECT fecha_terminacion FROM cc_contratos WHERE id = ?', [$contratoId]);
        self::assertSame('2026-12-31', $contrato['fecha_terminacion']);
    }

    /** Un cronograma descuadrado no se puede activar (§8.1). */
    public function testNoActivaSiElCronogramaNoSumaElTotal(): void
    {
        // Suma 14.000.000 frente a un total de 14.250.000: faltan 250.000.
        $contratoId = $this->crearContrato('1499-2026', 14250000, array_fill(0, 5, ['Mensualidad', 'COMPLETA', null, 2800000]));
        $this->agregarCdp($contratoId);

        $this->post('/admin/cuentas-cobro/contratos/' . $contratoId . '/activar', []);

        $estado = self::$conexion->fetchOne('SELECT estado FROM cc_contratos WHERE id = ?', [$contratoId]);
        self::assertSame('EN_REVISION', $estado, 'Un contrato descuadrado quedó activo.');
    }

    /** Con supervisor, CDP y cronograma cuadrado, activa y deja auditoría. */
    public function testActivarCambiaElEstadoYDejaAuditoria(): void
    {
        $contratoId = $this->crearContrato('1500-2026', 14250000, array_fill(0, 5, ['Mensualidad', 'COMPLETA', null, 2850000]));
        $this->agregarCdp($contratoId);

        $this->post('/admin/cuentas-cobro/contratos/' . $contratoId . '/activar', []);

        self::assertSame('ACTIVO', self::$conexion->fetchOne('SELECT estado FROM cc_contratos WHERE id = ?', [$contratoId]));

        $rastro = self::$conexion->fetchOne(
            "SELECT COUNT(*) FROM auditoria WHERE entidad = 'cc_contrato' AND entidad_id = ? AND accion = 'cambio_estado'",
            [$contratoId],
        );
        self::assertSame(1, (int) $rastro, 'Activar un contrato no dejó rastro en la auditoría.');
    }

    /** Sin CDP no hay activación: el pago saldría sin respaldo presupuestal. */
    public function testNoActivaSinCdp(): void
    {
        $contratoId = $this->crearContrato('1501-2026', 14250000, array_fill(0, 5, ['Mensualidad', 'COMPLETA', null, 2850000]));

        $this->post('/admin/cuentas-cobro/contratos/' . $contratoId . '/activar', []);

        self::assertSame('EN_REVISION', self::$conexion->fetchOne('SELECT estado FROM cc_contratos WHERE id = ?', [$contratoId]));
    }

    // ------------------------------------------------------------------
    // Ayudas
    // ------------------------------------------------------------------

    /** @param list<array{string, string, ?string, int}> $cuotas [descripcion, tipo, mes, valor] */
    private function crearContrato(string $numero, int $valorTotal, array $cuotas): int
    {
        self::$conexion->insert('cc_contratos', [
            'numero' => $numero,
            'contratista_id' => $this->contratistaId,
            'supervisor_id' => $this->supervisorId,
            'fecha_suscripcion' => '2026-08-01',
            'objeto' => 'Contrato de prueba de aceptación de la fase 1.',
            'valor_total' => $valorTotal,
            'valor_total_letras' => 'VALOR EN LETRAS DE PRUEBA',
            'plazo_texto' => 'CINCO (5) MESES',
        ]);
        $contratoId = (int) self::$conexion->lastInsertId();

        foreach (array_values($cuotas) as $i => [$descripcion, $tipo, $mes, $valor]) {
            self::$conexion->insert('cc_contrato_pagos', [
                'contrato_id' => $contratoId,
                'numero_cuota' => $i + 1,
                'descripcion' => $descripcion,
                'tipo' => $tipo,
                'mes' => $mes,
                'valor' => $valor,
            ]);
        }

        return $contratoId;
    }

    private function agregarCdp(int $contratoId): void
    {
        self::$conexion->insert('cc_contrato_imputaciones', [
            'contrato_id' => $contratoId,
            'tipo' => 'CDP',
            'numero' => '1611',
            'fecha' => '2026-07-24',
            'codigo_rubro' => '2.1.2.02.02.008-83118',
            'nombre_rubro' => 'SERVICIOS DE GESTIÓN Y ADMINISTRACIÓN EMPRESARIAL (OFICINA CENTRAL)',
            'valor' => 14250000,
        ]);
    }

    /** @param array<string, string> $campos */
    private function post(string $ruta, array $campos): Response
    {
        /** @var Sesion $sesion */
        $sesion = $this->contenedor->get(Sesion::class);
        $sesion->iniciar();

        $campos['_token'] = $sesion->tokenCsrf();

        return $this->kernel->manejar(Request::create($ruta, 'POST', $campos));
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

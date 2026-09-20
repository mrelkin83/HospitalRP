<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Core\Config\ConfigAplicacion;
use App\Core\Config\Env;
use App\Http\Kernel;
use App\Http\Web\CuentasNuevaController;
use App\Modules\CuentasCobro\Repository\CuentaRepository;
use App\Modules\CuentasCobro\Service\OtpService;
use App\Modules\CuentasCobro\Service\SesionContratista;
use App\Ports\NotificadorCorreo;
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
 * El portal del contratista de punta a punta (fase 2 del módulo): crear el
 * borrador, autoguardar, reportar la planilla, enviar con firma y radicado,
 * y descargar con token de un solo uso.
 *
 * También el OTP: el código sirve una vez, y el quinto reenvío en una hora
 * es el último.
 */
#[CoversClass(CuentasNuevaController::class)]
final class CuentasPortalTest extends TestCase
{
    private const string PNG_MINIMO = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private static Connection $conexion;
    private ContainerBuilder $contenedor;
    private Kernel $kernel;
    private int $contratistaId;
    private int $contratoId;

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

        // La firma del contratista, como archivo real del almacén privado.
        $dirFirmas = $raiz . '/storage/cuentas/firmas';

        if (!is_dir($dirFirmas)) {
            mkdir($dirFirmas, 0755, true);
        }

        file_put_contents($dirFirmas . '/prueba-firma.png', base64_decode(self::PNG_MINIMO, true));

        self::$conexion->insert('roles', [
            'nombre' => 'Rol portal', 'slug' => 'prueba-' . bin2hex(random_bytes(6)), 'permisos' => '[]',
        ]);
        self::$conexion->insert('usuarios', [
            'nombre' => 'Supervisor portal',
            'correo' => 'prueba-sup@hospital.gov.co',
            'hash_password' => 'x',
            'rol_id' => (int) self::$conexion->lastInsertId(),
        ]);
        $usuarioId = (int) self::$conexion->lastInsertId();

        self::$conexion->insert('cc_contratistas', [
            'numero_documento' => '1188888801',
            'lugar_expedicion' => 'Valledupar',
            'nombres' => 'Prueba',
            'apellidos' => 'Portal',
            'celular' => '3000000001',
            'correo' => 'contratista@ejemplo.test',
            'banco' => 'Banco de Prueba',
            'tipo_cuenta' => 'AHORROS',
            'numero_cuenta' => '123456789',
            'clase_riesgo_arl' => 3,
            'firma_archivo' => 'firmas/prueba-firma.png',
            'cedula_archivo' => 'firmas/prueba-firma.png',
            'rut_archivo' => 'firmas/prueba-firma.png',
            'datos_completos' => 1,
            'acepto_tratamiento_datos_en' => date('Y-m-d H:i:s'),
        ]);
        $this->contratistaId = (int) self::$conexion->lastInsertId();

        self::$conexion->insert('cc_supervisores', [
            'usuario_id' => $usuarioId,
            'nombre_completo' => 'SUPERVISOR PORTAL',
            'cargo' => 'PROFESIONAL UNIVERSITARIO',
        ]);
        $supervisorId = (int) self::$conexion->lastInsertId();

        self::$conexion->insert('cc_contratos', [
            'numero' => '1407-2026',
            'contratista_id' => $this->contratistaId,
            'supervisor_id' => $supervisorId,
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

        // Una cuota ya vencida (cobrable) y una futura.
        foreach ([[1, '2026-08-01', '2026-08-31'], [2, '2026-09-01', '2026-09-30']] as [$n, $desde, $hasta]) {
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

        foreach ([1, 2, 3] as $orden) {
            self::$conexion->insert('cc_contrato_obligaciones', [
                'contrato_id' => $this->contratoId,
                'orden' => $orden,
                'grupo' => 'ESPECIFICA',
                'texto' => 'Obligación específica número ' . $orden . ' del contrato de prueba.',
            ]);
        }

        self::$conexion->insert('cc_contrato_imputaciones', [
            'contrato_id' => $this->contratoId,
            'tipo' => 'CDP',
            'numero' => '1611',
            'fecha' => '2026-07-24',
            'codigo_rubro' => '2.1.2.02.02.008-83118',
            'nombre_rubro' => 'SERVICIOS DE GESTIÓN',
            'valor' => 14250000,
        ]);

        /** @var SesionContratista $sesion */
        $sesion = $this->contenedor->get(SesionContratista::class);
        $sesion->abrir($this->contratistaId, $this->contratoId);
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
     * El camino feliz completo, del borrador al radicado: es el flujo que
     * el contratista real hará cada mes.
     */
    public function testFlujoCompletoHastaElEnvio(): void
    {
        // P2: confirmar datos crea el borrador con sus actividades.
        $respuesta = $this->post('/cuentas/nueva/1', []);
        self::assertSame('/cuentas/nueva/2', $respuesta->headers->get('Location'));

        $cuenta = self::$conexion->fetchAssociative('SELECT * FROM cc_cuentas WHERE contrato_id = ?', [$this->contratoId]);
        self::assertNotFalse($cuenta);
        self::assertSame(1, (int) $cuenta['consecutivo']);
        self::assertSame('BORRADOR', $cuenta['estado']);
        self::assertSame(3, (int) self::$conexion->fetchOne('SELECT COUNT(*) FROM cc_cuenta_actividades WHERE cuenta_id = ?', [$cuenta['id']]));

        // Autoguardado: una obligación se guarda sin enviar el formulario.
        $obligacionId = (int) self::$conexion->fetchOne('SELECT obligacion_id FROM cc_cuenta_actividades WHERE cuenta_id = ? LIMIT 1', [$cuenta['id']]);
        $autoguardado = $this->postJson('/cuentas/nueva/autoguardar', [
            'obligacion_id' => $obligacionId,
            'texto' => 'Texto autoguardado antes de cerrar el navegador.',
        ]);
        self::assertSame(Response::HTTP_OK, $autoguardado->getStatusCode());
        self::assertSame(
            'Texto autoguardado antes de cerrar el navegador.',
            self::$conexion->fetchOne('SELECT descripcion_ejecucion FROM cc_cuenta_actividades WHERE cuenta_id = ? AND obligacion_id = ?', [$cuenta['id'], $obligacionId]),
        );

        // P3: las tres descripciones completas.
        $actividades = [];

        foreach (self::$conexion->fetchFirstColumn('SELECT obligacion_id FROM cc_cuenta_actividades WHERE cuenta_id = ?', [$cuenta['id']]) as $oid) {
            $actividades[(int) $oid] = 'Durante el periodo realicé las labores de la obligación ' . $oid . ' conforme al contrato.';
        }

        $respuesta = $this->post('/cuentas/nueva/2', ['actividad' => $actividades]);
        self::assertSame('/cuentas/nueva/3', $respuesta->headers->get('Location'));

        // P4: la planilla con los valores sugeridos y su archivo.
        $respuesta = $this->post('/cuentas/nueva/3', [
            'numero_planilla' => '987654321',
            'mes_salud' => '2026-07',
            'mes_pension' => '2026-07',
            'ibc' => '1750905',
            'aporte_salud' => '218900',
            'aporte_pension' => '280200',
            'aporte_arl' => '42700',
            'aporte_fsp' => '0',
        ], ['planilla' => $this->archivoSubido('planilla.png')]);
        self::assertSame('/cuentas/nueva/4', $respuesta->headers->get('Location'));

        // P5: enviar con la confirmación marcada.
        $respuesta = $this->post('/cuentas/nueva/enviar', ['confirmo' => '1']);
        self::assertSame('/cuentas/enviada/' . $cuenta['id'], $respuesta->headers->get('Location'));

        $enviada = self::$conexion->fetchAssociative('SELECT * FROM cc_cuentas WHERE id = ?', [$cuenta['id']]);
        self::assertSame('ENVIADA', $enviada['estado']);
        self::assertMatchesRegularExpression('/^CC-\d{4}-\d{6}$/', (string) $enviada['radicado']);

        // Los dos PDF existen y son PDF de verdad.
        $raiz = dirname(__DIR__, 3) . '/storage/cuentas/';

        foreach (['pdf_cuenta_cobro', 'pdf_informe_actividades'] as $columna) {
            self::assertNotNull($enviada[$columna], "No se generó {$columna}.");
            $contenido = (string) file_get_contents($raiz . $enviada[$columna]);
            self::assertStringStartsWith('%PDF', $contenido, "{$columna} no es un PDF.");
        }

        // Evidencia de firma electrónica simple: una fila por documento.
        $firmas = self::$conexion->fetchAllAssociative('SELECT * FROM cc_cuenta_firmas WHERE cuenta_id = ?', [$cuenta['id']]);
        self::assertCount(2, $firmas);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $firmas[0]['hash_pdf']);
        self::assertSame($this->contratistaId, (int) $firmas[0]['contratista_id']);

        // El historial registra la transición.
        self::assertSame(
            1,
            (int) self::$conexion->fetchOne(
                "SELECT COUNT(*) FROM cc_cuenta_historial WHERE cuenta_id = ? AND estado_anterior = 'BORRADOR' AND estado_nuevo = 'ENVIADA'",
                [$cuenta['id']],
            ),
        );

        // Descarga autenticada.
        $pdf = $this->kernel->manejar(Request::create('/cuentas/pdf/' . $cuenta['id'] . '/cuenta'));
        self::assertSame(Response::HTTP_OK, $pdf->getStatusCode());
        self::assertSame('application/pdf', $pdf->headers->get('Content-Type'));
    }

    /** El enlace externo sirve UNA vez y a la segunda ya no existe (§9). */
    public function testTokenDeDescargaEsDeUnSoloUso(): void
    {
        $this->post('/cuentas/nueva/1', []);
        $cuentaId = (int) self::$conexion->fetchOne('SELECT id FROM cc_cuentas WHERE contrato_id = ?', [$this->contratoId]);
        $this->completarYEnviar($cuentaId);

        $repositorio = new CuentaRepository(self::$conexion);
        $token = $repositorio->crearTokenDescarga($cuentaId, 'CUENTA_COBRO');

        $primera = $this->kernel->manejar(Request::create('/cuentas/descarga/' . $token));
        self::assertSame(Response::HTTP_OK, $primera->getStatusCode());

        $segunda = $this->kernel->manejar(Request::create('/cuentas/descarga/' . $token));
        self::assertSame(Response::HTTP_GONE, $segunda->getStatusCode());
    }

    /** Dejar obligaciones vacías exige confirmarlo a conciencia (§7.1 P3). */
    public function testObligacionesVaciasPidenConfirmacion(): void
    {
        $this->post('/cuentas/nueva/1', []);

        $respuesta = $this->post('/cuentas/nueva/2', ['actividad' => []]);

        self::assertSame('/cuentas/nueva/2', $respuesta->headers->get('Location'), 'Con vacías y sin confirmar debía devolverse al paso 2.');
    }

    /** El OTP sirve una sola vez y el reenvío tiene tope por hora (§9). */
    public function testOtpDeUnSoloUsoYConTope(): void
    {
        $capturado = new class implements NotificadorCorreo {
            public string $cuerpo = '';

            public function enviar(string $destinatario, string $asunto, string $textoPlano): void
            {
                $this->cuerpo = $textoPlano;
            }
        };

        $otp = new OtpService(self::$conexion, $capturado);
        $contratista = self::$conexion->fetchAssociative('SELECT * FROM cc_contratistas WHERE id = ?', [$this->contratistaId]);
        self::assertNotFalse($contratista);

        $resultado = $otp->enviar($contratista, '127.0.0.1');
        self::assertTrue($resultado['ok']);
        self::assertSame(1, preg_match('/(\d{6})/', $capturado->cuerpo, $m), 'El correo no traía el código.');

        self::assertTrue($otp->verificar($this->contratistaId, $m[1]));
        self::assertFalse($otp->verificar($this->contratistaId, $m[1]), 'El código sirvió dos veces.');

        // Tope: ya se envió 1; los siguientes 4 pasan y el sexto no.
        for ($i = 0; $i < 4; $i++) {
            self::assertTrue($otp->enviar($contratista, '127.0.0.1')['ok']);
        }

        self::assertFalse($otp->enviar($contratista, '127.0.0.1')['ok'], 'El sexto código de la hora debía rechazarse.');
    }

    // ------------------------------------------------------------------
    // Ayudas
    // ------------------------------------------------------------------

    private function completarYEnviar(int $cuentaId): void
    {
        foreach (self::$conexion->fetchFirstColumn('SELECT obligacion_id FROM cc_cuenta_actividades WHERE cuenta_id = ?', [$cuentaId]) as $oid) {
            self::$conexion->update('cc_cuenta_actividades', [
                'descripcion_ejecucion' => 'Actividades del periodo frente a la obligación ' . $oid . '.',
            ], ['cuenta_id' => $cuentaId, 'obligacion_id' => $oid]);
        }

        $this->post('/cuentas/nueva/3', [
            'numero_planilla' => '987654321',
            'mes_salud' => '2026-07',
            'mes_pension' => '2026-07',
            'ibc' => '1750905',
            'aporte_salud' => '218900',
            'aporte_pension' => '280200',
            'aporte_arl' => '42700',
            'aporte_fsp' => '0',
        ], ['planilla' => $this->archivoSubido('planilla.png')]);

        $this->post('/cuentas/nueva/enviar', ['confirmo' => '1']);
    }

    private function archivoSubido(string $nombre): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'cc');
        file_put_contents((string) $ruta, base64_decode(self::PNG_MINIMO, true));

        return new UploadedFile((string) $ruta, $nombre, 'image/png', null, true);
    }

    /** @param array<string, mixed> $campos @param array<string, UploadedFile> $archivos */
    private function post(string $ruta, array $campos, array $archivos = []): Response
    {
        /** @var SesionContratista $sesion */
        $sesion = $this->contenedor->get(SesionContratista::class);
        $campos['_token'] = $sesion->tokenCsrf();

        return $this->kernel->manejar(Request::create($ruta, 'POST', $campos, [], $archivos));
    }

    /** @param array<string, mixed> $datos */
    private function postJson(string $ruta, array $datos): Response
    {
        /** @var SesionContratista $sesion */
        $sesion = $this->contenedor->get(SesionContratista::class);

        return $this->kernel->manejar(Request::create(
            $ruta,
            'POST',
            [],
            [],
            [],
            ['HTTP_X-Token-CSRF' => $sesion->tokenCsrf(), 'CONTENT_TYPE' => 'application/json'],
            (string) json_encode($datos),
        ));
    }
}

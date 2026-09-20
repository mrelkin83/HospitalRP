<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Core\Auth\Sesion;
use App\Core\Auth\Usuario;
use App\Core\Config\ConfigAplicacion;
use App\Core\Config\Env;
use App\Http\Admin\CuentasMinutasController;
use App\Http\Kernel;
use Doctrine\DBAL\Connection;
use Dompdf\Dompdf;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouteCollection;
use Tests\Support\BaseDatosDePruebas;

/**
 * La pantalla A2 (fase 5) SIN proveedor de IA, que es el criterio de
 * aceptación del respaldo: la minuta se convierte a imágenes, la revisión
 * a dos columnas funciona con el formulario vacío y el contrato se crea a
 * mano desde ahí. En pruebas no hay claves de IA, así que este es
 * exactamente el camino que se ejercita.
 *
 * La minuta es SINTÉTICA (generada con Dompdf): las reales tienen datos
 * personales y no entran ni al repositorio ni a la base de pruebas.
 */
#[CoversClass(CuentasMinutasController::class)]
final class CuentasMinutasTest extends TestCase
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
        if (!is_file('C:/laragon/bin/poppler/poppler-24.08.0/Library/bin/pdftoppm.exe')
            && getenv('PDF_PDFTOPPM') === false) {
            self::markTestSkipped('pdftoppm (Poppler) no está instalado en esta máquina.');
        }

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
            'nombre' => 'Rol minutas',
            'slug' => 'prueba-' . bin2hex(random_bytes(6)),
            'permisos' => json_encode(['cuentas.administrar'], JSON_THROW_ON_ERROR),
        ]);
        self::$conexion->insert('usuarios', [
            'nombre' => 'Admin de minutas',
            'correo' => 'prueba-minutas@hospital.gov.co',
            'hash_password' => 'x',
            'rol_id' => (int) self::$conexion->lastInsertId(),
        ]);
        $this->autenticar((int) self::$conexion->lastInsertId());
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
     * Sin IA: la minuta se convierte, queda LISTA con la alerta de carga
     * manual, la revisión responde y el contrato sale del formulario.
     */
    public function testFlujoManualCompleto(): void
    {
        // 1. Subir la minuta sintética.
        $respuesta = $this->post('/admin/cuentas-cobro/minutas', [], ['minuta' => $this->minutaSintetica()]);

        $extraccion = self::$conexion->fetchAssociative('SELECT * FROM cc_extracciones ORDER BY id DESC LIMIT 1');
        self::assertNotFalse($extraccion, 'No se creó la extracción.');
        $id = (int) $extraccion['id'];
        self::assertSame('/admin/cuentas-cobro/minutas/' . $id, $respuesta->headers->get('Location'));
        self::assertSame('LISTA', $extraccion['estado']);
        self::assertStringContainsString('no está configurado', implode(' ', self::alertasDe($extraccion)));

        // 2. Las imágenes del visor existen y se sirven.
        $imagen = $this->kernel->manejar(Request::create('/admin/cuentas-cobro/minutas/' . $id . '/imagen/1'));
        self::assertSame(Response::HTTP_OK, $imagen->getStatusCode());
        self::assertSame('image/png', $imagen->headers->get('Content-Type'));
        self::assertStringStartsWith("\x89PNG", (string) $imagen->getContent());

        // 3. La pantalla de revisión responde con el formulario.
        $revision = $this->kernel->manejar(Request::create('/admin/cuentas-cobro/minutas/' . $id));
        self::assertSame(Response::HTTP_OK, $revision->getStatusCode());
        self::assertStringContainsString('Crear contrato en revisión', (string) $revision->getContent());

        // 4. Aplicar: el contrato nace EN_REVISION con todo lo del formulario.
        $respuesta = $this->post('/admin/cuentas-cobro/minutas/' . $id . '/aplicar', [
            'contratista_documento' => '1155555501',
            'contratista_nombres' => 'Prueba',
            'contratista_apellidos' => 'Minutas',
            'numero' => '1999-2026',
            'fecha_suscripcion' => '2026-08-01',
            'valor_total' => '14250000',
            'objeto' => 'Prestación de servicios de apoyo a la gestión, creada desde la extracción.',
            'tipo_contrato' => 'PRESTACIÓN DE SERVICIOS',
            'plazo_texto' => 'CINCO (5) MESES',
            'plazo_meses' => '5',
            'cuota_descripcion' => ['Agosto de 2026', 'Septiembre de 2026', 'Octubre de 2026', 'Noviembre de 2026', 'Diciembre de 2026'],
            'cuota_tipo' => ['COMPLETA', 'COMPLETA', 'COMPLETA', 'COMPLETA', 'COMPLETA'],
            'cuota_mes' => ['2026-08', '2026-09', '2026-10', '2026-11', '2026-12'],
            'cuota_valor' => ['2850000', '2850000', '2850000', '2850000', '2850000'],
            'cdp_numero' => ['1611'],
            'cdp_fecha' => ['2026-07-24'],
            'cdp_codigo_rubro' => ['2.1.2.02.02.008-83118'],
            'cdp_nombre_rubro' => ['SERVICIOS DE GESTIÓN'],
            'cdp_valor' => ['14250000'],
            'obligaciones' => "Primera obligación específica del contrato sintético.\nSegunda obligación específica del contrato sintético.",
            'obligaciones_sgic' => '',
        ]);

        $contrato = self::$conexion->fetchAssociative("SELECT * FROM cc_contratos WHERE numero = '1999-2026'");
        self::assertNotFalse($contrato, 'No se creó el contrato desde la extracción.');
        self::assertSame('/admin/cuentas-cobro/contratos/' . $contrato['id'], $respuesta->headers->get('Location'));
        self::assertSame('EN_REVISION', $contrato['estado']);
        self::assertSame(5, (int) self::$conexion->fetchOne('SELECT COUNT(*) FROM cc_contrato_pagos WHERE contrato_id = ?', [$contrato['id']]));
        self::assertSame(2, (int) self::$conexion->fetchOne('SELECT COUNT(*) FROM cc_contrato_obligaciones WHERE contrato_id = ?', [$contrato['id']]));
        self::assertSame(1, (int) self::$conexion->fetchOne("SELECT COUNT(*) FROM cc_contrato_imputaciones WHERE contrato_id = ? AND tipo = 'CDP'", [$contrato['id']]));

        // El contratista quedó creado por cédula.
        self::assertNotFalse(self::$conexion->fetchOne("SELECT id FROM cc_contratistas WHERE numero_documento = '1155555501'"));

        // Y la extracción quedó APLICADA y amarrada al contrato.
        $extraccion = self::$conexion->fetchAssociative('SELECT * FROM cc_extracciones WHERE id = ?', [$id]);
        self::assertSame('APLICADA', $extraccion['estado']);
        self::assertSame((int) $contrato['id'], (int) $extraccion['contrato_id']);

        // Aplicarla dos veces no crea dos contratos.
        $repetida = $this->post('/admin/cuentas-cobro/minutas/' . $id . '/aplicar', ['contratista_documento' => '1155555501']);
        self::assertSame('/admin/cuentas-cobro/minutas/' . $id, $repetida->headers->get('Location'));
    }

    // ------------------------------------------------------------------
    // Ayudas
    // ------------------------------------------------------------------

    /** Una minuta sintética de dos páginas, generada con Dompdf. */
    private function minutaSintetica(): UploadedFile
    {
        $dompdf = new Dompdf();
        $dompdf->loadHtml(
            '<h1>CONTRATO 1999-2026</h1><p>Minuta sintética de prueba, sin datos personales.</p>'
            . '<div style="page-break-before: always;"></div><p>CLAUSULA 3. FORMA DE PAGO: cinco pagos.</p>',
            'UTF-8',
        );
        $dompdf->setPaper('letter');
        $dompdf->render();

        $ruta = tempnam(sys_get_temp_dir(), 'minuta') . '.pdf';
        file_put_contents($ruta, (string) $dompdf->output());

        return new UploadedFile($ruta, 'minuta-sintetica.pdf', 'application/pdf', null, true);
    }

    /** @param array<string, mixed> $extraccion @return list<string> */
    private static function alertasDe(array $extraccion): array
    {
        $alertas = json_decode((string) ($extraccion['alertas_json'] ?? '[]'), true);

        return is_array($alertas) ? array_map(strval(...), $alertas) : [];
    }

    private function autenticar(int $usuarioId): void
    {
        /** @var Sesion $sesion */
        $sesion = $this->contenedor->get(Sesion::class);
        $sesion->iniciar();
        $sesion->autenticar(new Usuario(
            id: $usuarioId,
            nombre: 'Admin de minutas',
            correo: 'prueba-minutas@hospital.gov.co',
            hashPassword: '',
            rolId: 0,
            activo: true,
            intentosFallidos: 0,
            bloqueadoHasta: null,
            debeCambiarPassword: false,
        ));
    }

    /** @param array<string, mixed> $campos @param array<string, UploadedFile> $archivos */
    private function post(string $ruta, array $campos, array $archivos = []): Response
    {
        /** @var Sesion $sesion */
        $sesion = $this->contenedor->get(Sesion::class);
        $sesion->iniciar();
        $campos['_token'] = $sesion->tokenCsrf();

        return $this->kernel->manejar(Request::create($ruta, 'POST', $campos, [], $archivos));
    }
}

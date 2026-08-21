<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Cumplimiento;

use App\Core\Audit\AuditoriaService;
use App\Core\Config\ConfigDinamica;
use App\Modules\Cumplimiento\Repository\PoliticaRepository;
use App\Modules\Tramites\Command\SembrarTramitesCommand;
use App\Modules\Tramites\Repository\TipoTramiteRepository;
use App\Modules\Tramites\Repository\TramiteRepository;
use App\Modules\Tramites\Request\SolicitudTramite;
use App\Modules\Tramites\Service\CalculadoraDiasHabiles;
use App\Modules\Tramites\Service\GeneradorRadicado;
use App\Modules\Tramites\Service\RadicacionService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Support\BaseDatosDePruebas;
use Tests\Support\NotificadorCorreoEspia;

/**
 * Criterios de la Fase 6 contra MySQL real.
 */
#[CoversClass(PoliticaRepository::class)]
final class CumplimientoTest extends TestCase
{
    private static Connection $conexion;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        foreach ([
            'tramite_adjuntos', 'tramite_seguimiento', 'tramite_valores', 'autorizaciones_datos',
            'tramites', 'tramite_campos', 'tramite_estados', 'tipos_tramite',
            'radicado_secuencias', 'politicas',
        ] as $tabla) {
            self::$conexion->executeStatement("DELETE FROM {$tabla}");
        }

        $tester = new CommandTester(new SembrarTramitesCommand(self::$conexion, new AuditoriaService(self::$conexion)));
        $tester->execute([]);
    }

    public function testPublicarUnaVersionNuevaCierraLaAnterior(): void
    {
        $politicas = new PoliticaRepository(self::$conexion);

        $activaVieja = $politicas->activaPorTipo('tratamiento_datos');
        self::assertNotNull($activaVieja);

        $nuevaId = $politicas->publicarVersion([
            'tipo' => 'tratamiento_datos',
            'version' => '2.0',
            'titulo' => 'Política de tratamiento de datos personales',
            'contenido' => '<p>Texto definitivo adoptado por la oficina jurídica.</p>',
            'creado_por' => null,
        ]);

        $activaNueva = $politicas->activaPorTipo('tratamiento_datos');
        self::assertNotNull($activaNueva);
        self::assertSame($nuevaId, (int) $activaNueva['id']);
        self::assertSame('2.0', (string) $activaNueva['version']);

        // La anterior quedó cerrada, no borrada: la evidencia vieja sigue
        // apuntando a la versión que se aceptó en su momento.
        $vieja = self::$conexion->fetchAssociative('SELECT * FROM politicas WHERE id = ?', [(int) $activaVieja['id']]);
        self::assertNotFalse($vieja);
        self::assertSame(0, (int) $vieja['activa']);
        self::assertNotNull($vieja['vigente_hasta']);
    }

    public function testLaEvidenciaApuntaALaVersionActivaEnElMomentoDeAceptar(): void
    {
        $politicas = new PoliticaRepository(self::$conexion);
        $tipos = new TipoTramiteRepository(self::$conexion);

        $versionVieja = (int) $politicas->activaPorTipo('tratamiento_datos')['id'];

        // Primer trámite: acepta la versión vieja.
        $primero = $this->radicar($tipos, 'Primera solicitud');

        // Cambia la política y radica un segundo trámite.
        $versionNueva = $politicas->publicarVersion([
            'tipo' => 'tratamiento_datos', 'version' => '2.0',
            'titulo' => 'Política nueva', 'contenido' => '<p>Nueva.</p>', 'creado_por' => null,
        ]);
        $segundo = $this->radicar($tipos, 'Segunda solicitud');

        $evidencias = self::$conexion->fetchAllKeyValue(
            "SELECT origen_id, politica_id FROM autorizaciones_datos WHERE origen = 'tramite'",
        );

        self::assertSame($versionVieja, (int) $evidencias[$primero['tramite_id']]);
        self::assertSame($versionNueva, (int) $evidencias[$segundo['tramite_id']]);
    }

    public function testLaRevocatoriaMarcaLasAutorizacionesYNotificaAlResponsable(): void
    {
        self::$conexion->executeStatement(
            "INSERT IGNORE INTO configuracion (clave, valor, tipo, grupo, etiqueta) VALUES
             ('contacto.linea_etica_correo', 'oficialcumplimiento@hrplopez.gov.co', 'texto', 'contacto', 'Línea ética')",
        );

        $tipos = new TipoTramiteRepository(self::$conexion);

        // El ciudadano ya tiene una autorización previa (un PQRSDF).
        $previo = $this->radicar($tipos, 'PQRSDF previo');

        // Radica la revocatoria de habeas data.
        $habeas = $tipos->activoPorSlug('habeas-data');
        self::assertNotNull($habeas);
        $campoTipo = $habeas->campos[0]['id'];

        $correo = new NotificadorCorreoEspia();
        $resultado = $this->servicio($correo)->radicar($habeas, new SolicitudTramite(
            anonimo: false,
            nombre: 'María Pérez',
            tipoDocumento: 'CC',
            numeroDocumento: '1065123456',
            correo: 'maria@example.com',
            telefono: null,
            direccion: null,
            municipio: null,
            medioRespuesta: 'correo',
            asunto: 'Revoco mi autorización',
            descripcion: 'Deseo revocar la autorización de tratamiento de mis datos.',
            valores: [$campoTipo => 'Revocar mi autorización'],
            autorizaDatos: true,
        ), [], '203.0.113.7', 'PHPUnit');

        // La autorización previa quedó revocada; la de la revocatoria no.
        $previa = self::$conexion->fetchAssociative(
            "SELECT revocado_en FROM autorizaciones_datos WHERE origen_id = ? AND origen = 'tramite'",
            [$previo['tramite_id']],
        );
        self::assertNotFalse($previa);
        self::assertNotNull($previa['revocado_en']);

        $propia = self::$conexion->fetchAssociative(
            "SELECT revocado_en FROM autorizaciones_datos WHERE origen_id = ? AND origen = 'tramite'",
            [$resultado['tramite_id']],
        );
        self::assertNotFalse($propia);
        self::assertNull($propia['revocado_en']);

        // Queda auditada y el responsable fue notificado.
        self::assertSame(1, (int) self::$conexion->fetchOne(
            "SELECT COUNT(*) FROM auditoria WHERE accion = 'revocar_autorizacion' AND entidad_id = ?",
            [$resultado['tramite_id']],
        ));

        $destinatarios = array_column($correo->enviados, 'destinatario');
        self::assertContains('oficialcumplimiento@hrplopez.gov.co', $destinatarios);
    }

    // --- soporte ---

    /**
     * @return array{radicado: string, vence_en: string, tramite_id: int}
     */
    private function radicar(TipoTramiteRepository $tipos, string $asunto): array
    {
        $pqrsdf = $tipos->activoPorSlug('pqrsdf');
        self::assertNotNull($pqrsdf);

        return $this->servicio(new NotificadorCorreoEspia())->radicar($pqrsdf, new SolicitudTramite(
            anonimo: false,
            nombre: 'María Pérez',
            tipoDocumento: 'CC',
            numeroDocumento: '1065123456',
            correo: 'maria@example.com',
            telefono: null,
            direccion: null,
            municipio: null,
            medioRespuesta: 'correo',
            asunto: $asunto,
            descripcion: 'Descripción de la solicitud.',
            valores: [$pqrsdf->campos[0]['id'] => 'Petición'],
            autorizaDatos: true,
        ), [], null, null);
    }

    private function servicio(NotificadorCorreoEspia $correo): RadicacionService
    {
        return new RadicacionService(
            self::$conexion,
            new TramiteRepository(self::$conexion),
            new GeneradorRadicado(self::$conexion),
            new CalculadoraDiasHabiles(self::$conexion),
            new AuditoriaService(self::$conexion),
            $correo,
            new ConfigDinamica(self::$conexion),
        );
    }
}

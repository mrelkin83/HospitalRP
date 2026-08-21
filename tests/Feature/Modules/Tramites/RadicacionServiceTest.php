<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tramites;

use App\Core\Audit\AuditoriaService;
use App\Modules\Tramites\Command\SembrarTramitesCommand;
use App\Modules\Tramites\Entity\TipoTramite;
use App\Modules\Tramites\Repository\TipoTramiteRepository;
use App\Modules\Tramites\Repository\TramiteRepository;
use App\Modules\Tramites\Request\SolicitudTramite;
use App\Modules\Tramites\Service\CalculadoraDiasHabiles;
use App\Modules\Tramites\Service\FestivosColombia;
use App\Modules\Tramites\Service\GeneradorRadicado;
use App\Modules\Tramites\Service\RadicacionService;
use Doctrine\DBAL\Connection;
use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Support\BaseDatosDePruebas;
use Tests\Support\NotificadorCorreoEspia;

/**
 * Criterios de la Fase 5 sobre la radicación: anónimo funciona sin
 * identificación, sin casilla no hay radicación, y el rastro queda completo.
 */
#[CoversClass(RadicacionService::class)]
#[CoversClass(TramiteRepository::class)]
#[CoversClass(TipoTramiteRepository::class)]
#[CoversClass(SolicitudTramite::class)]
final class RadicacionServiceTest extends TestCase
{
    private static Connection $conexion;
    private TipoTramiteRepository $tipos;
    private TramiteRepository $tramites;
    private NotificadorCorreoEspia $correo;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        foreach ([
            'tramite_adjuntos', 'tramite_seguimiento', 'tramite_valores', 'autorizaciones_datos',
            'tramites', 'tramite_campos', 'tramite_estados', 'tipos_tramite',
            'radicado_secuencias', 'politicas', 'festivos',
        ] as $tabla) {
            self::$conexion->executeStatement("DELETE FROM {$tabla}");
        }

        foreach (FestivosColombia::delAnio((int) date('Y')) as $fecha => $nombre) {
            self::$conexion->insert('festivos', ['fecha' => $fecha, 'nombre' => $nombre]);
        }

        $tester = new CommandTester(new SembrarTramitesCommand(self::$conexion, new AuditoriaService(self::$conexion)));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $this->tipos = new TipoTramiteRepository(self::$conexion);
        $this->tramites = new TramiteRepository(self::$conexion);
        $this->correo = new NotificadorCorreoEspia();
    }

    public function testRadicaConTodoElRastro(): void
    {
        $tipo = $this->tipoPqrsdf();
        $resultado = $this->servicio()->radicar($tipo, $this->solicitud($tipo), [], '203.0.113.7', 'PHPUnit');

        // Radicado con el formato del glosario.
        self::assertMatchesRegularExpression('/^HRPL-\d{4}-\d{6}$/', $resultado['radicado']);

        // El trámite quedó con su término y estado inicial.
        $tramite = $this->tramites->porId($resultado['tramite_id']);
        self::assertNotNull($tramite);
        self::assertSame('recibido', $tramite['estado_codigo']);
        self::assertSame(15, (int) $tramite['dias_termino']);

        // El vencimiento coincide con la calculadora de días hábiles.
        $esperado = (new CalculadoraDiasHabiles(self::$conexion))
            ->vencimiento(new \DateTimeImmutable((string) $tramite['recibido_en']), 15);
        self::assertSame($esperado->format('Y-m-d'), (string) $tramite['vence_en']);

        // Valor dinámico guardado.
        $valores = $this->tramites->valores($resultado['tramite_id']);
        self::assertSame('Queja', $valores[0]['valor'] ?? null);

        // Evidencia de autorización con la política activa (Ley 1581).
        $evidencia = self::$conexion->fetchAssociative(
            "SELECT * FROM autorizaciones_datos WHERE origen = 'tramite' AND origen_id = ?",
            [$resultado['tramite_id']],
        );
        self::assertNotFalse($evidencia);
        self::assertSame(1, (int) $evidencia['aceptado']);

        // Seguimiento inicial visible al ciudadano y correo enviado.
        self::assertCount(1, $this->tramites->seguimientos($resultado['tramite_id'], soloPublicos: true));
        self::assertCount(1, $this->correo->enviados);
        self::assertStringContainsString($resultado['radicado'], $this->correo->enviados[0]['asunto']);
    }

    public function testSinCasillaDeAutorizacionNoHayRadicacion(): void
    {
        $tipo = $this->tipoPqrsdf();

        // La validación del formulario ya lo rechaza…
        $sinCasilla = $this->solicitud($tipo, autoriza: false);
        self::assertArrayHasKey('autoriza_datos', $sinCasilla->validar($tipo));

        // …y el servicio tampoco lo permite por ninguna otra ruta.
        $this->expectException(DomainException::class);
        $this->servicio()->radicar($tipo, $sinCasilla, [], null, null);
    }

    public function testUnTramiteAnonimoFuncionaSinIdentificacion(): void
    {
        $tipo = $this->tipoPqrsdf();
        $solicitud = $this->solicitud($tipo, anonimo: true);

        // Anónimo: la validación no exige nombre ni documento.
        self::assertSame([], $solicitud->validar($tipo));

        $resultado = $this->servicio()->radicar($tipo, $solicitud, [], null, null);

        $tramite = $this->tramites->porId($resultado['tramite_id']);
        self::assertNotNull($tramite);
        self::assertSame(1, (int) $tramite['anonimo']);
        self::assertNull($tramite['nombre']);
        self::assertNull($tramite['numero_documento']);
        // Sin correo a quién notificar.
        self::assertCount(0, $this->correo->enviados);
    }

    public function testElCorreoCaidoNoTumbaLaRadicacion(): void
    {
        $tipo = $this->tipoPqrsdf();
        $servicio = $this->servicio(correoCaido: true);

        $resultado = $servicio->radicar($tipo, $this->solicitud($tipo), [], null, null);

        self::assertNotNull($this->tramites->porId($resultado['tramite_id']));

        $fallo = self::$conexion->fetchOne(
            "SELECT COUNT(*) FROM auditoria WHERE entidad = 'tramites' AND descripcion LIKE 'FALLO al enviar%' AND entidad_id = ?",
            [$resultado['tramite_id']],
        );
        self::assertSame(1, (int) $fallo);
    }

    // --- soporte ---

    private function servicio(bool $correoCaido = false): RadicacionService
    {
        $this->correo = new NotificadorCorreoEspia($correoCaido);

        return new RadicacionService(
            self::$conexion,
            $this->tramites,
            new GeneradorRadicado(self::$conexion),
            new CalculadoraDiasHabiles(self::$conexion),
            new AuditoriaService(self::$conexion),
            $this->correo,
        );
    }

    private function tipoPqrsdf(): TipoTramite
    {
        $tipo = $this->tipos->activoPorSlug('pqrsdf');
        self::assertNotNull($tipo);

        return $tipo;
    }

    private function solicitud(TipoTramite $tipo, bool $autoriza = true, bool $anonimo = false): SolicitudTramite
    {
        $campoTipoSolicitud = $tipo->campos[0]['id'];

        return new SolicitudTramite(
            anonimo: $anonimo,
            nombre: $anonimo ? null : 'María Pérez',
            tipoDocumento: $anonimo ? null : 'CC',
            numeroDocumento: $anonimo ? null : '1065123456',
            correo: $anonimo ? null : 'maria@example.com',
            telefono: null,
            direccion: null,
            municipio: 'Valledupar',
            medioRespuesta: 'correo',
            asunto: 'Demora en la asignación de cita',
            descripcion: 'Llevo dos semanas esperando la asignación de una cita de pediatría.',
            valores: [$campoTipoSolicitud => 'Queja'],
            autorizaDatos: $autoriza,
        );
    }
}

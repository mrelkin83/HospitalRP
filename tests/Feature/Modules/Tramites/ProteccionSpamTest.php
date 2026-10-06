<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tramites;

use App\Modules\Tramites\Entity\TipoTramite;
use App\Modules\Tramites\Request\SolicitudTramite;
use App\Modules\Tramites\Service\ProteccionSpam;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Tests\Support\BaseDatosDePruebas;

/**
 * Anti-SPAM del formulario público (ITA 13.3.c) y dirección de
 * correspondencia completa (ITA 13.3.g).
 *
 * El equilibrio que se verifica aquí es el que importa: parar al robot sin
 * estorbarle a quien radica. Por eso la prueba del límite comprueba las dos
 * caras —que la solicitud 10 pasa y la 11 no— y no solo que bloquea.
 *
 * Las IP son del bloque 203.0.113.0/24, reservado por la RFC 5737 para
 * documentación: nunca corresponden a una persona real.
 */
#[CoversClass(ProteccionSpam::class)]
#[CoversClass(SolicitudTramite::class)]
final class ProteccionSpamTest extends TestCase
{
    private const string IP = '203.0.113.7';

    private static Connection $conexion;
    private ProteccionSpam $spam;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM tramite_intentos');
        $this->spam = new ProteccionSpam(self::$conexion);
    }

    protected function tearDown(): void
    {
        self::$conexion->executeStatement('DELETE FROM tramite_intentos');
    }

    public function testElCampoTrampaRellenoDelataAlRobot(): void
    {
        $conTrampa = Request::create('/', 'POST', [ProteccionSpam::CAMPO_TRAMPA => 'http://spam.example']);

        self::assertTrue($this->spam->pareceRobot($conTrampa));
    }

    public function testUnaPersonaDejaElCampoTrampaVacioYPasa(): void
    {
        self::assertFalse($this->spam->pareceRobot(
            Request::create('/', 'POST', ['asunto' => 'Solicito copia de un acta']),
        ));

        // Un espacio en blanco tampoco es contenido: algunos navegadores lo
        // dejan al autocompletar y no debe costarle la radicación a nadie.
        self::assertFalse($this->spam->pareceRobot(
            Request::create('/', 'POST', [ProteccionSpam::CAMPO_TRAMPA => '   ']),
        ));
    }

    public function testElLimiteDejaPasarDiezSolicitudesPorHoraYFrenaLaOnce(): void
    {
        for ($i = 0; $i < 10; $i++) {
            self::assertFalse(
                $this->spam->excedeLimite(self::IP),
                sprintf('La solicitud %d debía pasar: el límite es de 10 por hora.', $i + 1),
            );
            $this->spam->registrarIntento(self::IP);
        }

        self::assertTrue($this->spam->excedeLimite(self::IP));
    }

    public function testElLimiteEsPorIpYNoAfectaAOtraConexion(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->spam->registrarIntento(self::IP);
        }

        self::assertTrue($this->spam->excedeLimite(self::IP));
        self::assertFalse($this->spam->excedeLimite('203.0.113.8'));
    }

    /** Fuera de la ventana no cuenta: el límite es por hora, no acumulativo. */
    public function testLosIntentosViejosNoCuentanNiSobrevivenALaPurga(): void
    {
        for ($i = 0; $i < 10; $i++) {
            self::$conexion->insert('tramite_intentos', [
                'ip' => self::IP,
                'creado_en' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            ]);
        }

        self::assertFalse($this->spam->excedeLimite(self::IP));
        self::assertSame(10, $this->spam->purgar());
        self::assertSame(
            0,
            (int) self::$conexion->fetchOne('SELECT COUNT(*) FROM tramite_intentos'),
        );
    }

    /**
     * Sin IP (consola, cabecera ausente) no se bloquea a nadie: el derecho de
     * petición no puede depender de un dato de red.
     */
    public function testSinIpNoSeBloqueaNiSeRegistra(): void
    {
        self::assertFalse($this->spam->excedeLimite(null));
        $this->spam->registrarIntento(null);

        self::assertSame(
            0,
            (int) self::$conexion->fetchOne('SELECT COUNT(*) FROM tramite_intentos'),
        );
    }

    /**
     * ITA 13.3.g: la dirección de correspondencia se captura completa.
     * El país se asume Colombia porque el criterio solo lo exige «en caso que
     * sea diferente», y pedírselo a todos añade un campo que nadie cambia.
     */
    public function testLaDireccionDeCorrespondenciaSeLeeCompletaDelFormulario(): void
    {
        $peticion = Request::create('/', 'POST', [
            'direccion' => 'Calle 16 n.º 17-141',
            'barrio' => 'Hernando Santana',
            'municipio' => 'Valledupar',
            'asunto' => 'Solicito copia de un acta',
            'descripcion' => 'Texto de la solicitud.',
        ]);

        $solicitud = SolicitudTramite::desdePeticion($peticion, $this->tipoMinimo());

        self::assertSame('Calle 16 n.º 17-141', $solicitud->direccion);
        self::assertSame('Hernando Santana', $solicitud->barrio);
        self::assertSame('Valledupar', $solicitud->municipio);
        self::assertSame('Colombia', $solicitud->pais);
    }

    public function testElPaisSeRespetaCuandoQuienRadicaViveFueraDeColombia(): void
    {
        $peticion = Request::create('/', 'POST', [
            'pais' => 'España',
            'asunto' => 'Solicito copia de un acta',
            'descripcion' => 'Texto de la solicitud.',
        ]);

        self::assertSame('España', SolicitudTramite::desdePeticion($peticion, $this->tipoMinimo())->pais);
    }

    /** ITA 7.1: un trámite sin la columna se asume gratuito, nunca con costo. */
    public function testUnTramiteSeAsumeGratuitoMientrasNadieDigaLoContrario(): void
    {
        self::assertTrue($this->tipoMinimo()->gratuito);
        self::assertNull($this->tipoMinimo()->costoDetalle);

        $conCosto = TipoTramite::desdeFila([
            ...$this->filaMinima(),
            'gratuito' => 0,
            'costo_detalle' => 'Copia de historia clínica: $500 por folio.',
        ]);

        self::assertFalse($conCosto->gratuito);
        self::assertSame('Copia de historia clínica: $500 por folio.', $conCosto->costoDetalle);
    }

    // --- soporte ---

    private function tipoMinimo(): TipoTramite
    {
        return TipoTramite::desdeFila($this->filaMinima());
    }

    /** @return array<string, mixed> */
    private function filaMinima(): array
    {
        return [
            'id' => 1,
            'nombre' => 'Prueba',
            'slug' => 'prueba',
            'prefijo_radicado' => 'HRPL',
            'dias_termino' => 15,
            'tipo_dias' => 'habiles',
            'permite_anonimo' => 0,
            'requiere_adjunto' => 0,
            'max_adjuntos' => 3,
            'activo' => 1,
        ];
    }
}

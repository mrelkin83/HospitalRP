<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Eventos;

use App\Modules\Convocatorias\Repository\ConvocatoriaRepository;
use App\Modules\Eventos\Repository\EventoRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosDePruebas;

/**
 * Calendario de eventos (bloque B6, exigido por los ítems 1.12 y 6.5 del ITA)
 * y convocatorias (bloque B4).
 */
#[CoversClass(EventoRepository::class)]
#[CoversClass(ConvocatoriaRepository::class)]
final class AgendaYConvocatoriasTest extends TestCase
{
    private static Connection $conexion;
    private EventoRepository $eventos;
    private ConvocatoriaRepository $convocatorias;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM eventos');
        self::$conexion->executeStatement('DELETE FROM convocatorias');
        $this->eventos = new EventoRepository(self::$conexion);
        $this->convocatorias = new ConvocatoriaRepository(self::$conexion);
    }

    public function testUnEventoSinPublicarNoSeVe(): void
    {
        $this->crearEvento('Jornada de vacunación', '2026-03-10 08:00:00', publicado: false);

        self::assertSame([], $this->eventos->delMes(2026, 3));
        self::assertNull($this->eventos->publicoPorSlug('jornada-de-vacunacion'));
    }

    /**
     * Una semana de la salud que empieza el 28 de febrero y termina el 4 de
     * marzo tiene que verse en los dos meses, no solo en el de inicio.
     */
    public function testUnEventoQueCruzaElFinDeMesApareceEnAmbosMeses(): void
    {
        $this->crearEvento('Semana de la salud', '2026-02-28 08:00:00', '2026-03-04 18:00:00');

        self::assertCount(1, $this->eventos->delMes(2026, 2));
        self::assertCount(1, $this->eventos->delMes(2026, 3));
        self::assertSame([], $this->eventos->delMes(2026, 4));
    }

    public function testLosProximosNoIncluyenLoQueYaPaso(): void
    {
        $this->crearEvento('Rendición de cuentas 2019', date('Y-m-d H:i:s', strtotime('-2 years')));
        $this->crearEvento('Rendición de cuentas próxima', date('Y-m-d H:i:s', strtotime('+10 days')));

        $proximos = $this->eventos->proximos();

        self::assertCount(1, $proximos);
        self::assertSame('Rendición de cuentas próxima', $proximos[0]['titulo']);
    }

    public function testElSlugRepetidoRecibeSufijoEnLugarDeChocar(): void
    {
        $this->crearEvento('Rendición de cuentas', '2026-05-01 09:00:00');
        $this->crearEvento('Rendición de cuentas', '2027-05-01 09:00:00');

        $slugs = self::$conexion->fetchFirstColumn('SELECT slug FROM eventos ORDER BY id');

        self::assertSame(['rendicion-de-cuentas', 'rendicion-de-cuentas-2'], $slugs);
    }

    public function testLasConvocatoriasAbiertasVanAntesQueLasCerradas(): void
    {
        $this->crearConvocatoria('Proceso cerrado', 'contratacion', 'cerrada');
        $this->crearConvocatoria('Vacante abierta', 'empleo', 'abierta');

        $titulos = array_column($this->convocatorias->publicadas(), 'titulo');

        self::assertSame(['Vacante abierta', 'Proceso cerrado'], $titulos);
    }

    public function testElFiltroPorTipoSoloDevuelveEseTipo(): void
    {
        $this->crearConvocatoria('Vacante de enfermería', 'empleo', 'abierta');
        $this->crearConvocatoria('Invitación pública 001', 'contratacion', 'abierta');

        $empleo = $this->convocatorias->publicadas('empleo');

        self::assertCount(1, $empleo);
        self::assertSame('Vacante de enfermería', $empleo[0]['titulo']);
    }

    public function testUnaConvocatoriaSinPublicarNoSeVe(): void
    {
        $id = $this->crearConvocatoria('Borrador interno', 'empleo', 'proxima');
        self::$conexion->update('convocatorias', ['publicada' => 0], ['id' => $id]);

        self::assertSame([], $this->convocatorias->publicadas());
        self::assertNull($this->convocatorias->publicaPorSlug('borrador-interno'));
    }

    private function crearEvento(string $titulo, string $inicio, ?string $fin = null, bool $publicado = true): int
    {
        return $this->eventos->crear([
            'titulo' => $titulo,
            'inicio' => $inicio,
            'fin' => $fin,
            'tipo' => 'institucional',
            'publicado' => $publicado ? 1 : 0,
        ]);
    }

    private function crearConvocatoria(string $titulo, string $tipo, string $estado): int
    {
        return $this->convocatorias->crear([
            'titulo' => $titulo,
            'tipo' => $tipo,
            'estado' => $estado,
            'descripcion' => 'Descripción de prueba.',
            'publicada' => 1,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Encuestas;

use App\Modules\Encuestas\Repository\EncuestaRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosDePruebas;

/**
 * Encuestas de satisfacción (Fase 9, bloque D2).
 *
 * Lo que se fija aquí no es la funcionalidad bonita sino las tres promesas
 * que se le hacen al ciudadano en el formulario: que es anónima, que no puede
 * responder dos veces, y que los resultados se leen agregados.
 */
#[CoversClass(EncuestaRepository::class)]
final class EncuestasTest extends TestCase
{
    private static Connection $conexion;
    private EncuestaRepository $encuestas;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM encuestas');
        $this->encuestas = new EncuestaRepository(self::$conexion);
    }

    /**
     * La tabla no debe tener por dónde entrar un dato personal. Si alguien
     * añade una columna `cedula` o `correo` a futuro, esta prueba lo frena.
     */
    public function testLaTablaDeRespuestasNoGuardaDatosDeLaPersona(): void
    {
        $columnas = array_map(
            static fn (array $c): string => strtolower((string) $c['Field']),
            self::$conexion->fetchAllAssociative('SHOW COLUMNS FROM encuesta_respuestas'),
        );

        self::assertSame(['id', 'encuesta_id', 'huella', 'enviado_en'], $columnas);

        foreach (['cedula', 'documento', 'nombre', 'correo', 'email', 'telefono', 'usuario_id', 'ip'] as $prohibida) {
            self::assertNotContains($prohibida, $columnas, "La columna «{$prohibida}» no puede existir aquí");
        }
    }

    public function testLaMismaHuellaNoPuedeResponderDosVeces(): void
    {
        [$encuestaId, $preguntaId] = $this->crearEncuestaConEscala();

        self::assertTrue($this->encuestas->guardarRespuesta($encuestaId, 'huella-a', [$preguntaId => ['5']]));
        self::assertFalse($this->encuestas->guardarRespuesta($encuestaId, 'huella-a', [$preguntaId => ['1']]));

        self::assertSame(1, $this->encuestas->totalRespuestas($encuestaId));
        self::assertTrue($this->encuestas->yaRespondio($encuestaId, 'huella-a'));
    }

    public function testHuellasDistintasSiSuman(): void
    {
        [$encuestaId, $preguntaId] = $this->crearEncuestaConEscala();

        $this->encuestas->guardarRespuesta($encuestaId, 'huella-a', [$preguntaId => ['5']]);
        $this->encuestas->guardarRespuesta($encuestaId, 'huella-b', [$preguntaId => ['5']]);
        $this->encuestas->guardarRespuesta($encuestaId, 'huella-c', [$preguntaId => ['3']]);

        self::assertSame(3, $this->encuestas->totalRespuestas($encuestaId));
        self::assertSame(['5' => 2, '3' => 1], $this->encuestas->conteoDe($preguntaId));
    }

    /** Una respuesta a medias no puede dejar valores huérfanos. */
    public function testLaRespuestaSeGuardaCompletaONoSeGuarda(): void
    {
        [$encuestaId, $preguntaId] = $this->crearEncuestaConEscala();

        $this->encuestas->guardarRespuesta($encuestaId, 'huella-a', [$preguntaId => ['4']]);

        $huerfanos = self::$conexion->fetchOne(
            'SELECT COUNT(*) FROM encuesta_valores v
              LEFT JOIN encuesta_respuestas r ON r.id = v.respuesta_id
              WHERE r.id IS NULL',
        );

        self::assertSame(0, (int) $huerfanos);
    }

    public function testCerrarLaEncuestaLaSacaDelListadoPublico(): void
    {
        [$encuestaId] = $this->crearEncuestaConEscala();

        self::assertCount(1, $this->encuestas->abiertas());

        self::$conexion->update('encuestas', ['estado' => 'cerrada'], ['id' => $encuestaId]);

        self::assertSame([], $this->encuestas->abiertas());
        self::assertNull($this->encuestas->abiertaPorSlug('satisfaccion-urgencias'));
    }

    public function testUnaEncuestaConFechaDeCierrePasadaYaNoSeResponde(): void
    {
        [$encuestaId] = $this->crearEncuestaConEscala();
        self::$conexion->update(
            'encuestas',
            ['cierre' => date('Y-m-d H:i:s', strtotime('-1 hour'))],
            ['id' => $encuestaId],
        );

        self::assertNull($this->encuestas->abiertaPorSlug('satisfaccion-urgencias'));
    }

    /** @return array{int, int} */
    private function crearEncuestaConEscala(): array
    {
        self::$conexion->insert('encuestas', [
            'titulo' => 'Satisfacción en urgencias',
            'slug' => 'satisfaccion-urgencias',
            'estado' => 'abierta',
        ]);
        $encuestaId = (int) self::$conexion->lastInsertId();

        self::$conexion->insert('encuesta_preguntas', [
            'encuesta_id' => $encuestaId,
            'enunciado' => '¿Cómo calificas la atención recibida?',
            'tipo' => 'escala',
            'obligatoria' => 1,
            'orden' => 0,
        ]);

        return [$encuestaId, (int) self::$conexion->lastInsertId()];
    }
}

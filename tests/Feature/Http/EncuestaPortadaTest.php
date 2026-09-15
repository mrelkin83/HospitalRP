<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Core\Config\ConfigAplicacion;
use App\Core\Config\Env;
use App\Http\Kernel;
use App\Http\Web\EncuestasPublicoController;
use App\Modules\Encuestas\Repository\EncuestaRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouteCollection;
use Tests\Support\BaseDatosDePruebas;

/**
 * La encuesta se responde EN la portada, sin salir de ella.
 *
 * Aquí se prueba el contrato que hace posible eso: el mismo endpoint del
 * formulario completo contesta JSON cuando se lo piden, y devuelve los
 * resultados ya recalculados para que la tarjeta pinte las barras sin
 * recargar. Lo que el navegador haga con ese JSON es cosa de `encuesta.js`;
 * lo que no puede fallar nunca es el servidor.
 */
#[CoversClass(EncuestasPublicoController::class)]
#[CoversClass(EncuestaRepository::class)]
final class EncuestaPortadaTest extends TestCase
{
    private const string SLUG = 'encuesta-de-prueba-portada';

    private static Connection $conexion;
    private Kernel $kernel;

    /** @var array{encuesta: int, escala: int, opcion: int, texto: int} */
    private array $ids;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM encuestas WHERE slug = ?', [self::SLUG]);

        $raiz = dirname(__DIR__, 3);
        $env = Env::desdeSuperglobales();

        /** @var callable(Env): ContainerBuilder $fabrica */
        $fabrica = require $raiz . '/config/servicios.php';
        $contenedor = $fabrica($env);

        /** @var ConfigAplicacion $config */
        $config = $contenedor->get(ConfigAplicacion::class);

        /** @var RouteCollection $rutas */
        $rutas = require $raiz . '/config/rutas.php';

        $this->kernel = new Kernel($contenedor, $rutas, $config);
        $this->ids = $this->crearEncuesta();
    }

    protected function tearDown(): void
    {
        self::$conexion->executeStatement('DELETE FROM encuestas WHERE slug = ?', [self::SLUG]);
    }

    /** La tarjeta trae el cuestionario entero, no un enlace a otra página. */
    public function testLaPortadaTraeLasPreguntasDeLaEncuesta(): void
    {
        $cuerpo = (string) $this->kernel->manejar(Request::create('/'))->getContent();

        self::assertStringContainsString('¿Cómo califica la atención?', $cuerpo);
        self::assertStringContainsString('action="/encuestas/' . self::SLUG . '"', $cuerpo);
        self::assertStringContainsString('x-data="encuestaPortada"', $cuerpo);
        self::assertStringContainsString('/js/encuesta.js', $cuerpo);
    }

    public function testResponderPorJsonDevuelveLosResultadosRecalculados(): void
    {
        $respuesta = $this->responder(['p' . $this->ids['escala'] => ['5'], 'p' . $this->ids['opcion'] => ['Sí']]);

        self::assertSame(Response::HTTP_OK, $respuesta->getStatusCode());

        /** @var array{ok: bool, total: int, resultados: list<array<string, mixed>>} $datos */
        $datos = json_decode((string) $respuesta->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($datos['ok']);
        self::assertSame(1, $datos['total']);

        $escala = $datos['resultados'][0];
        self::assertSame('escala', $escala['tipo']);
        self::assertCount(5, $escala['opciones'], 'La escala 1-5 debe traer sus cinco peldaños aunque solo se vote uno.');

        $votado = array_values(array_filter($escala['opciones'], static fn (array $o): bool => $o['texto'] === '5'))[0];
        self::assertSame(1, $votado['votos']);
        self::assertSame(100, $votado['porcentaje']);
    }

    /** Sin responder la pregunta obligatoria, el JSON dice que no y no guarda. */
    public function testSinLaPreguntaObligatoriaElJsonRechaza(): void
    {
        $respuesta = $this->responder(['p' . $this->ids['opcion'] => ['Sí']]);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $respuesta->getStatusCode());

        /** @var array{ok: bool} $datos */
        $datos = json_decode((string) $respuesta->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertFalse($datos['ok']);
        self::assertSame(0, (int) self::$conexion->fetchOne(
            'SELECT COUNT(*) FROM encuesta_respuestas WHERE encuesta_id = ?',
            [$this->ids['encuesta']],
        ));
    }

    /**
     * Mirar cómo va una consulta pública no exige haber participado: es la
     * petición que motivó esta pantalla.
     */
    public function testLosResultadosSeVenSinHaberRespondido(): void
    {
        $respuesta = $this->kernel->manejar(Request::create('/encuestas/' . self::SLUG . '/resultados'));

        self::assertSame(Response::HTTP_OK, $respuesta->getStatusCode());
        self::assertStringContainsString('¿Cómo califica la atención?', (string) $respuesta->getContent());
    }

    /**
     * El texto libre puede traer el nombre o el diagnóstico de quien escribe:
     * en los resultados públicos solo se cuenta, nunca se publica.
     */
    public function testElTextoLibreNoSePublicaEnLosResultados(): void
    {
        $this->responder([
            'p' . $this->ids['escala'] => ['4'],
            'p' . $this->ids['texto'] => ['Me atendió la doctora Pérez y mi cédula es 1234567890'],
        ]);

        $cuerpo = (string) $this->kernel->manejar(Request::create('/encuestas/' . self::SLUG . '/resultados'))->getContent();

        self::assertStringNotContainsString('1234567890', $cuerpo);
        self::assertStringNotContainsString('doctora Pérez', $cuerpo);
        self::assertStringContainsString('respuesta escrita', $cuerpo);
    }

    // --- soporte ---

    /**
     * @param array<string, list<string>> $valores
     */
    private function responder(array $valores): Response
    {
        return $this->kernel->manejar(Request::create(
            '/encuestas/' . self::SLUG,
            'POST',
            $valores,
            server: ['HTTP_ACCEPT' => 'application/json'],
        ));
    }

    /**
     * @return array{encuesta: int, escala: int, opcion: int, texto: int}
     */
    private function crearEncuesta(): array
    {
        self::$conexion->insert('encuestas', [
            'titulo' => 'Encuesta de prueba de portada',
            'slug' => self::SLUG,
            'descripcion' => 'Prueba.',
            'estado' => 'abierta',
        ]);

        $encuestaId = (int) self::$conexion->lastInsertId();
        $ids = ['encuesta' => $encuestaId];

        $preguntas = [
            ['escala', '¿Cómo califica la atención?', null, 1],
            ['opcion', '¿Volverías?', ['Sí', 'No'], 0],
            ['texto', '¿Qué mejorarías?', null, 0],
        ];

        foreach ($preguntas as $orden => [$clave, $enunciado, $opciones, $obligatoria]) {
            self::$conexion->insert('encuesta_preguntas', [
                'encuesta_id' => $encuestaId,
                'enunciado' => $enunciado,
                'tipo' => $clave === 'opcion' ? 'opcion_unica' : $clave,
                'opciones' => $opciones === null ? null : json_encode($opciones, JSON_THROW_ON_ERROR),
                'obligatoria' => $obligatoria,
                'orden' => $orden + 1,
            ]);

            $ids[$clave] = (int) self::$conexion->lastInsertId();
        }

        /** @var array{encuesta: int, escala: int, opcion: int, texto: int} $ids */
        return $ids;
    }
}

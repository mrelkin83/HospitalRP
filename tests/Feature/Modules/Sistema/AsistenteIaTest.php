<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Sistema;

use App\Core\Config\ConfigDinamica;
use App\Modules\Sistema\Entity\FuncionIa;
use App\Modules\Sistema\Exception\IaDesactivadaException;
use App\Modules\Sistema\Repository\IaUsoRepository;
use App\Modules\Sistema\Service\AsistenteIa;
use App\Ports\AiProvider;
use App\Ports\FabricaAiProvider;
use App\Ports\ImagenIa;
use App\Ports\PeticionIa;
use App\Ports\ProveedorIaException;
use App\Ports\RespuestaIa;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosDePruebas;

/**
 * Criterios de la Fase 8: con la IA desactivada todo funciona igual (aquí:
 * lanza su excepción tipada y NO registra nada), cambiar de proveedor es
 * configuración, y cada llamada —también la fallida— queda en ia_usos.
 */
#[CoversClass(AsistenteIa::class)]
#[CoversClass(IaUsoRepository::class)]
final class AsistenteIaTest extends TestCase
{
    private static Connection $conexion;
    private ProveedorIaFalso $proveedor;
    private FabricaIaFalsa $fabrica;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM ia_usos');
        self::$conexion->executeStatement("DELETE FROM configuracion WHERE clave LIKE 'ia.%'");

        $this->proveedor = new ProveedorIaFalso();
        $this->fabrica = new FabricaIaFalsa($this->proveedor);
    }

    public function testDesactivadaLanzaSuExcepcionYNoRegistraNada(): void
    {
        $asistente = $this->asistente(activada: false);

        $this->expectException(IaDesactivadaException::class);

        try {
            $asistente->sugerir(FuncionIa::BorradorNoticia, ['texto' => 'apuntes'], null, null);
        } finally {
            self::assertSame(0, $this->totalUsos());
            self::assertSame(0, $this->proveedor->llamadas);
        }
    }

    public function testSinClaveDelProveedorTampocoEstaActiva(): void
    {
        $this->fabrica->conClave = false;

        self::assertFalse($this->asistente(activada: true)->activa());
    }

    public function testLaSugerenciaDevuelveElTextoYRegistraElConsumo(): void
    {
        $this->proveedor->respuesta = new RespuestaIa('Borrador sugerido.', 120, 45);

        $texto = $this->asistente(activada: true)->sugerir(
            FuncionIa::BorradorNoticia,
            ['texto' => 'jornada de vacunación el sábado'],
            null,
            usuarioId: null,
        );

        self::assertSame('Borrador sugerido.', $texto);

        $uso = self::$conexion->fetchAssociative('SELECT * FROM ia_usos');
        self::assertNotFalse($uso);
        self::assertSame('borrador_noticia', $uso['funcion']);
        self::assertSame('anthropic', $uso['proveedor']);
        self::assertSame('modelo-de-prueba', $uso['modelo']);
        self::assertSame(120, (int) $uso['tokens_entrada']);
        self::assertSame(45, (int) $uso['tokens_salida']);
        self::assertSame(1, (int) $uso['exito']);

        // Los apuntes del funcionario viajan en la petición al proveedor.
        self::assertStringContainsString('jornada de vacunación', $this->proveedor->ultimaPeticion->mensaje);
    }

    public function testElFalloDelProveedorQuedaRegistradoYSeRelanza(): void
    {
        $this->proveedor->error = ProveedorIaException::porHttp('Anthropic', 500, 'boom');
        $asistente = $this->asistente(activada: true);

        try {
            $asistente->sugerir(FuncionIa::MetadatosSeo, ['titulo' => 'x', 'texto' => 'y'], null, null);
            self::fail('Debió relanzar la excepción del proveedor.');
        } catch (ProveedorIaException) {
        }

        $uso = self::$conexion->fetchAssociative('SELECT * FROM ia_usos');
        self::assertNotFalse($uso);
        self::assertSame(0, (int) $uso['exito']);
        self::assertStringContainsString('500', (string) $uso['error']);
    }

    public function testElTextoAlternativoEnviaLaImagenAlProveedor(): void
    {
        $imagen = new ImagenIa('image/png', base64_encode('png-falso'));

        $this->asistente(activada: true)->sugerir(FuncionIa::TextoAlternativo, [], $imagen, null);

        self::assertSame($imagen, $this->proveedor->ultimaPeticion->imagen);
        self::assertStringContainsString('120 caracteres', $this->proveedor->ultimaPeticion->mensaje);
    }

    // --- soporte ---

    private function asistente(bool $activada): AsistenteIa
    {
        foreach ([
            'ia.activada' => $activada ? '1' : '0',
            'ia.proveedor' => 'anthropic',
            'ia.modelo' => 'modelo-de-prueba',
        ] as $clave => $valor) {
            self::$conexion->insert('configuracion', [
                'clave' => $clave,
                'valor' => $valor,
                'tipo' => 'texto',
                'grupo' => 'ia',
                'etiqueta' => $clave,
            ]);
        }

        return new AsistenteIa(
            new ConfigDinamica(self::$conexion),
            $this->fabrica,
            new IaUsoRepository(self::$conexion),
        );
    }

    private function totalUsos(): int
    {
        return (int) self::$conexion->fetchOne('SELECT COUNT(*) FROM ia_usos');
    }
}

final class ProveedorIaFalso implements AiProvider
{
    public int $llamadas = 0;
    public PeticionIa $ultimaPeticion;
    public RespuestaIa $respuesta;
    public ?ProveedorIaException $error = null;

    public function __construct()
    {
        $this->respuesta = new RespuestaIa('sugerencia', 10, 5);
    }

    public function sugerir(PeticionIa $peticion): RespuestaIa
    {
        $this->llamadas++;
        $this->ultimaPeticion = $peticion;

        if ($this->error !== null) {
            throw $this->error;
        }

        return $this->respuesta;
    }
}

final class FabricaIaFalsa implements FabricaAiProvider
{
    public bool $conClave = true;

    public function __construct(private readonly ProveedorIaFalso $proveedor)
    {
    }

    public function tieneClave(string $proveedor): bool
    {
        return $this->conClave;
    }

    public function crear(string $proveedor, string $modelo): AiProvider
    {
        return $this->proveedor;
    }

    public function listarModelos(string $proveedor): array
    {
        return ['origen' => 'estatico', 'modelos' => [], 'nota' => null];
    }
}

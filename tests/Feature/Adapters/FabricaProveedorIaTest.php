<?php

declare(strict_types=1);

namespace Tests\Feature\Adapters;

use App\Adapters\Ia\AnthropicProvider;
use App\Adapters\Ia\ClienteHttpJson;
use App\Adapters\Ia\FabricaProveedorIa;
use App\Adapters\Ia\OpenAiProvider;
use App\Core\Support\Cifrador;
use App\Ports\ProveedorIaException;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosDePruebas;

/**
 * La fábrica de proveedores con las claves administradas desde el panel
 * (patrón del módulo de IA del SEPUD): la clave cifrada en `configuracion`
 * manda, el .env queda de respaldo, y cada proveedor del catálogo produce
 * el adaptador correcto.
 */
#[CoversClass(FabricaProveedorIa::class)]
final class FabricaProveedorIaTest extends TestCase
{
    private static Connection $conexion;
    private Cifrador $cifrador;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement("DELETE FROM configuracion WHERE clave LIKE 'ia.clave_%' OR clave = 'ia.url_base'");
        $this->cifrador = new Cifrador(base64_encode(random_bytes(32)));
    }

    protected function tearDown(): void
    {
        self::$conexion->executeStatement("DELETE FROM configuracion WHERE clave LIKE 'ia.clave_%' OR clave = 'ia.url_base'");
    }

    private function fabrica(string $envAnthropic = '', string $envOpenai = ''): FabricaProveedorIa
    {
        return new FabricaProveedorIa(new ClienteHttpJson(), self::$conexion, $this->cifrador, $envAnthropic, $envOpenai);
    }

    private function guardarConfig(string $clave, string $valor): void
    {
        self::$conexion->insert('configuracion', [
            'clave' => $clave,
            'valor' => $valor,
            'tipo' => 'texto',
            'grupo' => 'ia',
            'etiqueta' => 'prueba',
            'editable' => 0,
        ]);
    }

    public function testLaClaveCifradaDelPanelManda(): void
    {
        $fabrica = $this->fabrica();
        self::assertFalse($fabrica->tieneClave('deepseek'));

        $this->guardarConfig('ia.clave_deepseek', $this->cifrador->cifrar('sk-deepseek-panel'));

        self::assertTrue($fabrica->tieneClave('deepseek'));
        self::assertInstanceOf(OpenAiProvider::class, $fabrica->crear('deepseek', ''));
    }

    public function testElEnvQuedaComoRespaldo(): void
    {
        $fabrica = $this->fabrica(envAnthropic: 'sk-ant-del-env');

        self::assertTrue($fabrica->tieneClave('anthropic'));
        self::assertInstanceOf(AnthropicProvider::class, $fabrica->crear('anthropic', 'claude-sonnet-5'));
        self::assertFalse($fabrica->tieneClave('openai'), 'OpenAI sin clave en env ni panel debía dar falso.');
    }

    public function testSinClaveNoSeCrea(): void
    {
        $this->expectException(ProveedorIaException::class);
        $this->fabrica()->crear('mistral', 'mistral-large-latest');
    }

    public function testElPersonalizadoExigeUrlBase(): void
    {
        $this->guardarConfig('ia.clave_personalizado', $this->cifrador->cifrar('sk-local'));

        $this->expectException(ProveedorIaException::class);
        $this->fabrica()->crear('personalizado', 'mi-modelo');
    }

    public function testElPersonalizadoConUrlBaseSeCrea(): void
    {
        $this->guardarConfig('ia.clave_personalizado', $this->cifrador->cifrar('sk-local'));
        $this->guardarConfig('ia.url_base', 'https://mi-servidor.interno/v1');

        self::assertInstanceOf(OpenAiProvider::class, $this->fabrica()->crear('personalizado', 'mi-modelo'));
    }

    public function testSinClaveElListadoDeModelosEsLaReferencia(): void
    {
        $resultado = $this->fabrica()->listarModelos('groq');

        self::assertSame('estatico', $resultado['origen']);
        self::assertContains('llama-3.3-70b-versatile', $resultado['modelos']);
        self::assertNotNull($resultado['nota']);
    }

    public function testTodoElCatalogoDelPanelSePuedeCrearConClave(): void
    {
        foreach (array_keys(FabricaProveedorIa::COMPATIBLES) as $proveedor) {
            $this->guardarConfig('ia.clave_' . $proveedor, $this->cifrador->cifrar('sk-' . $proveedor));
            self::assertInstanceOf(
                OpenAiProvider::class,
                $this->fabrica()->crear($proveedor, ''),
                "El proveedor {$proveedor} no produjo su adaptador.",
            );
        }
    }
}

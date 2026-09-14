<?php

declare(strict_types=1);

namespace Tests\Feature\Adapters;

use App\Adapters\Chat\ChatConfigurable;
use App\Core\Config\ConfigDinamica;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosDePruebas;

/**
 * Chat en vivo (Fase 9, bloque D3).
 *
 * Lo que importa aquí no es que el widget se pinte bonito, sino que no se
 * pinte cuando no debe y que un valor de configuración manipulado no termine
 * dentro de un `<script>` del sitio de un hospital.
 *
 * Va en Feature y no en Unit porque `ConfigDinamica` es final a propósito: se
 * usa la clase real contra la base de pruebas en vez de relajar el diseño
 * para poder doblarla.
 */
#[CoversClass(ChatConfigurable::class)]
final class ChatConfigurableTest extends TestCase
{
    private static Connection $conexion;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement(
            "DELETE FROM configuracion WHERE clave IN ('chat_activo', 'chat_proveedor', 'chat_id_widget')",
        );
    }

    public function testNaceApagado(): void
    {
        $chat = $this->chat(activo: false, proveedor: 'tawk', id: 'abc123');

        self::assertFalse($chat->estaActivo());
        self::assertSame('', $chat->widget());
    }

    public function testNoSeEnciendeSinProveedorConocido(): void
    {
        $chat = $this->chat(activo: true, proveedor: 'proveedor-inventado', id: 'abc123');

        self::assertFalse($chat->estaActivo());
        self::assertSame('', $chat->widget());
    }

    public function testNoSeEnciendeSinIdentificador(): void
    {
        self::assertFalse($this->chat(activo: true, proveedor: 'tawk', id: '')->estaActivo());
    }

    /** @return list<array{string}> */
    public static function identificadoresPeligrosos(): array
    {
        return [
            ['"></script><script>alert(1)</script>'],
            ["' onload='alert(1)"],
            ['abc"><img src=x onerror=alert(1)>'],
            ['id con espacios'],
            [str_repeat('a', 65)],
        ];
    }

    #[DataProvider('identificadoresPeligrosos')]
    public function testUnIdentificadorManipuladoApagaElChatEnLugarDeIncrustarse(string $id): void
    {
        $chat = $this->chat(activo: true, proveedor: 'tawk', id: $id);

        self::assertFalse($chat->estaActivo(), 'Un identificador con forma rara no puede activar el chat');
        self::assertSame('', $chat->widget());
    }

    public function testEncendidoMontaElScriptYElAvisoDelHospital(): void
    {
        $widget = $this->chat(activo: true, proveedor: 'tawk', id: 'abc123')->widget();

        self::assertStringContainsString('https://embed.tawk.to/abc123/default', $widget);
        // El aviso es del hospital y no se delega en el proveedor.
        self::assertStringContainsString('No atiende urgencias ni consultas médicas', $widget);
        self::assertStringContainsString('/urgencias', $widget);
    }

    private function chat(bool $activo, string $proveedor, string $id): ChatConfigurable
    {
        $this->guardar('chat_activo', $activo ? '1' : '0', 'booleano');
        $this->guardar('chat_proveedor', $proveedor, 'texto');
        $this->guardar('chat_id_widget', $id, 'texto');

        return new ChatConfigurable(new ConfigDinamica(self::$conexion));
    }

    private function guardar(string $clave, string $valor, string $tipo): void
    {
        self::$conexion->insert('configuracion', [
            'clave' => $clave,
            'valor' => $valor,
            'tipo' => $tipo,
            'grupo' => 'chat',
            'etiqueta' => $clave,
        ]);
    }
}

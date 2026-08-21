<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Auth;

use App\Core\Auth\AutorizacionService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosDePruebas;

#[CoversClass(AutorizacionService::class)]
final class AutorizacionServiceTest extends TestCase
{
    private static Connection $conexion;
    private AutorizacionService $autorizacion;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM usuarios');
        self::$conexion->executeStatement("DELETE FROM roles WHERE slug LIKE 'prueba-%'");

        $this->autorizacion = new AutorizacionService(self::$conexion);
    }

    public function testElComodinTotalConcedeCualquierPermiso(): void
    {
        $usuarioId = $this->crearUsuarioConPermisos(['*']);

        self::assertTrue($this->autorizacion->puede($usuarioId, 'contenido.publicar'));
        self::assertTrue($this->autorizacion->puede($usuarioId, 'sistema.usuarios.crear'));
    }

    public function testUnPermisoExactoConcedeSoloEsePermiso(): void
    {
        $usuarioId = $this->crearUsuarioConPermisos(['contenido.crear', 'contenido.editar']);

        self::assertTrue($this->autorizacion->puede($usuarioId, 'contenido.crear'));
        self::assertFalse($this->autorizacion->puede($usuarioId, 'contenido.publicar'));
        self::assertFalse($this->autorizacion->puede($usuarioId, 'sistema.usuarios.crear'));
    }

    public function testElComodinDeModuloConcedeTodoElModuloYNadaMas(): void
    {
        $usuarioId = $this->crearUsuarioConPermisos(['contenido.*']);

        self::assertTrue($this->autorizacion->puede($usuarioId, 'contenido.publicar'));
        self::assertTrue($this->autorizacion->puede($usuarioId, 'contenido.noticias.crear'));
        self::assertFalse($this->autorizacion->puede($usuarioId, 'tramites.responder'));
        // «contenido.*» no debe conceder «contenidoextra.ver» por prefijo textual.
        self::assertFalse($this->autorizacion->puede($usuarioId, 'contenidoextra.ver'));
    }

    /**
     * Desactivar la cuenta revoca los permisos al instante, aunque la sesión
     * del navegador siga viva: el Kernel pregunta en cada petición.
     */
    public function testUnUsuarioDesactivadoPierdeTodoSusPermisos(): void
    {
        $usuarioId = $this->crearUsuarioConPermisos(['*']);
        self::$conexion->executeStatement('UPDATE usuarios SET activo = 0 WHERE id = ?', [$usuarioId]);

        // Instancia nueva: la caché por petición no aplica entre peticiones.
        $autorizacion = new AutorizacionService(self::$conexion);

        self::assertFalse($autorizacion->puede($usuarioId, 'contenido.ver'));
    }

    public function testUnUsuarioInexistenteNoTienePermisos(): void
    {
        self::assertFalse($this->autorizacion->puede(999_999, 'contenido.ver'));
    }

    /**
     * @param list<string> $permisos
     */
    private function crearUsuarioConPermisos(array $permisos): int
    {
        self::$conexion->insert('roles', [
            'nombre' => 'Rol de prueba',
            'slug' => 'prueba-' . bin2hex(random_bytes(6)),
            'permisos' => json_encode($permisos, JSON_THROW_ON_ERROR),
        ]);
        $rolId = (int) self::$conexion->lastInsertId();

        self::$conexion->insert('usuarios', [
            'nombre' => 'Usuario de prueba',
            'correo' => bin2hex(random_bytes(6)) . '@hospital.gov.co',
            'hash_password' => 'irrelevante-para-esta-prueba',
            'rol_id' => $rolId,
            'activo' => 1,
        ]);

        return (int) self::$conexion->lastInsertId();
    }
}

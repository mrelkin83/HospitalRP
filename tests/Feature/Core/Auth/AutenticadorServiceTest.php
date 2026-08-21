<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Auth;

use App\Core\Audit\AuditoriaService;
use App\Core\Auth\Autenticacion;
use App\Core\Auth\AutenticadorService;
use App\Core\Auth\HasherPassword;
use App\Core\Auth\ResultadoLogin;
use App\Core\Auth\Usuario;
use App\Core\Auth\UsuarioRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosDePruebas;

/**
 * Reglas de login contra MySQL real: bloqueo por intentos, expiración del
 * bloqueo y auditoría de cada intento (Fase 0, paso 7).
 */
#[CoversClass(AutenticadorService::class)]
#[CoversClass(UsuarioRepository::class)]
#[CoversClass(Usuario::class)]
#[CoversClass(Autenticacion::class)]
final class AutenticadorServiceTest extends TestCase
{
    private const string PASSWORD = 'Secreta123!';

    private static Connection $conexion;
    private static string $hashPassword;
    private static int $rolId;
    private AutenticadorService $autenticador;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
        // Argon2id cuesta ~100 ms por hash: se calcula una vez para la clase.
        self::$hashPassword = (new HasherPassword())->hash(self::PASSWORD);
        self::$rolId = self::rolDePruebas();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM usuarios');

        $this->autenticador = new AutenticadorService(
            new UsuarioRepository(self::$conexion),
            new HasherPassword(),
            new AuditoriaService(self::$conexion),
        );
    }

    public function testEntraConCredencialesCorrectas(): void
    {
        $id = $this->crearUsuario('maria@hospital.gov.co');

        $resultado = $this->intentar('maria@hospital.gov.co', self::PASSWORD);

        self::assertSame(ResultadoLogin::Correcto, $resultado->resultado);
        self::assertSame($id, $resultado->usuario?->id);

        $fila = $this->filaUsuario($id);
        self::assertSame(0, (int) $fila['intentos_fallidos']);
        self::assertNotNull($fila['ultimo_acceso']);

        $auditoria = $this->ultimaAuditoria();
        self::assertSame('inicio_sesion', $auditoria['accion']);
        self::assertSame($id, (int) $auditoria['usuario_id']);
    }

    public function testPasswordErradoIncrementaIntentosYAudita(): void
    {
        $id = $this->crearUsuario('maria@hospital.gov.co');

        $resultado = $this->intentar('maria@hospital.gov.co', 'equivocada');

        self::assertSame(ResultadoLogin::CredencialesInvalidas, $resultado->resultado);
        self::assertSame(1, (int) $this->filaUsuario($id)['intentos_fallidos']);
        self::assertSame('intento_fallido', $this->ultimaAuditoria()['accion']);
    }

    public function testElQuintoFalloBloqueaLaCuenta(): void
    {
        $id = $this->crearUsuario('maria@hospital.gov.co', intentosFallidos: 4);

        $resultado = $this->intentar('maria@hospital.gov.co', 'equivocada');

        self::assertSame(ResultadoLogin::CredencialesInvalidas, $resultado->resultado);

        $fila = $this->filaUsuario($id);
        self::assertNotNull($fila['bloqueado_hasta']);
        self::assertGreaterThan(
            new DateTimeImmutable(),
            new DateTimeImmutable((string) $fila['bloqueado_hasta']),
        );
        self::assertSame('bloqueo_cuenta', $this->ultimaAuditoria()['accion']);
    }

    public function testBloqueadoNoEntraNiConPasswordCorrecto(): void
    {
        $this->crearUsuario(
            'maria@hospital.gov.co',
            bloqueadoHasta: new DateTimeImmutable('+10 minutes'),
        );

        $resultado = $this->intentar('maria@hospital.gov.co', self::PASSWORD);

        self::assertSame(ResultadoLogin::CuentaBloqueada, $resultado->resultado);
        self::assertNull($resultado->usuario);
    }

    public function testElBloqueoExpiradoDejaEntrarYReiniciaContadores(): void
    {
        $id = $this->crearUsuario(
            'maria@hospital.gov.co',
            intentosFallidos: 5,
            bloqueadoHasta: new DateTimeImmutable('-1 minute'),
        );

        $resultado = $this->intentar('maria@hospital.gov.co', self::PASSWORD);

        self::assertSame(ResultadoLogin::Correcto, $resultado->resultado);

        $fila = $this->filaUsuario($id);
        self::assertSame(0, (int) $fila['intentos_fallidos']);
        self::assertNull($fila['bloqueado_hasta']);
    }

    public function testCorreoInexistenteFallaSinRevelarNada(): void
    {
        $resultado = $this->intentar('nadie@hospital.gov.co', self::PASSWORD);

        self::assertSame(ResultadoLogin::CredencialesInvalidas, $resultado->resultado);

        $auditoria = $this->ultimaAuditoria();
        self::assertSame('intento_fallido', $auditoria['accion']);
        self::assertNull($auditoria['usuario_id']);
    }

    public function testCuentaInactivaNoEntraNiConPasswordCorrecto(): void
    {
        $this->crearUsuario('maria@hospital.gov.co', activo: false);

        $resultado = $this->intentar('maria@hospital.gov.co', self::PASSWORD);

        self::assertSame(ResultadoLogin::CredencialesInvalidas, $resultado->resultado);
    }

    // --- soporte ---

    private function intentar(string $correo, string $password): Autenticacion
    {
        return $this->autenticador->intentar($correo, $password, '203.0.113.7', 'PHPUnit');
    }

    private function crearUsuario(
        string $correo,
        bool $activo = true,
        int $intentosFallidos = 0,
        ?DateTimeImmutable $bloqueadoHasta = null,
    ): int {
        self::$conexion->insert('usuarios', [
            'nombre' => 'María Pérez',
            'correo' => $correo,
            'hash_password' => self::$hashPassword,
            'rol_id' => self::$rolId,
            'activo' => $activo ? 1 : 0,
            'intentos_fallidos' => $intentosFallidos,
            'bloqueado_hasta' => $bloqueadoHasta?->format('Y-m-d H:i:s'),
        ]);

        return (int) self::$conexion->lastInsertId();
    }

    /**
     * @return array<string, mixed>
     */
    private function filaUsuario(int $id): array
    {
        $fila = self::$conexion->fetchAssociative('SELECT * FROM usuarios WHERE id = ?', [$id]);
        self::assertNotFalse($fila);

        return $fila;
    }

    /**
     * @return array<string, mixed>
     */
    private function ultimaAuditoria(): array
    {
        $fila = self::$conexion->fetchAssociative('SELECT * FROM auditoria ORDER BY id DESC LIMIT 1');
        self::assertNotFalse($fila);

        return $fila;
    }

    private static function rolDePruebas(): int
    {
        $id = self::$conexion->fetchOne("SELECT id FROM roles WHERE slug = 'administrador'");

        if ($id !== false) {
            return (int) $id;
        }

        self::$conexion->insert('roles', [
            'nombre' => 'Administrador',
            'slug' => 'administrador',
            'permisos' => '["*"]',
            'protegido' => 1,
        ]);

        return (int) self::$conexion->lastInsertId();
    }
}

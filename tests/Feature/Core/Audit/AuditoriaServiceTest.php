<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Audit;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosDePruebas;

/**
 * Criterio de aceptación del paso 6 (Fase 0): un insert queda; un intento de
 * update —o delete— falla. Contra MySQL real: el append-only lo hacen cumplir
 * los triggers de la base, no el código.
 */
#[CoversClass(AuditoriaService::class)]
#[CoversClass(RegistroAuditoria::class)]
final class AuditoriaServiceTest extends TestCase
{
    private static Connection $conexion;
    private AuditoriaService $servicio;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        $this->servicio = new AuditoriaService(self::$conexion);
    }

    public function testUnRegistroCompletoQueda(): void
    {
        $this->servicio->registrar(new RegistroAuditoria(
            accion: AccionAuditoria::Actualizacion,
            entidad: 'noticias',
            entidadId: 42,
            descripcion: 'Corrigió el título de la noticia',
            antes: ['titulo' => 'Jornada de vacunacion'],
            despues: ['titulo' => 'Jornada de vacunación'],
            usuarioId: 7,
            usuarioNombre: 'María Pérez',
            ip: '203.0.113.7',
            userAgent: 'Mozilla/5.0',
        ));

        $fila = $this->ultimaFila();

        self::assertSame('actualizar', $fila['accion']);
        self::assertSame('noticias', $fila['entidad']);
        self::assertSame(42, (int) $fila['entidad_id']);
        self::assertSame('María Pérez', $fila['usuario_nombre']);
        // El JSON debe sobrevivir el viaje con tildes intactas.
        self::assertSame(
            ['titulo' => 'Jornada de vacunación'],
            json_decode((string) $fila['despues'], true),
        );
        // La IP se guarda en binario (VARBINARY 16), no como texto.
        self::assertSame(inet_pton('203.0.113.7'), $fila['ip']);
    }

    public function testAdmiteActorAnonimoEIpInvalida(): void
    {
        // Antes de autenticar no hay usuario, y una IP malformada no debe
        // impedir que el hecho quede registrado.
        $this->servicio->registrar(new RegistroAuditoria(
            accion: AccionAuditoria::IntentoFallido,
            entidad: 'sesion',
            ip: 'no-es-una-ip',
        ));

        $fila = $this->ultimaFila();

        self::assertSame('intento_fallido', $fila['accion']);
        self::assertNull($fila['usuario_id']);
        self::assertNull($fila['ip']);
    }

    public function testElUpdateFalla(): void
    {
        $this->servicio->registrar(new RegistroAuditoria(
            accion: AccionAuditoria::Creacion,
            entidad: 'paginas',
        ));
        $id = (int) self::$conexion->lastInsertId();

        $this->expectException(DriverException::class);
        $this->expectExceptionMessageMatches('/append-only/');

        self::$conexion->executeStatement(
            'UPDATE auditoria SET descripcion = ? WHERE id = ?',
            ['falsificado', $id],
        );
    }

    public function testElDeleteFalla(): void
    {
        $this->servicio->registrar(new RegistroAuditoria(
            accion: AccionAuditoria::Creacion,
            entidad: 'paginas',
        ));
        $id = (int) self::$conexion->lastInsertId();

        $this->expectException(DriverException::class);
        $this->expectExceptionMessageMatches('/append-only/');

        self::$conexion->executeStatement('DELETE FROM auditoria WHERE id = ?', [$id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function ultimaFila(): array
    {
        $fila = self::$conexion->fetchAssociative(
            'SELECT * FROM auditoria WHERE id = ?',
            [(int) self::$conexion->lastInsertId()],
        );

        self::assertNotFalse($fila);

        return $fila;
    }
}

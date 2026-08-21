<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Contenido;

use App\Core\Audit\AuditoriaService;
use App\Modules\Contenido\Service\ProgramadorPublicacionService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosDePruebas;

/**
 * Criterio de aceptación de la Fase 1: una noticia programada se publica
 * sola y se despublica sola — y cada movimiento queda auditado.
 */
#[CoversClass(ProgramadorPublicacionService::class)]
final class ProgramadorPublicacionServiceTest extends TestCase
{
    private static Connection $conexion;
    private ProgramadorPublicacionService $programador;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM noticias');
        self::$conexion->executeStatement('DELETE FROM paginas');

        $this->programador = new ProgramadorPublicacionService(
            self::$conexion,
            new AuditoriaService(self::$conexion),
        );
    }

    public function testPublicaLaAprobadaConFechaVencidaYLaAudita(): void
    {
        $id = $this->crearNoticia('aprobado', publicarEnMinutos: -5);

        $resumen = $this->programador->ejecutar();

        self::assertSame(1, $resumen['publicadas']);
        self::assertSame('publicado', $this->estadoDe($id));

        $auditoria = self::$conexion->fetchAssociative(
            'SELECT * FROM auditoria ORDER BY id DESC LIMIT 1',
        );
        self::assertNotFalse($auditoria);
        self::assertSame('publicar', $auditoria['accion']);
        self::assertSame('noticias', $auditoria['entidad']);
        self::assertSame($id, (int) $auditoria['entidad_id']);
    }

    public function testNoPublicaLoQueAunNoLlegaASuFecha(): void
    {
        $id = $this->crearNoticia('aprobado', publicarEnMinutos: 120);

        $resumen = $this->programador->ejecutar();

        self::assertSame(0, $resumen['publicadas']);
        self::assertSame('aprobado', $this->estadoDe($id));
    }

    /**
     * Programar no salta el flujo: un borrador con fecha vencida se queda
     * quieto hasta que alguien lo apruebe.
     */
    public function testNoPublicaUnBorradorAunqueLaFechaVenza(): void
    {
        $id = $this->crearNoticia('borrador', publicarEnMinutos: -1440);

        $this->programador->ejecutar();

        self::assertSame('borrador', $this->estadoDe($id));
    }

    public function testDespublicaLaVencidaYLimpiaLaFecha(): void
    {
        $id = $this->crearNoticia('publicado', despublicarEnMinutos: -1);

        $resumen = $this->programador->ejecutar();

        self::assertSame(1, $resumen['despublicadas']);
        self::assertSame('borrador', $this->estadoDe($id));

        $fila = self::$conexion->fetchAssociative('SELECT despublicar_en FROM noticias WHERE id = ?', [$id]);
        self::assertNotFalse($fila);
        self::assertNull($fila['despublicar_en']);
    }

    public function testIgnoraLoEliminadoLogicamente(): void
    {
        $id = $this->crearNoticia('aprobado', publicarEnMinutos: -5, eliminada: true);

        $resumen = $this->programador->ejecutar();

        self::assertSame(0, $resumen['publicadas']);
        self::assertSame('aprobado', $this->estadoDe($id));
    }

    // --- soporte ---

    /**
     * Las fechas se calculan con el reloj de MySQL (NOW() + INTERVAL), que es
     * el mismo que usa el servicio: la zona horaria del PHP de las pruebas no
     * participa.
     */
    private function crearNoticia(
        string $estado,
        ?int $publicarEnMinutos = null,
        ?int $despublicarEnMinutos = null,
        bool $eliminada = false,
    ): int {
        self::$conexion->executeStatement(
            'INSERT INTO noticias (slug, titulo, resumen, contenido, estado_aprobacion, publicar_en, despublicar_en, eliminado_en)
             VALUES (?, ?, ?, ?, ?,
                     IF(? IS NULL, NULL, NOW() + INTERVAL ? MINUTE),
                     IF(? IS NULL, NULL, NOW() + INTERVAL ? MINUTE),
                     IF(?, NOW(), NULL))',
            [
                'prueba-' . bin2hex(random_bytes(6)),
                'Noticia de prueba',
                'Resumen',
                '<p>Contenido</p>',
                $estado,
                $publicarEnMinutos, $publicarEnMinutos ?? 0,
                $despublicarEnMinutos, $despublicarEnMinutos ?? 0,
                (int) $eliminada,
            ],
        );

        return (int) self::$conexion->lastInsertId();
    }

    private function estadoDe(int $id): string
    {
        return (string) self::$conexion->fetchOne(
            'SELECT estado_aprobacion FROM noticias WHERE id = ?',
            [$id],
        );
    }
}

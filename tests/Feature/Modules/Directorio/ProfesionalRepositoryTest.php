<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Directorio;

use App\Modules\Directorio\Repository\EspecialidadRepository;
use App\Modules\Directorio\Repository\ProfesionalRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosDePruebas;

/**
 * Criterio de aceptación de la Fase 2: ningún profesional se publica sin
 * `autoriza_publicacion = 1`. La regla vive en la consulta del repositorio,
 * no en la vista: no hay ruta de código que la esquive.
 */
#[CoversClass(ProfesionalRepository::class)]
#[CoversClass(EspecialidadRepository::class)]
final class ProfesionalRepositoryTest extends TestCase
{
    private static Connection $conexion;
    private ProfesionalRepository $profesionales;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM profesionales');
        self::$conexion->executeStatement('DELETE FROM especialidades');

        $this->profesionales = new ProfesionalRepository(self::$conexion);
    }

    public function testSinAutorizacionNoExisteParaElPublico(): void
    {
        $conAutorizacion = $this->crear('Dra. Ana Rojas', autoriza: true, activo: true);
        $this->crear('Dr. Luis Gómez', autoriza: false, activo: true);
        $this->crear('Dra. Marta Díaz', autoriza: true, activo: false);

        $publicos = $this->profesionales->publicosConEspecialidades();

        self::assertCount(1, $publicos);
        self::assertSame($conAutorizacion, $publicos[0]->id);

        // Tampoco por URL directa.
        self::assertNotNull($this->profesionales->publicoPorSlug('dra-ana-rojas'));
        self::assertNull($this->profesionales->publicoPorSlug('dr-luis-gomez'));
        self::assertNull($this->profesionales->publicoPorSlug('dra-marta-diaz'));
    }

    public function testRetirarLaAutorizacionLoSacaDelPublicoAlInstante(): void
    {
        $id = $this->crear('Dra. Ana Rojas', autoriza: true, activo: true);

        self::assertNotNull($this->profesionales->publicoPorSlug('dra-ana-rojas'));

        self::$conexion->executeStatement('UPDATE profesionales SET autoriza_publicacion = 0 WHERE id = ?', [$id]);

        self::assertNull($this->profesionales->publicoPorSlug('dra-ana-rojas'));
    }

    public function testLosPublicosDeUnaEspecialidadTambienRespetanLaAutorizacion(): void
    {
        $especialidades = new EspecialidadRepository(self::$conexion);
        $especialidadId = $especialidades->crear(['nombre' => 'Pediatría', 'orden' => 0, 'activa' => 1]);

        $autorizado = $this->crear('Dra. Ana Rojas', autoriza: true, activo: true);
        $noAutorizado = $this->crear('Dr. Luis Gómez', autoriza: false, activo: true);
        $this->profesionales->sincronizarEspecialidades($autorizado, [$especialidadId]);
        $this->profesionales->sincronizarEspecialidades($noAutorizado, [$especialidadId]);

        $publicos = $this->profesionales->publicosDeEspecialidad($especialidadId);

        self::assertCount(1, $publicos);
        self::assertSame('Dra. Ana Rojas', $publicos[0]->nombre);
        self::assertSame(['Pediatría'], $publicos[0]->especialidadNombres);
    }

    // --- soporte ---

    private function crear(string $nombre, bool $autoriza, bool $activo): int
    {
        return $this->profesionales->crear([
            'nombre' => $nombre,
            'autoriza_publicacion' => $autoriza ? 1 : 0,
            'activo' => $activo ? 1 : 0,
        ]);
    }
}

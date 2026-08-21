<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Contenido;

use App\Modules\Contenido\Service\VersionadoService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosDePruebas;

#[CoversClass(VersionadoService::class)]
final class VersionadoServiceTest extends TestCase
{
    private static Connection $conexion;
    private VersionadoService $versionado;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM contenido_versiones');
        $this->versionado = new VersionadoService(self::$conexion);
    }

    public function testLasVersionesSonConsecutivasPorEntidad(): void
    {
        $v1 = $this->versionado->registrar('noticias', 7, ['titulo' => 'Primera'], null, 1);
        $v2 = $this->versionado->registrar('noticias', 7, ['titulo' => 'Segunda'], 'corrección', 1);
        // Otra entidad y otro id llevan su propia cuenta.
        $otra = $this->versionado->registrar('paginas', 7, ['titulo' => 'Página'], null, 1);

        self::assertSame(1, $v1);
        self::assertSame(2, $v2);
        self::assertSame(1, $otra);
    }

    public function testElSnapshotVuelveIntactoConTildesYAnidamiento(): void
    {
        $original = [
            'titulo' => 'Jornada de vacunación',
            'contenido' => '<p>Acércate a tu sede más cercana.</p>',
            'metadatos' => ['destacada' => true, 'categoria_id' => null],
        ];

        $this->versionado->registrar('noticias', 3, $original, null, null);

        self::assertSame($original, $this->versionado->snapshot('noticias', 3, 1));
        self::assertNull($this->versionado->snapshot('noticias', 3, 99));
    }

    public function testElHistorialSaleDeLaMasRecienteALaMasVieja(): void
    {
        $this->versionado->registrar('noticias', 5, ['titulo' => 'a'], 'primera redacción', 4);
        $this->versionado->registrar('noticias', 5, ['titulo' => 'b'], null, 4);

        $historial = $this->versionado->historial('noticias', 5);

        self::assertCount(2, $historial);
        self::assertSame(2, $historial[0]['version']);
        self::assertSame(1, $historial[1]['version']);
        self::assertSame('primera redacción', $historial[1]['nota']);
        self::assertSame(4, $historial[0]['creado_por']);
    }
}

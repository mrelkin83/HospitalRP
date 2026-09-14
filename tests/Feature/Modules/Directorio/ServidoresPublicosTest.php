<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Directorio;

use App\Modules\Directorio\Repository\ServidorPublicoRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosDePruebas;

/**
 * Directorio de servidores públicos (Fase 9, bloque A5 — ITA 1.6 y 1.6.1).
 */
#[CoversClass(ServidorPublicoRepository::class)]
final class ServidoresPublicosTest extends TestCase
{
    private static Connection $conexion;
    private ServidorPublicoRepository $servidores;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM servidores_publicos');
        $this->servidores = new ServidorPublicoRepository(self::$conexion);
    }

    /**
     * La Ley 1712 obliga a publicar el cargo y la remuneración, no la vida
     * privada de quien ocupa el cargo. Si alguien añade una columna con dato
     * personal a futuro, esta prueba lo frena.
     */
    public function testLaTablaNoTieneColumnasDeDatosPersonales(): void
    {
        $columnas = array_map(
            static fn (array $c): string => strtolower((string) $c['Field']),
            self::$conexion->fetchAllAssociative('SHOW COLUMNS FROM servidores_publicos'),
        );

        foreach (['documento', 'cedula', 'fecha_nacimiento', 'direccion', 'telefono_personal', 'correo_personal'] as $prohibida) {
            self::assertNotContains($prohibida, $columnas, "La columna «{$prohibida}» no puede existir aquí");
        }
    }

    public function testUnServidorSinPublicarNoAparece(): void
    {
        $this->crear('Ana', 'Pérez', 'Enfermera jefe', publicado: false);

        self::assertSame(0, $this->servidores->totalPublicados());
        self::assertSame([], $this->servidores->publicadosPorDependencia());
    }

    public function testSeAgrupanPorDependenciaYLosSinDependenciaTienenGrupoPropio(): void
    {
        $dependenciaId = $this->crearDependencia('Dirección de Hospitalización');

        $this->crear('Ana', 'Pérez', 'Enfermera jefe', dependenciaId: $dependenciaId);
        $this->crear('Luis', 'Gómez', 'Auxiliar', dependenciaId: $dependenciaId);
        $this->crear('Sara', 'Díaz', 'Contratista');

        $agrupado = $this->servidores->publicadosPorDependencia();

        self::assertSame(3, $this->servidores->totalPublicados());
        self::assertArrayHasKey('Dirección de Hospitalización', $agrupado);
        self::assertArrayHasKey('Sin dependencia asignada', $agrupado);
        self::assertCount(2, $agrupado['Dirección de Hospitalización']);
        // Orden alfabético por apellido dentro de la dependencia.
        self::assertSame('Gómez', $agrupado['Dirección de Hospitalización'][0]['apellidos']);
    }

    public function testDosHomonimosNoChocanPorElSlug(): void
    {
        $this->crear('Ana', 'Pérez', 'Enfermera jefe');
        $this->crear('Ana', 'Pérez', 'Auxiliar de enfermería');

        $slugs = self::$conexion->fetchFirstColumn('SELECT slug FROM servidores_publicos ORDER BY id');

        self::assertSame(['ana-perez', 'ana-perez-2'], $slugs);
    }

    private function crearDependencia(string $nombre): int
    {
        self::$conexion->insert('dependencias', ['nombre' => $nombre]);

        return (int) self::$conexion->lastInsertId();
    }

    private function crear(
        string $nombres,
        string $apellidos,
        string $cargo,
        ?int $dependenciaId = null,
        bool $publicado = true,
    ): int {
        return $this->servidores->crear([
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'cargo' => $cargo,
            'dependencia_id' => $dependenciaId,
            'publicado' => $publicado ? 1 : 0,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Core\Storage\AlmacenArchivos;
use App\Modules\Contenido\Repository\CategoriaRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatosDePruebas;

/**
 * Galería pública y categorías por slug: las dos últimas secciones de PRENSA
 * que el Joomla tenía y el sitio nuevo no (docs/14 §2).
 *
 * La prueba que importa es la primera. La biblioteca de medios del panel
 * guarda TODO lo subido, incluidos los adjuntos que la ciudadanía manda en
 * los trámites. Publicar la galería reutilizando esa consulta habría
 * expuesto documentos privados de personas, y sin que nadie se enterara.
 */
#[CoversClass(AlmacenArchivos::class)]
#[CoversClass(CategoriaRepository::class)]
final class GaleriaPublicaTest extends TestCase
{
    private static Connection $conexion;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement("DELETE FROM archivos WHERE ruta LIKE 'prueba-galeria/%'");
        self::$conexion->executeStatement("DELETE FROM categorias WHERE slug = 'revista-de-prueba'");
    }

    public function testLaGaleriaJamasExponeUnArchivoPrivado(): void
    {
        $this->crearImagen('publica.jpg', publico: true);
        $this->crearImagen('adjunto-de-tramite.jpg', publico: false);

        $nombres = $this->nombresEnGaleria();

        self::assertContains('publica.jpg', $nombres);
        self::assertNotContains('adjunto-de-tramite.jpg', $nombres, 'Se filtró un archivo privado a la galería.');
    }

    public function testSoloEntranImagenes(): void
    {
        $this->crearImagen('foto.jpg', publico: true);
        $this->crearArchivo('informe.pdf', 'application/pdf', publico: true);

        $nombres = $this->nombresEnGaleria();

        self::assertContains('foto.jpg', $nombres);
        self::assertNotContains('informe.pdf', $nombres);
    }

    public function testElTotalCuentaSoloLoPublicable(): void
    {
        $this->crearImagen('a.jpg', publico: true);
        $this->crearImagen('b.jpg', publico: true);
        $this->crearImagen('c.jpg', publico: false);
        $this->crearArchivo('d.pdf', 'application/pdf', publico: true);

        $total = self::almacen()->imagenesPublicas(1, 50)['total'];
        $privadas = (int) self::$conexion->fetchOne(
            "SELECT COUNT(*) FROM archivos WHERE ruta LIKE 'prueba-galeria/%' AND publico = 0",
        );

        self::assertSame(1, $privadas);
        self::assertGreaterThanOrEqual(2, $total);
    }

    /** El enlace de menú de la revista usa slug porque el id no es estable. */
    public function testLaCategoriaSeResuelvePorSlugYNoDevuelveLaDeOtroTipo(): void
    {
        self::$conexion->insert('categorias', [
            'tipo' => 'documento',
            'nombre' => 'Revista de prueba',
            'slug' => 'revista-de-prueba',
            'orden' => 0,
        ]);

        $repositorio = new CategoriaRepository(self::$conexion);

        self::assertNotNull($repositorio->porSlug('documento', 'revista-de-prueba'));
        self::assertNull($repositorio->porSlug('noticia', 'revista-de-prueba'));
        self::assertNull($repositorio->porSlug('documento', 'no-existe'));
    }

    // --- soporte ---

    private static function almacen(): AlmacenArchivos
    {
        return new AlmacenArchivos(self::$conexion, sys_get_temp_dir());
    }

    /** @return list<string> */
    private function nombresEnGaleria(): array
    {
        return array_map(
            static fn ($a): string => $a->nombreOriginal,
            self::almacen()->imagenesPublicas(1, 200)['imagenes'],
        );
    }

    private function crearImagen(string $nombre, bool $publico): void
    {
        $this->crearArchivo($nombre, 'image/jpeg', $publico);
    }

    private function crearArchivo(string $nombre, string $mime, bool $publico): void
    {
        self::$conexion->insert('archivos', [
            'nombre_original' => $nombre,
            'ruta' => 'prueba-galeria/' . $nombre,
            'mime' => $mime,
            'extension' => pathinfo($nombre, PATHINFO_EXTENSION),
            'tamano' => 1024,
            'hash_sha256' => hash('sha256', $nombre . random_bytes(4)),
            'publico' => $publico ? 1 : 0,
        ]);
    }
}

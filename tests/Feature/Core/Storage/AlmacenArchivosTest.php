<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Storage;

use App\Core\Storage\AlmacenArchivos;
use App\Core\Storage\Archivo;
use App\Core\Storage\ArchivoInvalidoException;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Tests\Support\BaseDatosDePruebas;

/**
 * Criterio de aceptación del paso 8: un `.php` renombrado a `.jpg` se
 * rechaza. La validación lee el contenido real, nunca la extensión.
 */
#[CoversClass(AlmacenArchivos::class)]
#[CoversClass(Archivo::class)]
#[CoversClass(ArchivoInvalidoException::class)]
final class AlmacenArchivosTest extends TestCase
{
    private static Connection $conexion;
    private string $directorioBase;
    private string $directorioEntrada;
    private AlmacenArchivos $almacen;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM archivos');

        $this->directorioBase = sys_get_temp_dir() . '/hrpl_uploads_' . bin2hex(random_bytes(6));
        $this->directorioEntrada = sys_get_temp_dir() . '/hrpl_entrada_' . bin2hex(random_bytes(6));
        mkdir($this->directorioEntrada, 0755, true);

        $this->almacen = new AlmacenArchivos(self::$conexion, $this->directorioBase);
    }

    public function testGuardaUnaImagenValidaFueraDelDocumentRoot(): void
    {
        $archivo = $this->almacen->guardar($this->subidaPng('foto navidad.png'), publico: true, creadoPor: null);

        self::assertSame('image/png', $archivo->mime);
        self::assertSame('png', $archivo->extension);
        self::assertSame('foto navidad.png', $archivo->nombreOriginal);
        self::assertSame(2, $archivo->ancho);
        self::assertSame(2, $archivo->alto);
        self::assertTrue($archivo->publico);

        $rutaFisica = $this->almacen->rutaAbsoluta($archivo);
        self::assertFileExists($rutaFisica);
        // El nombre en disco es aleatorio: el original no aparece en la ruta.
        self::assertStringNotContainsString('navidad', $rutaFisica);
        self::assertSame(hash_file('sha256', $rutaFisica), $archivo->hashSha256);
    }

    public function testRechazaUnPhpRenombradoAJpg(): void
    {
        $ruta = $this->directorioEntrada . '/malicioso.jpg';
        file_put_contents($ruta, "<?php system('cualquier cosa');");
        $subida = new UploadedFile($ruta, 'malicioso.jpg', 'image/jpeg', null, true);

        try {
            $this->almacen->guardar($subida, publico: true, creadoPor: null);
            self::fail('Debió rechazar el archivo.');
        } catch (ArchivoInvalidoException $e) {
            self::assertStringContainsString('no está permitido', $e->getMessage());
        }

        $this->asegurarQueNadaSeGuardo();
    }

    public function testRechazaUnaExtensionQueNoCoincideConElContenido(): void
    {
        // Un PNG de verdad, pero renombrado a .pdf.
        $subida = $this->subidaPng('informe.pdf');

        try {
            $this->almacen->guardar($subida, publico: true, creadoPor: null);
            self::fail('Debió rechazar el archivo.');
        } catch (ArchivoInvalidoException $e) {
            self::assertStringContainsString('no corresponde al contenido real', $e->getMessage());
        }

        $this->asegurarQueNadaSeGuardo();
    }

    public function testRechazaUnArchivoVacio(): void
    {
        $ruta = $this->directorioEntrada . '/vacio.png';
        touch($ruta);
        $subida = new UploadedFile($ruta, 'vacio.png', 'image/png', null, true);

        $this->expectException(ArchivoInvalidoException::class);

        $this->almacen->guardar($subida, publico: true, creadoPor: null);
    }

    // --- soporte ---

    private function subidaPng(string $nombreOriginal): UploadedFile
    {
        $ruta = $this->directorioEntrada . '/' . bin2hex(random_bytes(4)) . '.png';
        $imagen = imagecreatetruecolor(2, 2);
        imagepng($imagen, $ruta);
        imagedestroy($imagen);

        return new UploadedFile($ruta, $nombreOriginal, 'image/png', null, true);
    }

    private function asegurarQueNadaSeGuardo(): void
    {
        self::assertSame(0, (int) self::$conexion->fetchOne('SELECT COUNT(*) FROM archivos'));
        self::assertFalse(is_dir($this->directorioBase), 'No debió crearse nada en el almacén.');
    }
}

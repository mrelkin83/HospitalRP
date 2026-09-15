<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Contenido;

use App\Core\Audit\AuditoriaService;
use App\Core\Storage\AlmacenArchivos;
use App\Modules\Contenido\Command\SembrarImagenesCommand;
use App\Modules\Contenido\Service\GeneradorPortadas;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Support\BaseDatosDePruebas;

#[CoversClass(SembrarImagenesCommand::class)]
final class SembrarImagenesTest extends TestCase
{
    private static Connection $conexion;
    private string $almacenamiento;
    private CommandTester $consola;
    private int $archivosAlEmpezar;

    public static function setUpBeforeClass(): void
    {
        self::$conexion = BaseDatosDePruebas::conexionMigrada();
    }

    protected function setUp(): void
    {
        self::$conexion->executeStatement('DELETE FROM noticias');
        self::$conexion->executeStatement('DELETE FROM campanas');
        // `archivos` NO se vacía: `documentos` la referencia con ON DELETE
        // RESTRICT y otras pruebas dejan filas ahí. Lo que este caso mide son
        // las que aparecen de más, no el total.
        $this->archivosAlEmpezar = $this->cuantosArchivos();

        $this->almacenamiento = sys_get_temp_dir() . '/medios-' . bin2hex(random_bytes(6));
        mkdir($this->almacenamiento);

        $this->consola = new CommandTester(new SembrarImagenesCommand(
            self::$conexion,
            new AuditoriaService(self::$conexion),
            new AlmacenArchivos(self::$conexion, $this->almacenamiento),
            new GeneradorPortadas(),
        ));
    }

    protected function tearDown(): void
    {
        $this->borrarArbol($this->almacenamiento);
    }

    public function testPoneImagenALoQueNoLaTieneYEsUnJpegDeVerdad(): void
    {
        $noticiaId = $this->crearNoticia('jornada-de-donacion-de-sangre', 'Jornada de donación de sangre');

        $this->consola->execute([]);

        $imagenId = self::$conexion->fetchOne('SELECT imagen_id FROM noticias WHERE id = ?', [$noticiaId]);
        self::assertNotNull($imagenId);

        $archivo = self::$conexion->fetchAssociative('SELECT * FROM archivos WHERE id = ?', [$imagenId]);
        self::assertIsArray($archivo);
        self::assertSame('image/jpeg', $archivo['mime']);
        self::assertSame(1200, (int) $archivo['ancho']);
        self::assertSame(630, (int) $archivo['alto']);
        // Decorativa: la plantilla vuelca `texto_alt` en `alt`, y el titular
        // repetido por el lector de pantalla no aporta nada.
        self::assertNull($archivo['texto_alt']);
        self::assertSame(1, (int) $archivo['publico']);

        $ruta = $this->almacenamiento . '/' . $archivo['ruta'];
        self::assertFileExists($ruta, 'La fila de archivos apunta a un archivo que no está en disco.');
        self::assertSame(hash_file('sha256', $ruta), $archivo['hash_sha256']);
    }

    public function testNoPisaLaImagenQueYaTieneUnaPieza(): void
    {
        $this->crearNoticia('jornada-de-donacion-de-sangre', 'Jornada de donación de sangre');
        $this->consola->execute([]);

        $antes = self::$conexion->fetchOne('SELECT imagen_id FROM noticias');
        $archivosAntes = $this->cuantosArchivos();

        $this->consola->execute([]);

        self::assertSame($antes, self::$conexion->fetchOne('SELECT imagen_id FROM noticias'));
        self::assertSame(
            $archivosAntes,
            $this->cuantosArchivos(),
            'La segunda pasada generó archivos de más: la siembra no es idempotente.',
        );
        self::assertStringContainsString('No se tocó nada', $this->consola->getDisplay());
    }

    public function testElContenidoBorradoNoRecibePortada(): void
    {
        $id = $this->crearNoticia('nota-retirada', 'Nota retirada');
        self::$conexion->update('noticias', ['eliminado_en' => '2026-01-01 00:00:00'], ['id' => $id]);

        $this->consola->execute([]);

        self::assertNull(self::$conexion->fetchOne('SELECT imagen_id FROM noticias WHERE id = ?', [$id]));
        self::assertSame($this->archivosAlEmpezar, $this->cuantosArchivos());
    }

    /**
     * El reparto por palabras clave es lo que atenderá lo que el hospital
     * escriba de aquí en adelante, cuando el slug ya no esté en la tabla fija.
     */
    public function testUnaPiezaNuevaRecibeLaPortadaQueSuTituloSugiere(): void
    {
        $convocatoria = $this->crearCampana('proceso-2027-a', 'Convocatoria de talento humano 2027');
        $vacunacion = $this->crearCampana('brigada-2027', 'Brigada de vacunación en zona rural');

        $this->consola->execute([]);

        self::assertSame('portada-empleo.jpg', $this->nombreDeLaPortada('campanas', $convocatoria));
        self::assertSame('portada-vacuna.jpg', $this->nombreDeLaPortada('campanas', $vacunacion));
    }

    public function testUnTituloSinPistasRecibeLaPortadaPorDefecto(): void
    {
        $id = $this->crearCampana('sin-pistas', 'Información general para la ciudadanía');

        $this->consola->execute([]);

        self::assertSame('portada-quirofano.jpg', $this->nombreDeLaPortada('campanas', $id));
    }

    // --- soporte ---

    private function cuantosArchivos(): int
    {
        return (int) self::$conexion->fetchOne('SELECT COUNT(*) FROM archivos');
    }

    private function nombreDeLaPortada(string $tabla, int $id): string
    {
        return (string) self::$conexion->fetchOne(
            sprintf('SELECT a.nombre_original FROM %s t JOIN archivos a ON a.id = t.imagen_id WHERE t.id = ?', $tabla),
            [$id],
        );
    }

    private function crearNoticia(string $slug, string $titulo): int
    {
        self::$conexion->insert('noticias', [
            'slug' => $slug,
            'titulo' => $titulo,
            'resumen' => 'Resumen de prueba.',
            'contenido' => '<p>Contenido de prueba.</p>',
            'estado_aprobacion' => 'publicado',
        ]);

        return (int) self::$conexion->lastInsertId();
    }

    private function crearCampana(string $slug, string $titulo): int
    {
        self::$conexion->insert('campanas', [
            'slug' => $slug,
            'titulo' => $titulo,
            'resumen' => 'Resumen de prueba.',
            'estado_aprobacion' => 'publicado',
        ]);

        return (int) self::$conexion->lastInsertId();
    }

    private function borrarArbol(string $directorio): void
    {
        if (!is_dir($directorio)) {
            return;
        }

        foreach (scandir($directorio) ?: [] as $entrada) {
            if ($entrada === '.' || $entrada === '..') {
                continue;
            }

            $ruta = $directorio . '/' . $entrada;
            is_dir($ruta) ? $this->borrarArbol($ruta) : unlink($ruta);
        }

        rmdir($directorio);
    }
}

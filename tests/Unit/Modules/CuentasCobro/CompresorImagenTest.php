<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\CuentasCobro;

use App\Modules\CuentasCobro\Service\CompresorImagen;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * La compresión de las evidencias (§fase 6, B5).
 *
 * Con catorce obligaciones por cuenta y 1.500 contratistas al mes, la
 * diferencia entre guardar la foto tal como sale del celular y encogerla
 * son unos veinticinco gigas mensuales.
 */
#[CoversClass(CompresorImagen::class)]
final class CompresorImagenTest extends TestCase
{
    private CompresorImagen $compresor;
    private string $archivo = '';

    protected function setUp(): void
    {
        if (!extension_loaded('gd')) {
            self::markTestSkipped('GD no está disponible en esta máquina.');
        }

        $this->compresor = new CompresorImagen();
    }

    protected function tearDown(): void
    {
        if ($this->archivo !== '' && is_file($this->archivo)) {
            unlink($this->archivo);
        }
    }

    /** Una foto de celular: 3000×2000, se guarda mucho más liviana. */
    public function test_encoge_la_foto_de_un_celular(): void
    {
        $original = $this->fotoDePrueba(3000, 2000);
        $pesoOriginal = (int) filesize($this->archivo);

        $comprimida = $this->compresor->comprimir($this->archivo, 'image/jpeg');

        self::assertNotNull($comprimida, 'Una foto grande siempre debe poder encogerse.');
        self::assertLessThan($pesoOriginal, strlen($comprimida));

        $dimensiones = getimagesizefromstring($comprimida);
        self::assertNotFalse($dimensiones);
        self::assertSame(1600, $dimensiones[0], 'El lado largo debe quedar en 1600 px.');
        self::assertLessThanOrEqual(1600, $dimensiones[1]);
        self::assertSame('image/jpeg', $dimensiones['mime']);
        self::assertNotSame('', $original);
    }

    /** Un PDF no se toca: solo se comprimen imágenes. */
    public function test_no_toca_lo_que_no_es_imagen(): void
    {
        $this->fotoDePrueba(100, 100);

        self::assertNull($this->compresor->comprimir($this->archivo, 'application/pdf'));
    }

    /**
     * Un archivo que GD no sepa leer se deja pasar tal cual: la
     * radicación no puede caerse por una cámara que guarda un JPEG raro.
     */
    public function test_un_archivo_ilegible_no_lanza(): void
    {
        $this->archivo = (string) tempnam(sys_get_temp_dir(), 'cc');
        file_put_contents($this->archivo, 'esto no es una imagen');

        self::assertNull($this->compresor->comprimir($this->archivo, 'image/jpeg'));
    }

    private function fotoDePrueba(int $ancho, int $alto): string
    {
        $imagen = imagecreatetruecolor($ancho, $alto);

        // Ruido: una imagen de un solo color se comprime a nada y la
        // prueba no mediría lo que dice medir.
        for ($x = 0; $x < $ancho; $x += 4) {
            for ($y = 0; $y < $alto; $y += 4) {
                $color = (int) imagecolorallocate($imagen, ($x * 7) % 255, ($y * 13) % 255, ($x + $y) % 255);
                imagefilledrectangle($imagen, $x, $y, $x + 3, $y + 3, $color);
            }
        }

        $this->archivo = (string) tempnam(sys_get_temp_dir(), 'cc');
        imagejpeg($imagen, $this->archivo, 95);
        imagedestroy($imagen);

        return $this->archivo;
    }
}

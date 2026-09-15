<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Contenido;

use App\Modules\Contenido\Command\SembrarImagenesCommand;
use App\Modules\Contenido\Service\GeneradorPortadas;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(GeneradorPortadas::class)]
final class GeneradorPortadasTest extends TestCase
{
    private string $directorio;

    protected function setUp(): void
    {
        $this->directorio = sys_get_temp_dir() . '/portadas-' . bin2hex(random_bytes(6));
        mkdir($this->directorio);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directorio . '/*') ?: [] as $archivo) {
            unlink($archivo);
        }

        rmdir($this->directorio);
    }

    /** @return iterable<string, array{string}> */
    public static function motivos(): iterable
    {
        foreach (GeneradorPortadas::motivos() as $motivo) {
            yield $motivo => [$motivo];
        }
    }

    #[DataProvider('motivos')]
    public function testCadaMotivoProduceUnJpegDe1200Por630(string $motivo): void
    {
        $destino = $this->directorio . '/' . $motivo . '.jpg';

        (new GeneradorPortadas())->generar($motivo, $destino);

        self::assertFileExists($destino);

        $info = getimagesize($destino);
        self::assertIsArray($info, 'El archivo no es una imagen que GD sepa leer.');
        self::assertSame([1200, 630], [$info[0], $info[1]]);
        self::assertSame('image/jpeg', $info['mime']);
    }

    /**
     * Sembrar dos veces no debe llenar la biblioteca de medios de variantes de
     * la misma portada, así que el dibujo no puede llevar azar dentro.
     */
    public function testElMismoMotivoDaSiempreElMismoArchivo(): void
    {
        $generador = new GeneradorPortadas();
        $generador->generar('corazon', $this->directorio . '/a.jpg');
        $generador->generar('corazon', $this->directorio . '/b.jpg');

        self::assertSame(
            hash_file('sha256', $this->directorio . '/a.jpg'),
            hash_file('sha256', $this->directorio . '/b.jpg'),
        );
    }

    public function testDosMotivosDistintosNoDanLaMismaImagen(): void
    {
        $generador = new GeneradorPortadas();
        $generador->generar('corazon', $this->directorio . '/corazon.jpg');
        $generador->generar('encuesta', $this->directorio . '/encuesta.jpg');

        self::assertNotSame(
            hash_file('sha256', $this->directorio . '/corazon.jpg'),
            hash_file('sha256', $this->directorio . '/encuesta.jpg'),
        );
    }

    public function testUnMotivoDesconocidoFallaEnVozAlta(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Motivo de portada desconocido');

        (new GeneradorPortadas())->generar('helicoptero', $this->directorio . '/x.jpg');
    }

    /**
     * El comando reparte motivos con dos tablas escritas a mano —doce slugs y
     * treinta y una palabras clave—. Un motivo mal tecleado ahí no se nota al
     * leer: revienta en mitad de la siembra, cuando ya hay filas actualizadas.
     * Esta prueba lee las constantes del comando y comprueba que todo lo que
     * prometen existe de verdad.
     */
    public function testElComandoSoloReparteMotivosQueExisten(): void
    {
        $constantes = (new ReflectionClass(SembrarImagenesCommand::class))->getConstants();

        /** @var array<string, string> $porSlug */
        $porSlug = $constantes['POR_SLUG'];
        /** @var array<string, string> $porPalabra */
        $porPalabra = $constantes['POR_PALABRA'];
        /** @var string $porDefecto */
        $porDefecto = $constantes['MOTIVO_POR_DEFECTO'];

        foreach ([...array_values($porSlug), ...array_values($porPalabra), $porDefecto] as $motivo) {
            self::assertTrue(
                GeneradorPortadas::existeMotivo($motivo),
                sprintf('El comando reparte el motivo «%s», que el generador no sabe dibujar.', $motivo),
            );
        }
    }
}

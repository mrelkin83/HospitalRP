<?php

declare(strict_types=1);

namespace Tests\Unit\Diseno;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Integridad estructural de las plantillas.
 *
 * Nace de un fallo que se repitió cuatro veces mientras se rediseñaban las
 * vistas: la cabecera PHP del archivo quedaba duplicada. En `contacto.php` y
 * `urgencias.php` pasaba desapercibido porque solo reasignan variables, pero
 * en `noticias/listado.php` había un `use` y la página reventó con «Cannot
 * use App\Core\Support\Fechas because the name is already in use». El error
 * solo aparecía al abrir esa página concreta.
 *
 * Las comprobaciones son de texto, sin base de datos ni navegador, así que
 * cuestan milisegundos y cubren todas las plantillas de una vez.
 */
final class VistasSanasTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function plantillas(): iterable
    {
        $raiz = dirname(__DIR__, 3) . '/resources/views';
        $iterador = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($raiz));

        /** @var \SplFileInfo $archivo */
        foreach ($iterador as $archivo) {
            if ($archivo->isFile() && $archivo->getExtension() === 'php') {
                $relativa = str_replace('\\', '/', substr($archivo->getPathname(), strlen($raiz) + 1));

                yield $relativa => [$archivo->getPathname()];
            }
        }
    }

    /** Un `use` repetido es un error fatal en cuanto se abre la página. */
    #[DataProvider('plantillas')]
    public function testNingunaPlantillaRepiteUnImport(string $ruta): void
    {
        preg_match_all('/^use\s+([^;]+);/m', (string) file_get_contents($ruta), $m);

        $repetidos = array_keys(array_filter(array_count_values($m[1]), static fn (int $n): bool => $n > 1));

        self::assertSame([], $repetidos, sprintf(
            'La plantilla repite el import de %s: es un error fatal al renderizarla.',
            implode(', ', $repetidos),
        ));
    }

    /**
     * Dos bloques de cabecera idénticos seguidos significan que una versión
     * nueva se pegó encima de la vieja sin borrarla.
     */
    #[DataProvider('plantillas')]
    public function testNingunaPlantillaDuplicaSuCabecera(string $ruta): void
    {
        $contenido = (string) file_get_contents($ruta);

        if (preg_match('/^(<\?php\R.*?\R\?>\R)(<\?php\R.*?\R\?>\R)/s', $contenido, $m) !== 1) {
            self::assertTrue(true, 'Sin dos bloques de cabecera consecutivos.');

            return;
        }

        self::assertNotSame($m[1], $m[2], 'La cabecera del archivo está duplicada literalmente.');
    }

    /** Un `<h1>` por página: el segundo rompe el esquema de encabezados. */
    #[DataProvider('plantillas')]
    public function testNingunaPlantillaTraeDosTitulosH1(string $ruta): void
    {
        $encontrados = preg_match_all('/<h1[\s>]/i', (string) file_get_contents($ruta));

        self::assertLessThanOrEqual(1, $encontrados, sprintf(
            'La plantilla tiene %d elementos <h1>; debe haber uno solo por página.',
            $encontrados,
        ));
    }
}

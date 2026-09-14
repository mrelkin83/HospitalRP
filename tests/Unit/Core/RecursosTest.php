<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Support\Recursos;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Huella de caché de los estáticos.
 *
 * El 2026-09-14 se cambió la paleta del sitio y el visitante siguió viendo el
 * diseño viejo: el service worker cachea `/build/` con caché-primero y la URL
 * de la hoja no llevaba versión, así que la copia vieja nunca caducaba.
 *
 * Estas pruebas fijan las dos mitades del arreglo: que la URL lleve huella, y
 * que ninguna plantilla vuelva a enlazar el estático sin ella.
 */
#[CoversClass(Recursos::class)]
final class RecursosTest extends TestCase
{
    protected function setUp(): void
    {
        Recursos::olvidar();
    }

    public function testLaUrlDeUnEstaticoExistenteLlevaHuella(): void
    {
        $url = Recursos::url('/manifest.webmanifest');

        self::assertMatchesRegularExpression(
            '#^/manifest\.webmanifest\?v=[0-9a-f]+$#',
            $url,
            'El estático debe salir con huella de caché.',
        );
    }

    /** Un archivo sin compilar no puede tumbar la página. */
    public function testUnEstaticoInexistenteDevuelveLaRutaTalCual(): void
    {
        self::assertSame('/build/no-existe.css', Recursos::url('/build/no-existe.css'));
    }

    public function testDosArchivosDistintosNoCompartenHuella(): void
    {
        $uno = Recursos::url('/manifest.webmanifest');
        $otro = Recursos::url('/sw.js');

        self::assertNotSame($uno, $otro);
    }

    /**
     * La prueba que de verdad importa: si alguien vuelve a escribir
     * `href="/build/app.css"` a pelo en una plantilla, el service worker se
     * quedará sirviendo esa copia para siempre.
     *
     * @return iterable<string, array{string}>
     */
    public static function plantillasQueEnlazanEstaticos(): iterable
    {
        yield 'layout público' => ['resources/views/publico/base.php'];
        yield 'layout del panel' => ['resources/views/admin/base.php'];
        yield 'ingreso al panel' => ['resources/views/admin/login.php'];
    }

    #[DataProvider('plantillasQueEnlazanEstaticos')]
    public function testNingunaPlantillaEnlazaElCssSinHuella(string $ruta): void
    {
        $contenido = (string) file_get_contents(dirname(__DIR__, 3) . '/' . $ruta);

        self::assertStringNotContainsString(
            'href="/build/app.css"',
            $contenido,
            sprintf(
                'En %s la hoja se enlaza sin huella de caché. El service worker la ' .
                'cachea con caché-primero y el visitante se quedaría con la versión ' .
                'vieja. Usa Recursos::url().',
                $ruta,
            ),
        );
    }

    /**
     * El caché-primero del service worker solo es seguro mientras las URL
     * lleven huella. Si se quita una, hay que quitar la otra.
     */
    public function testElServiceWorkerDocumentaPorQueCacheaPrimero(): void
    {
        $sw = (string) file_get_contents(dirname(__DIR__, 3) . '/public/sw.js');

        self::assertStringContainsString('huella', $sw);
        self::assertMatchesRegularExpression("/var VERSION = 'hrpl-v\d+';/", $sw);
    }
}

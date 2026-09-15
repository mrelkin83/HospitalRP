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

    /**
     * El sitio público sirve `style-src 'self'` sin `'unsafe-inline'`, así que
     * el navegador DESCARTA cualquier `style="…"`. No es un aviso de consola:
     * el estilo no se aplica. Las dos barras del héroe llegaron a estar
     * pintadas al 100 % junto a un texto que decía 98,4 %.
     *
     * Para un ancho o una demora que salen de un dato, la salida son clases
     * (`.barra-N` de app.css, o un valor arbitrario de Tailwind cuando el
     * literal está en el código). El panel sí admite estilo en línea —su CSP
     * es otra—, por eso solo se revisan las vistas públicas.
     */
    #[DataProvider('plantillas')]
    public function testNingunaVistaPublicaUsaEstiloEnLinea(string $ruta): void
    {
        if (!self::esVistaPublica($ruta)) {
            self::assertTrue(true, 'El panel tiene su propia CSP y sí admite estilo en línea.');

            return;
        }

        self::assertDoesNotMatchRegularExpression(
            '/<[^>]*\sstyle\s*=\s*"/i',
            (string) file_get_contents($ruta),
            basename($ruta) . ' usa style="…", que la CSP del sitio público descarta. Usa una clase.',
        );
    }

    /**
     * Directivas de Alpine solo si Alpine está de verdad cargado.
     *
     * Alpine SÍ está en el stack aprobado (CLAUDE.md §3 y §6, «solo para
     * interactividad puntual»); lo que no puede pasar es usarlo sin haberlo
     * instalado, porque entonces no falla de forma ruidosa: se queda callado.
     * `x-text` deja el elemento VACÍO y `x-show` no esconde nada. Eso fue lo
     * que pasó en la portada el 2026-09-14 —campañas sin texto, las dos
     * pestañas visibles a la vez y el chat abierto en todas las páginas— y no
     * se notó porque la consola no dice una palabra.
     *
     * Por eso la prueba no prohíbe la herramienta: exige coherencia. El día
     * que se añada Alpine al cascarón, esta prueba deja de estorbar sola.
     */
    #[DataProvider('plantillas')]
    public function testNingunaPlantillaUsaAlpineSinQueEsteCargado(string $ruta): void
    {
        if (self::alpineEstaCargado()) {
            self::assertTrue(true, 'Alpine está en el cascarón: sus directivas funcionan.');

            return;
        }

        // Se buscan como atributo (`<div x-data=`) y no como texto suelto, para
        // que un comentario que explique por qué se retiraron no dispare.
        self::assertDoesNotMatchRegularExpression(
            '/<[^>]*\s(x-(data|show|text|for|transition|model|bind|if)|@click)\b/i',
            (string) file_get_contents($ruta),
            basename($ruta) . ' usa directivas de Alpine, pero Alpine no se carga en ninguna parte: '
                . 'el bloque queda mudo. O se instala, o se renderiza en el servidor.',
        );
    }

    private static function alpineEstaCargado(): bool
    {
        $raiz = dirname(__DIR__, 3);

        foreach (['/resources/views/publico/base.php', '/public/js'] as $donde) {
            $ruta = $raiz . $donde;

            if (is_file($ruta) && stripos((string) file_get_contents($ruta), 'alpine') !== false) {
                return true;
            }

            if (is_dir($ruta)) {
                foreach (glob($ruta . '/*.js') ?: [] as $script) {
                    if (stripos((string) file_get_contents($script), 'alpine') !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private static function esVistaPublica(string $ruta): bool
    {
        return str_contains(str_replace('\\', '/', $ruta), '/views/publico/');
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

    /**
     * Los desplegables de una barra de navegación se cierran entre sí.
     *
     * Dos `<details>` sueltos dentro de un `<nav>` se quedan abiertos a la vez:
     * abrir «Programas» dejaba «El Hospital» desplegado debajo, y con siete
     * raíces la barra acababa siendo una cortina de submenús superpuestos. Lo
     * que los hace excluyentes es el atributo `name` compartido —el acordeón
     * exclusivo de HTML—, no una clase ni un script.
     *
     * La comprobación es de texto y solo mira dentro de los `<nav>`: en el
     * panel hay muchos otros `<details>` (filtros, detalle de una fila) que sí
     * deben poder estar abiertos a la vez.
     */
    #[DataProvider('plantillas')]
    public function testLosDesplegablesDeNavegacionSeExcluyenEntreSi(string $ruta): void
    {
        $sinExcluir = [];

        preg_match_all('/<nav\b.*?<\/nav>/s', self::htmlLiteral($ruta), $navs);

        foreach ($navs[0] as $nav) {
            preg_match_all('/<details\b[^>]*>/i', $nav, $etiquetas);

            foreach ($etiquetas[0] as $etiqueta) {
                if (preg_match('/\sname\s*=\s*"/i', $etiqueta) !== 1) {
                    $sinExcluir[] = $etiqueta;
                }
            }
        }

        self::assertSame([], $sinExcluir, sprintf(
            '%s tiene desplegables de navegación sin `name`, así que no se cierran al abrir otro.',
            basename($ruta),
        ));
    }

    /**
     * Solo el HTML literal de la plantilla, sin una línea de PHP.
     *
     * Lo separa `token_get_all`, el propio analizador del lenguaje, y no una
     * expresión regular. El primer intento fue borrar los comentarios con
     * regex y salió mal dos veces: primero el comentario que explica el
     * desplegable se delataba a sí mismo, porque menciona «<details>»; después
     * el patrón que lo arreglaba se tragaba desde la cabecera del archivo
     * hasta el comentario siguiente, `<nav>` incluido, y la prueba pasaba
     * SIEMPRE, incluso con el defecto puesto a mano.
     */
    private static function htmlLiteral(string $ruta): string
    {
        $html = '';

        foreach (token_get_all((string) file_get_contents($ruta)) as $token) {
            if (is_array($token) && $token[0] === T_INLINE_HTML) {
                $html .= $token[1];
            }
        }

        return $html;
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

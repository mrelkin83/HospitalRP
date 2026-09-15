<?php

declare(strict_types=1);

namespace Tests\Unit\Diseno;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Criterio de aceptación del paso 9 (Fase 0): todo par texto/fondo del
 * sistema de diseño cumple WCAG 2.1 AA (≥ 4.5:1).
 *
 * Los pares se declaran aquí con sus valores y la prueba verifica dos cosas:
 * que el contraste alcanza, y que el color sigue existiendo en
 * resources/css/app.css. Si alguien cambia la paleta, esta prueba lo obliga
 * a volver a verificar el contraste, no a suponerlo.
 */
final class ContrasteTest extends TestCase
{
    private const float MINIMO_AA = 4.5;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function paresDeTextoYFondo(): iterable
    {
        // --- Tema claro ---
        yield 'texto sobre fondo' => ['#1F2937', '#FFFFFF'];
        yield 'texto suave sobre fondo' => ['#4B5563', '#FFFFFF'];
        yield 'titular sobre fondo' => ['#0A192F', '#FFFFFF'];
        yield 'enlace sobre fondo' => ['#225A96', '#FFFFFF'];
        yield 'texto del botón primario' => ['#FFFFFF', '#2B6CB0'];
        yield 'texto de error sobre fondo' => ['#B3251E', '#FFFFFF'];
        yield 'alerta de éxito' => ['#14532D', '#E7F5EE'];
        yield 'alerta de aviso' => ['#6B3308', '#FDF3E3'];
        yield 'alerta de error' => ['#7F1D18', '#FDEBEA'];
        yield 'texto sobre superficie' => ['#1F2937', '#F5F7FB'];

        // --- Tema oscuro ---
        yield 'oscuro: texto sobre fondo' => ['#E7ECF5', '#090E1A'];
        yield 'oscuro: texto suave sobre fondo' => ['#A9B4C6', '#090E1A'];
        yield 'oscuro: enlace sobre fondo' => ['#8FC3F5', '#090E1A'];
        yield 'oscuro: texto del botón primario' => ['#FFFFFF', '#225A96'];
        yield 'oscuro: texto sobre superficie' => ['#E7ECF5', '#121A2E'];

        // --- Alto contraste ---
        yield 'alto contraste: texto' => ['#000000', '#FFFFFF'];
        yield 'alto contraste: enlace' => ['#12294A', '#FFFFFF'];
        yield 'alto contraste oscuro: texto' => ['#FFFFFF', '#090E1A'];
        yield 'alto contraste oscuro: enlace' => ['#AFC9E8', '#090E1A'];

        // --- Lenguaje del prototipo: superficies oscuras de marca ---
        // El cian es ilegible sobre claro (1.39:1) pero cumple de sobra
        // sobre el azul 900, que es el único sitio donde se usa.
        yield 'cian de marca sobre azul 900' => ['#00F2FE', '#0A192F'];
        yield 'texto del pie sobre azul 900' => ['#D6E4F5', '#0A192F'];
        yield 'texto tenue del pie sobre azul 900' => ['#AFC9E8', '#0A192F'];
        yield 'blanco sobre azul 700 (héroe y botones)' => ['#FFFFFF', '#1A365D'];
        yield 'titular del degradado sobre fondo' => ['#1A365D', '#FFFFFF'];
        // El degradado del héroe se invierte en oscuro: con los azules
        // oscuros daba 1,54:1 sobre el fondo del tema y el titular no se
        // leía. Ambos extremos del gradiente deben cumplir por separado.
        yield 'oscuro: degradado, extremo claro' => ['#AFC9E8', '#090E1A'];
        yield 'oscuro: degradado, extremo medio' => ['#7FA9D8', '#090E1A'];


    }

    #[DataProvider('paresDeTextoYFondo')]
    public function testElParCumpleAaYSigueEnLaHojaDeTokens(string $texto, string $fondo): void
    {
        $ratio = self::ratioDeContraste($texto, $fondo);

        self::assertGreaterThanOrEqual(
            self::MINIMO_AA,
            $ratio,
            sprintf('El par %s sobre %s da %.2f:1 y el mínimo AA es 4.5:1.', $texto, $fondo, $ratio),
        );

        $css = self::hojaDeTokens();

        foreach ([$texto, $fondo] as $color) {
            if ($color === '#FFFFFF' || $color === '#000000') {
                continue;
            }

            self::assertStringContainsStringIgnoringCase(
                $color,
                $css,
                sprintf(
                    'El color %s ya no está en resources/css/app.css: la paleta cambió. ' .
                    'Actualiza este par y verifica de nuevo su contraste.',
                    $color,
                ),
            );
        }
    }

    /**
     * Las tarjetas de acceso llevan un VELO de cian, no un color fijo: el
     * fondo real es lo que haya debajo, y solapan el héroe, así que caen
     * sobre dos fondos distintos en cada tema.
     *
     * No se fijan los hexadecimales ya mezclados, que se quedarían obsoletos
     * en silencio: la prueba lee el porcentaje del velo de `app.css`, calcula
     * la mezcla sobre cada fondo posible y exige AA en los cuatro. Si alguien
     * sube la opacidad para que se vea «más celeste», esto lo frena.
     */
    public function testElVeloCelesteCumpleSobreTodoFondoPosible(): void
    {
        $css = self::hojaDeTokens();

        self::assertSame(
            1,
            preg_match('/--color-celeste:\s*color-mix\(in srgb,\s*(#[0-9A-Fa-f]{6})\s+(\d+)%/', $css, $m),
            'No se encontró el velo celeste en app.css con la forma esperada.',
        );

        [, $tinte, $porcentaje] = $m;
        $alfa = ((int) $porcentaje) / 100;

        // fondo posible => textos que se dibujan encima
        $casos = [
            'fondo claro' => ['#FFFFFF', ['#0A192F', '#4B5563']],
            'superficie del héroe' => ['#F5F7FB', ['#0A192F', '#4B5563']],
            'fondo oscuro' => ['#090E1A', ['#E7ECF5', '#A9B4C6']],
            'superficie oscura' => ['#121A2E', ['#E7ECF5', '#A9B4C6']],
        ];

        foreach ($casos as $nombre => [$debajo, $textos]) {
            $resultante = self::mezclar($tinte, $debajo, $alfa);

            foreach ($textos as $texto) {
                $ratio = self::ratioDeContraste($texto, $resultante);

                self::assertGreaterThanOrEqual(
                    self::MINIMO_AA,
                    $ratio,
                    sprintf(
                        'El velo al %s%% sobre %s da %s, y %s encima queda en %.2f:1.',
                        $porcentaje,
                        $nombre,
                        $resultante,
                        $texto,
                        $ratio,
                    ),
                );
            }
        }
    }

    /** Mezcla alfa de dos colores opacos, como hace el navegador al componer. */
    private static function mezclar(string $frente, string $fondo, float $alfa): string
    {
        $canal = static fn (string $hex, int $i): int => (int) hexdec(substr(ltrim($hex, '#'), $i, 2));
        $salida = '#';

        foreach ([0, 2, 4] as $i) {
            $valor = (int) round($alfa * $canal($frente, $i) + (1 - $alfa) * $canal($fondo, $i));
            $salida .= str_pad(dechex($valor), 2, '0', STR_PAD_LEFT);
        }

        return strtoupper($salida);
    }

    public function testElVerdeDeMarcaNoSeUsaComoColorDeTexto(): void
    {
        // 3.5:1 sobre blanco: decorativo. Si algún día se necesita como texto,
        // hay que oscurecerlo (los tokens exito-* ya cumplen).
        self::assertLessThan(self::MINIMO_AA, self::ratioDeContraste('#2F9A72', '#FFFFFF'));
        self::assertStringContainsString(
            'decorativo',
            self::hojaDeTokens(),
            'La advertencia sobre el verde decorativo desapareció de app.css.',
        );
    }

    // --- soporte ---

    private static function hojaDeTokens(): string
    {
        $ruta = dirname(__DIR__, 3) . '/resources/css/app.css';
        $contenido = file_get_contents($ruta);
        self::assertNotFalse($contenido);

        return $contenido;
    }

    private static function ratioDeContraste(string $hexA, string $hexB): float
    {
        [$a, $b] = [self::luminancia($hexA), self::luminancia($hexB)];

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    /**
     * Luminancia relativa según WCAG 2.1 (sRGB).
     */
    private static function luminancia(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $canales = [];

        foreach ([0, 2, 4] as $inicio) {
            $valor = hexdec(substr($hex, $inicio, 2)) / 255;
            $canales[] = $valor <= 0.04045 ? $valor / 12.92 : (($valor + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $canales[0] + 0.7152 * $canales[1] + 0.0722 * $canales[2];
    }
}

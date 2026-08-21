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
        yield 'titular sobre fondo' => ['#041646', '#FFFFFF'];
        yield 'enlace sobre fondo' => ['#0030C9', '#FFFFFF'];
        yield 'texto del botón primario' => ['#FFFFFF', '#0035ED'];
        yield 'texto de error sobre fondo' => ['#B3251E', '#FFFFFF'];
        yield 'alerta de éxito' => ['#14532D', '#E7F5EE'];
        yield 'alerta de aviso' => ['#6B3308', '#FDF3E3'];
        yield 'alerta de error' => ['#7F1D18', '#FDEBEA'];
        yield 'texto sobre superficie' => ['#1F2937', '#F5F7FB'];

        // --- Tema oscuro ---
        yield 'oscuro: texto sobre fondo' => ['#E7ECF5', '#0B1220'];
        yield 'oscuro: texto suave sobre fondo' => ['#A9B4C6', '#0B1220'];
        yield 'oscuro: enlace sobre fondo' => ['#8FA8FF', '#0B1220'];
        yield 'oscuro: texto del botón primario' => ['#FFFFFF', '#2547E8'];
        yield 'oscuro: texto sobre superficie' => ['#E7ECF5', '#131C2E'];

        // --- Alto contraste ---
        yield 'alto contraste: texto' => ['#000000', '#FFFFFF'];
        yield 'alto contraste: enlace' => ['#002AA8', '#FFFFFF'];
        yield 'alto contraste oscuro: texto' => ['#FFFFFF', '#0B1220'];
        yield 'alto contraste oscuro: enlace' => ['#A9C1FB', '#0B1220'];
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

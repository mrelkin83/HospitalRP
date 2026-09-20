<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\CuentasCobro;

use App\Modules\CuentasCobro\Service\PlantillaPdf;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * El motor de marcadores de las plantillas PDF (§11): escapado siempre en
 * {{campo}}, crudo solo en {{{campo}}} y bucles {{#lista}}…{{/lista}}.
 */
#[CoversClass(PlantillaPdf::class)]
final class PlantillaPdfTest extends TestCase
{
    public function testReemplazaYEscapa(): void
    {
        $resultado = PlantillaPdf::rellenar(
            '<p>{{nombre}} — {{valor}}</p>',
            ['nombre' => 'Pérez & Hijos <SAS>', 'valor' => 2350000],
        );

        self::assertSame('<p>Pérez &amp; Hijos &lt;SAS&gt; — 2350000</p>', $resultado);
    }

    public function testTresLlavesInsertaCrudo(): void
    {
        $resultado = PlantillaPdf::rellenar(
            '<div>{{{firma}}}</div>',
            ['firma' => '<img src="data:image/png;base64,AAA=">'],
        );

        self::assertSame('<div><img src="data:image/png;base64,AAA="></div>', $resultado);
    }

    public function testBucleRepiteSuBloque(): void
    {
        $resultado = PlantillaPdf::rellenar(
            '<ul>{{#obligaciones}}<li>{{orden}}. {{texto}}</li>{{/obligaciones}}</ul>',
            ['obligaciones' => [
                ['orden' => 1, 'texto' => 'Primera'],
                ['orden' => 2, 'texto' => 'Segunda & tercera'],
            ]],
        );

        self::assertSame('<ul><li>1. Primera</li><li>2. Segunda &amp; tercera</li></ul>', $resultado);
    }

    public function testBucleVacioNoDejaRastro(): void
    {
        self::assertSame(
            '<ul></ul>',
            PlantillaPdf::rellenar('<ul>{{#anexos}}<li>{{nombre}}</li>{{/anexos}}</ul>', ['anexos' => []]),
        );
    }

    public function testMarcadorSinDatoQuedaVacio(): void
    {
        self::assertSame('<td></td>', PlantillaPdf::rellenar('<td>{{no_existe}}</td>', []));
    }

    public function testLasPlantillasRealesNoDejanMarcadoresSinResolver(): void
    {
        foreach (['cuenta-cobro.html', 'informe-actividades.html'] as $archivo) {
            $plantilla = (string) file_get_contents(dirname(__DIR__, 4) . '/templates/pdf/' . $archivo);

            // Rellenada con TODO vacío no deben quedar {{…}} huérfanos:
            // señal de un marcador mal escrito en la plantilla.
            $resultado = PlantillaPdf::rellenar($plantilla, []);

            self::assertDoesNotMatchRegularExpression(
                '/\{\{[^}]+\}\}/',
                $resultado,
                "Quedaron marcadores sin resolver en {$archivo}.",
            );
        }
    }
}

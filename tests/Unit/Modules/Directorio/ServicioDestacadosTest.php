<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Directorio;

use App\Modules\Directorio\Entity\Servicio;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Las cifras del Portafolio de Servicios 2026 («269 camas», «8 quirófanos»)
 * viajan como JSON en la columna `destacados`. La columna la escribe un
 * funcionario desde el panel, así que lo que llegue puede ser cualquier cosa:
 * la página de un servicio no puede caerse porque alguien dejó el JSON a
 * medias.
 */
#[CoversClass(Servicio::class)]
final class ServicioDestacadosTest extends TestCase
{
    public function testLeeLasCifrasDelPortafolio(): void
    {
        $servicio = $this->servicioCon('[{"cifra":"269","texto":"camas habilitadas"},{"cifra":"8","texto":"quirófanos"}]');

        self::assertSame(
            [
                ['cifra' => '269', 'texto' => 'camas habilitadas'],
                ['cifra' => '8', 'texto' => 'quirófanos'],
            ],
            $servicio->destacados,
        );
    }

    public function testSinCifrasDevuelveListaVacia(): void
    {
        self::assertSame([], $this->servicioCon(null)->destacados);
        self::assertSame([], $this->servicioCon('')->destacados);
    }

    public function testUnJsonRotoNoTumbaLaPagina(): void
    {
        self::assertSame([], $this->servicioCon('[{"cifra":"269",')->destacados);
        self::assertSame([], $this->servicioCon('"solo un texto"')->destacados);
    }

    public function testDescartaLasEntradasIncompletasYConservaElResto(): void
    {
        $servicio = $this->servicioCon('[{"cifra":"5"},{"cifra":"5","texto":"ambulancias"},"suelta"]');

        self::assertSame([['cifra' => '5', 'texto' => 'ambulancias']], $servicio->destacados);
    }

    public function testUnaCifraVaciaSigueSiendoUnDestacadoValido(): void
    {
        // El panel permite escribir solo el texto, sin número: «Atención
        // humanizada» es un destacado legítimo aunque no tenga cifra.
        $servicio = $this->servicioCon('[{"cifra":"","texto":"Atención humanizada"}]');

        self::assertSame([['cifra' => '', 'texto' => 'Atención humanizada']], $servicio->destacados);
    }

    private function servicioCon(?string $destacados): Servicio
    {
        return Servicio::desdeFila([
            'id' => 1,
            'nombre' => 'Servicios de hospitalización',
            'slug' => 'hospitalizacion',
            'destacados' => $destacados,
            'orden' => 1,
            'activo' => 1,
        ]);
    }
}

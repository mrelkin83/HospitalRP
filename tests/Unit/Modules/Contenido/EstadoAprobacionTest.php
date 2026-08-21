<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Contenido;

use App\Modules\Contenido\Entity\EstadoAprobacion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EstadoAprobacion::class)]
final class EstadoAprobacionTest extends TestCase
{
    public function testElFlujoHaciaAdelanteEsElDeclarado(): void
    {
        self::assertTrue(EstadoAprobacion::Borrador->puedeCambiarA(EstadoAprobacion::EnRevision));
        self::assertTrue(EstadoAprobacion::EnRevision->puedeCambiarA(EstadoAprobacion::Aprobado));
        self::assertTrue(EstadoAprobacion::Aprobado->puedeCambiarA(EstadoAprobacion::Publicado));
    }

    public function testNoSePuedeSaltarElFlujo(): void
    {
        self::assertFalse(EstadoAprobacion::Borrador->puedeCambiarA(EstadoAprobacion::Publicado));
        self::assertFalse(EstadoAprobacion::Borrador->puedeCambiarA(EstadoAprobacion::Aprobado));
        self::assertFalse(EstadoAprobacion::EnRevision->puedeCambiarA(EstadoAprobacion::Publicado));
    }

    public function testTodoRetrocesoVuelveABorrador(): void
    {
        self::assertTrue(EstadoAprobacion::EnRevision->puedeCambiarA(EstadoAprobacion::Borrador));
        self::assertTrue(EstadoAprobacion::Aprobado->puedeCambiarA(EstadoAprobacion::Borrador));
        self::assertTrue(EstadoAprobacion::Publicado->puedeCambiarA(EstadoAprobacion::Borrador));
        // Pero no a estados intermedios.
        self::assertFalse(EstadoAprobacion::Publicado->puedeCambiarA(EstadoAprobacion::Aprobado));
        self::assertFalse(EstadoAprobacion::Publicado->puedeCambiarA(EstadoAprobacion::EnRevision));
    }

    public function testSoloPublicadoEsPublico(): void
    {
        foreach (EstadoAprobacion::cases() as $estado) {
            self::assertSame($estado === EstadoAprobacion::Publicado, $estado->esPublico());
        }
    }
}

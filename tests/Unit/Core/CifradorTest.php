<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Support\Cifrador;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * El cifrador de secretos de BD (claves de API del panel): ida y vuelta
 * con la llave correcta, nada sin llave, y compatibilidad con valores
 * viejos sin cifrar.
 */
#[CoversClass(Cifrador::class)]
final class CifradorTest extends TestCase
{
    public function testCifraYDescifra(): void
    {
        $cifrador = new Cifrador(base64_encode(random_bytes(32)));
        $cifrado = $cifrador->cifrar('sk-ant-clave-de-prueba');

        self::assertStringStartsWith('cifrado:v1:', $cifrado);
        self::assertStringNotContainsString('sk-ant', $cifrado);
        self::assertSame('sk-ant-clave-de-prueba', $cifrador->descifrar($cifrado));
    }

    public function testConLlaveEquivocadaNoRevelaNada(): void
    {
        $cifrado = (new Cifrador(base64_encode(random_bytes(32))))->cifrar('secreto');

        self::assertNull((new Cifrador(base64_encode(random_bytes(32))))->descifrar($cifrado));
    }

    public function testSinLlaveNoSePuedeCifrar(): void
    {
        $cifrador = new Cifrador('');

        self::assertFalse($cifrador->disponible());
        $this->expectException(\RuntimeException::class);
        $cifrador->cifrar('secreto');
    }

    public function testUnValorViejoSinCifrarSeDevuelveTalCual(): void
    {
        self::assertSame(
            'clave-plana-heredada',
            (new Cifrador(base64_encode(random_bytes(32))))->descifrar('clave-plana-heredada'),
        );
    }
}

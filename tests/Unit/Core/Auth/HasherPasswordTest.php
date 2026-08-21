<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Auth;

use App\Core\Auth\HasherPassword;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HasherPassword::class)]
final class HasherPasswordTest extends TestCase
{
    public function testProduceArgon2idYVerifica(): void
    {
        $hasher = new HasherPassword();
        $hash = $hasher->hash('Secreta123!');

        self::assertStringStartsWith('$argon2id$', $hash);
        self::assertTrue($hasher->verificar('Secreta123!', $hash));
        self::assertFalse($hasher->verificar('otra-cosa', $hash));
        self::assertFalse($hasher->necesitaRehash($hash));
    }

    /**
     * Si algún día llegan hashes con otro algoritmo (una migración de datos,
     * un cambio de parámetros), el sistema debe detectarlos para rehashear en
     * el siguiente login correcto.
     */
    public function testDetectaHashesDeOtroAlgoritmo(): void
    {
        $hasher = new HasherPassword();
        $bcrypt = password_hash('Secreta123!', PASSWORD_BCRYPT);

        self::assertTrue($hasher->necesitaRehash($bcrypt));
    }
}

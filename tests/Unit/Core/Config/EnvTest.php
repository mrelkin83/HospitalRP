<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Config;

use App\Core\Config\Env;
use App\Core\Config\Exception\VariableEntornoFaltanteException;
use App\Core\Config\Exception\VariableEntornoInvalidaException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Env::class)]
final class EnvTest extends TestCase
{
    public function testDevuelveElTextoConfigurado(): void
    {
        $env = new Env(['DB_NOMBRE' => 'hrpl']);

        self::assertSame('hrpl', $env->texto('DB_NOMBRE'));
    }

    public function testUsaElValorPorDefectoCuandoLaClaveNoExiste(): void
    {
        $env = new Env([]);

        self::assertSame('America/Bogota', $env->texto('APP_ZONA_HORARIA', 'America/Bogota'));
    }

    /**
     * Una variable presente pero vacía es el error más común al copiar .env.example
     * y es también el más peligroso: se cuela como configuración válida.
     */
    public function testTrataLaVariableVaciaComoAusente(): void
    {
        $env = new Env(['DB_NOMBRE' => '']);

        self::assertFalse($env->tiene('DB_NOMBRE'));
        self::assertSame('respaldo', $env->texto('DB_NOMBRE', 'respaldo'));
    }

    public function testFallaSiFaltaUnaVariableObligatoria(): void
    {
        $env = new Env([]);

        $this->expectException(VariableEntornoFaltanteException::class);
        $this->expectExceptionMessage('APP_URL');

        $env->texto('APP_URL');
    }

    public function testDevuelveNuloParaVariablesOpcionales(): void
    {
        $env = new Env(['REDIS_DSN' => '']);

        self::assertNull($env->textoOpcional('REDIS_DSN'));
    }

    public function testConvierteEnteros(): void
    {
        $env = new Env(['DB_PUERTO' => '3306', 'DESFASE' => '-5']);

        self::assertSame(3306, $env->entero('DB_PUERTO'));
        self::assertSame(-5, $env->entero('DESFASE'));
    }

    public function testRechazaEnterosMalFormados(): void
    {
        $env = new Env(['DB_PUERTO' => '3306abc']);

        $this->expectException(VariableEntornoInvalidaException::class);

        $env->entero('DB_PUERTO');
    }

    #[DataProvider('valoresVerdaderos')]
    public function testInterpretaBooleanosVerdaderos(string $valor): void
    {
        self::assertTrue((new Env(['APP_DEBUG' => $valor]))->booleano('APP_DEBUG'));
    }

    #[DataProvider('valoresFalsos')]
    public function testInterpretaBooleanosFalsos(string $valor): void
    {
        self::assertFalse((new Env(['APP_DEBUG' => $valor]))->booleano('APP_DEBUG'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function valoresVerdaderos(): iterable
    {
        yield 'true' => ['true'];
        yield 'TRUE en mayúsculas' => ['TRUE'];
        yield 'uno' => ['1'];
        yield 'on' => ['on'];
        yield 'si en español' => ['si'];
        yield 'sí con tilde' => ['sí'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function valoresFalsos(): iterable
    {
        yield 'false' => ['false'];
        yield 'cero' => ['0'];
        yield 'off' => ['off'];
        yield 'no' => ['no'];
    }

    public function testRechazaBooleanosAmbiguos(): void
    {
        $env = new Env(['APP_DEBUG' => 'quizás']);

        $this->expectException(VariableEntornoInvalidaException::class);

        $env->booleano('APP_DEBUG');
    }

    public function testAceptaOpcionesDeUnaLista(): void
    {
        $env = new Env(['APP_ENTORNO' => 'local']);

        self::assertSame('local', $env->opcion('APP_ENTORNO', ['local', 'pruebas', 'produccion']));
    }

    public function testRechazaOpcionesFueraDeLaLista(): void
    {
        $env = new Env(['APP_ENTORNO' => 'staging']);

        $this->expectException(VariableEntornoInvalidaException::class);
        $this->expectExceptionMessage('staging');

        $env->opcion('APP_ENTORNO', ['local', 'pruebas', 'produccion']);
    }
}

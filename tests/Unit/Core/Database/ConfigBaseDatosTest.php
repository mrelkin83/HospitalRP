<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Database;

use App\Core\Config\Env;
use App\Core\Config\Exception\VariableEntornoFaltanteException;
use App\Core\Database\ConfigBaseDatos;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigBaseDatos::class)]
final class ConfigBaseDatosTest extends TestCase
{
    public function testLeeLaConfiguracionCompleta(): void
    {
        $config = ConfigBaseDatos::desde(new Env([
            'DB_HOST' => '10.0.0.5',
            'DB_PUERTO' => '3307',
            'DB_NOMBRE' => 'hrpl',
            'DB_USUARIO' => 'hrpl_app',
            'DB_PASSWORD' => 'secreto',
        ]));

        self::assertSame('10.0.0.5', $config->host);
        self::assertSame(3307, $config->puerto);
        self::assertSame('hrpl', $config->nombre);
        self::assertSame('hrpl_app', $config->usuario);
        self::assertSame('secreto', $config->password);
    }

    /**
     * En Laragon root no tiene contraseña y el .env trae DB_PASSWORD vacío.
     * Eso tiene que funcionar sin declarar la variable como opcional en el
     * resto del sistema.
     */
    public function testAceptaPasswordVacioYAsumeLocalhost(): void
    {
        $config = ConfigBaseDatos::desde(new Env([
            'DB_NOMBRE' => 'hrpl',
            'DB_USUARIO' => 'root',
        ]));

        self::assertSame('127.0.0.1', $config->host);
        self::assertSame(3306, $config->puerto);
        self::assertSame('', $config->password);
    }

    /**
     * El nombre de la base no tiene valor por defecto a propósito: conectarse
     * "a la que sea" es como terminan las migraciones aplicadas al servidor
     * equivocado.
     */
    public function testSinNombreDeBaseFallaAlArrancar(): void
    {
        $this->expectException(VariableEntornoFaltanteException::class);

        ConfigBaseDatos::desde(new Env(['DB_USUARIO' => 'root']));
    }

    public function testLosParametrosDbalConectanConUtf8mb4(): void
    {
        $config = ConfigBaseDatos::desde(new Env([
            'DB_NOMBRE' => 'hrpl',
            'DB_USUARIO' => 'root',
        ]));

        $parametros = $config->comoParametrosDbal();

        self::assertSame('pdo_mysql', $parametros['driver']);
        self::assertSame('utf8mb4', $parametros['charset']);
        self::assertSame('hrpl', $parametros['dbname']);
    }
}

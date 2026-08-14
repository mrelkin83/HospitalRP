<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Config;

use App\Core\Config\ConfigAplicacion;
use App\Core\Config\Entorno;
use App\Core\Config\Env;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigAplicacion::class)]
#[CoversClass(Entorno::class)]
final class ConfigAplicacionTest extends TestCase
{
    public function testLeeLaConfiguracionCompleta(): void
    {
        $config = ConfigAplicacion::desde(new Env([
            'APP_ENTORNO' => 'local',
            'APP_DEBUG' => 'true',
            'APP_URL' => 'http://hr.test',
            'APP_ZONA_HORARIA' => 'America/Bogota',
        ]));

        self::assertSame(Entorno::Local, $config->entorno);
        self::assertTrue($config->debug);
        self::assertSame('http://hr.test', $config->url);
        self::assertSame('America/Bogota', $config->zonaHoraria);
    }

    /**
     * El fallo más caro de todos: subir a producción con APP_DEBUG=true olvidado.
     * Una traza de PHP expone rutas, credenciales de base de datos y estructura
     * de tablas. El entorno tiene que ganarle a la variable.
     */
    public function testProduccionIgnoraAppDebugAunqueEsteEnTrue(): void
    {
        $config = ConfigAplicacion::desde(new Env([
            'APP_ENTORNO' => 'produccion',
            'APP_DEBUG' => 'true',
            'APP_URL' => 'https://hospitalrosariopumarejo.gov.co',
        ]));

        self::assertFalse($config->debug);
        self::assertFalse($config->entorno->permiteMostrarErrores());
        self::assertTrue($config->entorno->exigeHttps());
    }

    /**
     * Si APP_ENTORNO falta o viene mal, se asume producción. Un entorno mal
     * configurado debe fallar hacia lo seguro, no hacia lo cómodo.
     */
    public function testSinEntornoDeclaradoAsumeProduccion(): void
    {
        $config = ConfigAplicacion::desde(new Env([
            'APP_URL' => 'https://hospitalrosariopumarejo.gov.co',
        ]));

        self::assertSame(Entorno::Produccion, $config->entorno);
        self::assertFalse($config->debug);
    }

    public function testQuitaLaBarraFinalDeLaUrl(): void
    {
        $config = ConfigAplicacion::desde(new Env([
            'APP_ENTORNO' => 'local',
            'APP_URL' => 'http://hr.test/',
        ]));

        self::assertSame('http://hr.test', $config->url);
    }

    public function testZonaHorariaPorDefectoEsBogota(): void
    {
        $config = ConfigAplicacion::desde(new Env([
            'APP_ENTORNO' => 'local',
            'APP_URL' => 'http://hr.test',
        ]));

        self::assertSame('America/Bogota', $config->zonaHoraria);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Config\Env;
use App\Core\Database\ConexionFactory;
use App\Core\Database\ConfigBaseDatos;
use Doctrine\DBAL\Connection;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\PhpFile;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\MigratorConfiguration;

/**
 * Conexión a la base de pruebas (`hrpl_pruebas`, definida en phpunit.xml),
 * migrada a la última versión con las mismas migraciones que producción.
 *
 * No hay un "esquema de pruebas" paralelo: si una migración está rota, deben
 * romperse también las pruebas de integración.
 */
final class BaseDatosDePruebas
{
    private static ?Connection $conexion = null;

    private function __construct()
    {
    }

    public static function conexionMigrada(): Connection
    {
        if (self::$conexion !== null) {
            return self::$conexion;
        }

        $conexion = ConexionFactory::crear(
            ConfigBaseDatos::desde(Env::desdeSuperglobales()),
        );

        $fabrica = DependencyFactory::fromConnection(
            new PhpFile(dirname(__DIR__, 2) . '/config/migraciones.php'),
            new ExistingConnection($conexion),
        );

        $fabrica->getMetadataStorage()->ensureInitialized();

        $plan = $fabrica->getMigrationPlanCalculator()->getPlanUntilVersion(
            $fabrica->getVersionAliasResolver()->resolveVersionAlias('latest'),
        );

        if (count($plan) > 0) {
            $fabrica->getMigrator()->migrate($plan, new MigratorConfiguration());
        }

        return self::$conexion = $conexion;
    }

    /**
     * La conexión YA abierta, o `null` si ninguna prueba la ha pedido todavía.
     *
     * La usa la extensión `SaneaTransacciones` al terminar cada prueba. No
     * llama a `conexionMigrada()` a propósito: eso abriría la conexión y
     * correría las migraciones por el simple hecho de que terminó una prueba
     * unitaria que nunca toca la base.
     */
    public static function conexionAbiertaONula(): ?Connection
    {
        return self::$conexion;
    }
}

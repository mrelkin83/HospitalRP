<?php

declare(strict_types=1);

namespace App\Core\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

/**
 * Factoría de la conexión Doctrine DBAL.
 *
 * Factoría estática pura (la excepción que permite CLAUDE.md §5): no guarda
 * estado y existe solo para que el contenedor y la consola de migraciones
 * construyan la conexión desde el mismo punto.
 */
final class ConexionFactory
{
    private function __construct()
    {
    }

    public static function crear(ConfigBaseDatos $config): Connection
    {
        return DriverManager::getConnection($config->comoParametrosDbal());
    }
}

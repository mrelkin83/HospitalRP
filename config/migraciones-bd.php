<?php

declare(strict_types=1);

/**
 * Conexión que usa la consola de migraciones.
 *
 * Reutiliza el mismo bootstrap y la misma configuración tipada que la
 * aplicación: si la web conecta, las migraciones conectan, y al revés.
 */

use App\Core\Database\ConfigBaseDatos;

$env = require __DIR__ . '/bootstrap.php';

return ConfigBaseDatos::desde($env)->comoParametrosDbal();

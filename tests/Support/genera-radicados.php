<?php

declare(strict_types=1);

/**
 * Trabajador de la prueba de concurrencia de radicados: genera N radicados
 * contra la base de pruebas y los imprime, uno por línea. Lo lanzan en
 * paralelo varios procesos desde GeneradorRadicadoTest.
 */

use App\Core\Config\Env;
use App\Core\Database\ConexionFactory;
use App\Core\Database\ConfigBaseDatos;
use App\Modules\Tramites\Service\GeneradorRadicado;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$cantidad = (int) ($argv[1] ?? 10);

$conexion = ConexionFactory::crear(ConfigBaseDatos::desde(new Env([
    'DB_HOST' => getenv('DB_HOST') ?: '127.0.0.1',
    'DB_PUERTO' => getenv('DB_PUERTO') ?: '3306',
    'DB_NOMBRE' => getenv('DB_NOMBRE') ?: 'hrpl_pruebas',
    'DB_USUARIO' => getenv('DB_USUARIO') ?: 'root',
])));

$generador = new GeneradorRadicado($conexion);

for ($i = 0; $i < $cantidad; $i++) {
    echo $generador->siguiente('CONC', 2099), "\n";
}

<?php

declare(strict_types=1);

/**
 * Arranque común a la web, la consola y las pruebas.
 *
 * Solo hace tres cosas: cargar el autoload, poblar las variables de entorno y
 * fijar la zona horaria. El contenedor de servicios se construye después, a
 * partir del Env que devuelve este archivo.
 */

use App\Core\Config\ConfigAplicacion;
use App\Core\Config\Env;
use Symfony\Component\Dotenv\Dotenv;

$raiz = dirname(__DIR__);

require_once $raiz . '/vendor/autoload.php';

// En producción las variables las inyecta el servidor y no hay archivo .env.
if (is_file($raiz . '/.env')) {
    // usePutenv(false): putenv() no es seguro entre hilos y filtra credenciales
    // a procesos hijos. $_ENV y $_SERVER bastan.
    (new Dotenv())->usePutenv(false)->loadEnv($raiz . '/.env', defaultEnv: 'produccion');
}

$env = Env::desdeSuperglobales();

// Toda fecha del sistema —términos legales incluidos— se calcula en esta zona.
// Equivocarla desplaza los vencimientos de los trámites un día entero.
date_default_timezone_set(ConfigAplicacion::desde($env)->zonaHoraria);

return $env;

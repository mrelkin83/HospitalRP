<?php

declare(strict_types=1);

/**
 * Único punto de entrada de la aplicación (CLAUDE.md §4).
 *
 * `public/` es el document root. Nada más del proyecto —ni vendor, ni storage,
 * ni .env— es alcanzable por el navegador.
 */

use App\Core\Config\ConfigAplicacion;
use App\Core\Config\Env;
use App\Http\Kernel;

// Solo para `php -S` en desarrollo: los archivos estáticos existentes los
// sirve el propio servidor embebido. Apache/Nginx nunca entran aquí por ellos.
if (PHP_SAPI === 'cli-server') {
    $rutaPedida = __DIR__ . parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

    if (is_file($rutaPedida)) {
        return false;
    }
}
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;

/** @var Env $env */
$env = require dirname(__DIR__) . '/config/bootstrap.php';

/** @var callable(Env): ContainerBuilder $fabricaContenedor */
$fabricaContenedor = require dirname(__DIR__) . '/config/servicios.php';
$contenedor = $fabricaContenedor($env);

/** @var RouteCollection $rutas */
$rutas = require dirname(__DIR__) . '/config/rutas.php';

/** @var ConfigAplicacion $config */
$config = $contenedor->get(ConfigAplicacion::class);

$kernel = new Kernel($contenedor, $rutas, $config);

$peticion = Request::createFromGlobals();
$kernel->manejar($peticion)->prepare($peticion)->send();

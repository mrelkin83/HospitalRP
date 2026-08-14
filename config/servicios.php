<?php

declare(strict_types=1);

/**
 * Contenedor de servicios.
 *
 * Inyección por constructor en todo el proyecto, cero estado global
 * (CLAUDE.md §5). Los controladores son los únicos servicios públicos: el
 * Kernel los resuelve por nombre de clase. Todo lo demás se cablea por
 * autowiring y se mantiene privado.
 */

use App\Core\Config\ConfigAplicacion;
use App\Core\Config\Env;
use App\Http\Web\InicioController;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

return static function (Env $env): ContainerBuilder {
    $contenedor = new ContainerBuilder();

    // El Env ya viene construido desde bootstrap.php: es el único servicio que
    // no puede crear el contenedor, porque el contenedor depende de él.
    $contenedor->set(Env::class, $env);

    $contenedor->register(ConfigAplicacion::class, ConfigAplicacion::class)
        ->setFactory([ConfigAplicacion::class, 'desde'])
        ->setArguments([new Reference(Env::class)])
        ->setPublic(true);

    // --- Controladores del sitio público ---
    $contenedor->register(InicioController::class, InicioController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->compile();

    return $contenedor;
};

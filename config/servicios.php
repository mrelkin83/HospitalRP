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

use App\Core\Audit\AuditoriaService;
use App\Core\Config\ConfigAplicacion;
use App\Core\Config\Env;
use App\Core\Database\ConexionFactory;
use App\Core\Database\ConfigBaseDatos;
use App\Http\Web\InicioController;
use Doctrine\DBAL\Connection;
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

    // --- Base de datos ---
    // Perezosa por diseño de DBAL: registrar la conexión no abre socket alguno;
    // el primer query lo abre. Una página sin consultas no paga la conexión.
    $contenedor->register(ConfigBaseDatos::class, ConfigBaseDatos::class)
        ->setFactory([ConfigBaseDatos::class, 'desde'])
        ->setArguments([new Reference(Env::class)]);

    // Pública mientras ningún repositorio la referencie: el contenedor compilado
    // elimina los servicios privados sin consumidores. Volver privada cuando
    // exista el primer repositorio.
    $contenedor->register(Connection::class, Connection::class)
        ->setFactory([ConexionFactory::class, 'crear'])
        ->setArguments([new Reference(ConfigBaseDatos::class)])
        ->setPublic(true);

    // --- Auditoría ---
    // Pública por la misma razón que la conexión: sus consumidores llegan en
    // el paso 7 (autenticación). Volver privada entonces.
    $contenedor->register(AuditoriaService::class, AuditoriaService::class)
        ->setAutowired(true)
        ->setPublic(true);

    // --- Controladores del sitio público ---
    $contenedor->register(InicioController::class, InicioController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->compile();

    return $contenedor;
};

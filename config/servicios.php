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
use App\Core\Auth\AutenticadorService;
use App\Core\Auth\HasherPassword;
use App\Core\Auth\Sesion;
use App\Core\Auth\UsuarioRepository;
use App\Core\Config\ConfigAplicacion;
use App\Core\Config\Env;
use App\Core\Database\ConexionFactory;
use App\Core\Database\ConfigBaseDatos;
use App\Http\Admin\LoginController;
use App\Http\Admin\PanelController;
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

    $contenedor->register(Connection::class, Connection::class)
        ->setFactory([ConexionFactory::class, 'crear'])
        ->setArguments([new Reference(ConfigBaseDatos::class)]);

    // --- Auditoría ---
    $contenedor->register(AuditoriaService::class, AuditoriaService::class)
        ->setAutowired(true);

    // --- Autenticación y sesión ---
    $contenedor->register(HasherPassword::class, HasherPassword::class);

    $contenedor->register(UsuarioRepository::class, UsuarioRepository::class)
        ->setAutowired(true);

    $contenedor->register(AutenticadorService::class, AutenticadorService::class)
        ->setAutowired(true);

    $contenedor->register(Sesion::class, Sesion::class)
        ->setFactory([Sesion::class, 'desde'])
        ->setArguments([new Reference(Env::class), new Reference(ConfigAplicacion::class)]);

    // --- Controladores del sitio público ---
    $contenedor->register(InicioController::class, InicioController::class)
        ->setAutowired(true)
        ->setPublic(true);

    // --- Controladores del panel ---
    $contenedor->register(LoginController::class, LoginController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->register(PanelController::class, PanelController::class)
        ->setAutowired(true)
        ->setPublic(true);

    $contenedor->compile();

    return $contenedor;
};

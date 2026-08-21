<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Command;

use App\Modules\Contenido\Service\ProgramadorPublicacionService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Punto de entrada para el programador del sistema. En el servidor:
 *
 *   * * * * * php /ruta/bin/consola contenido:programado
 *
 * (En Windows/Laragon: una tarea del Programador de tareas cada minuto.)
 */
final class ContenidoProgramadoCommand extends Command
{
    public function __construct(private readonly ProgramadorPublicacionService $programador)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('contenido:programado')
            ->setDescription('Publica y despublica el contenido cuya fecha programada ya venció');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $resumen = $this->programador->ejecutar();

        (new SymfonyStyle($input, $output))->success(sprintf(
            '%d publicadas, %d despublicadas.',
            $resumen['publicadas'],
            $resumen['despublicadas'],
        ));

        return Command::SUCCESS;
    }
}

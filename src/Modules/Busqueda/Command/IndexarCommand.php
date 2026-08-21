<?php

declare(strict_types=1);

namespace App\Modules\Busqueda\Command;

use App\Modules\Busqueda\Service\IndexadorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reconstrucción completa del índice de búsqueda. Los servicios editoriales
 * reindexan por evento; este comando es la red de seguridad (tras restaurar
 * un respaldo, sembrar datos o corregir a mano) y corre bien en el cron
 * nocturno junto a `tramites:alertas`.
 */
#[AsCommand(name: 'busqueda:indexar', description: 'Reconstruye el índice unificado de búsqueda')]
final class IndexarCommand extends Command
{
    public function __construct(private readonly IndexadorService $indexador)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $conteos = $this->indexador->reconstruirTodo();

        foreach ($conteos as $tipo => $cantidad) {
            $output->writeln(sprintf('  %-14s %d', $tipo, $cantidad));
        }

        $output->writeln(sprintf('<info>Índice reconstruido: %d entradas.</info>', array_sum($conteos)));

        return Command::SUCCESS;
    }
}

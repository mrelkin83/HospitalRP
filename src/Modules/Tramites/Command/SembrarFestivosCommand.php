<?php

declare(strict_types=1);

namespace App\Modules\Tramites\Command;

use App\Modules\Tramites\Service\FestivosColombia;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Siembra los festivos de Colombia calculados por la Ley Emiliani.
 * Idempotente por fecha; correrla cada año (o programarla) mantiene la
 * tabla al día. Un festivo decretado por fuera de la ley se agrega a mano
 * en la tabla, sin tocar código.
 */
final class SembrarFestivosCommand extends Command
{
    public function __construct(private readonly Connection $conexion)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('semillas:festivos')
            ->setDescription('Siembra los festivos de Colombia (año actual y los dos siguientes)')
            ->addOption('desde', null, InputOption::VALUE_REQUIRED, 'Primer año a sembrar', date('Y'))
            ->addOption('hasta', null, InputOption::VALUE_REQUIRED, 'Último año a sembrar', (string) ((int) date('Y') + 2));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $desde = (int) $input->getOption('desde');
        $hasta = (int) $input->getOption('hasta');
        $creados = 0;

        for ($anio = $desde; $anio <= $hasta; $anio++) {
            foreach (FestivosColombia::delAnio($anio) as $fecha => $nombre) {
                $creados += (int) $this->conexion->executeStatement(
                    'INSERT IGNORE INTO festivos (fecha, nombre) VALUES (?, ?)',
                    [$fecha, $nombre],
                );
            }
        }

        $io->success(sprintf('%d festivos sembrados (%d–%d).', $creados, $desde, $hasta));

        return Command::SUCCESS;
    }
}

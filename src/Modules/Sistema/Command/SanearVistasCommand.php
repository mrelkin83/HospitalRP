<?php

declare(strict_types=1);

namespace App\Modules\Sistema\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Quita cabeceras PHP duplicadas de las plantillas.
 *
 * Existe porque el fallo se repitió cinco veces al rediseñar las vistas: la
 * versión nueva se pega encima de la vieja y el bloque de cabecera queda dos
 * veces. Si ese bloque trae un `use`, la página revienta con «Cannot use …
 * because the name is already in use», y solo se ve al abrir esa página.
 *
 * `VistasSanasTest` ya lo DETECTA; esto lo ARREGLA, que es lo que faltaba
 * para no corregirlo a mano cada vez.
 *
 * Deliberadamente conservador: solo borra el primer bloque cuando es idéntico
 * byte a byte al segundo. Si difieren en algo —aunque sea un espacio— no toca
 * nada y lo reporta, porque entonces no es una copia sino dos cabeceras
 * distintas y eso hay que mirarlo a mano.
 */
#[AsCommand(name: 'vistas:sanear', description: 'Quita cabeceras PHP duplicadas de las plantillas')]
final class SanearVistasCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('listar', null, InputOption::VALUE_NONE, 'Solo informa, sin escribir');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $soloListar = (bool) $input->getOption('listar');
        $raiz = dirname(__DIR__, 4) . '/resources/views';

        $saneadas = [];
        $sospechosas = [];

        foreach ($this->plantillas($raiz) as $ruta) {
            $contenido = (string) file_get_contents($ruta);

            if (preg_match('/^(<\?php\R.*?\R\?>\R)(<\?php\R.*?\R\?>\R)/s', $contenido, $bloques) !== 1) {
                continue;
            }

            $relativa = str_replace('\\', '/', substr($ruta, strlen($raiz) + 1));

            if ($bloques[1] !== $bloques[2]) {
                $sospechosas[] = $relativa;

                continue;
            }

            $saneadas[] = $relativa;

            if (!$soloListar) {
                file_put_contents($ruta, substr($contenido, strlen($bloques[1])));
            }
        }

        if ($saneadas === [] && $sospechosas === []) {
            $io->success('Ninguna plantilla tiene la cabecera duplicada.');

            return Command::SUCCESS;
        }

        if ($saneadas !== []) {
            $io->writeln(sprintf(
                '<info>%d plantilla(s) con la cabecera duplicada%s:</info>',
                count($saneadas),
                $soloListar ? '' : ', ya corregidas',
            ));
            $io->listing($saneadas);
        }

        if ($sospechosas !== []) {
            $io->warning('Estas tienen dos bloques al inicio pero DISTINTOS. No se tocaron; revísalas a mano:');
            $io->listing($sospechosas);
        }

        return Command::SUCCESS;
    }

    /** @return list<string> */
    private function plantillas(string $raiz): array
    {
        $rutas = [];
        $iterador = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($raiz));

        /** @var \SplFileInfo $archivo */
        foreach ($iterador as $archivo) {
            if ($archivo->isFile() && $archivo->getExtension() === 'php') {
                $rutas[] = $archivo->getPathname();
            }
        }

        sort($rutas);

        return $rutas;
    }
}

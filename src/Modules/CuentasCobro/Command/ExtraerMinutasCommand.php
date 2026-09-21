<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Command;

use App\Modules\CuentasCobro\Repository\ExtraccionMinutaRepository;
use App\Modules\CuentasCobro\Service\ExtractorMinutaService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Procesa la cola de minutas subidas (§fase 6, bloque A).
 *
 * La extracción vivía dentro de la petición web: el funcionario subía un
 * PDF y esperaba el minuto con la pestaña abierta. Con 1.500 contratos
 * eso son veinticinco horas de navegador, así que la subida ahora solo
 * encola y este comando hace el trabajo — de noche, sin nadie mirando, y
 * reanudable: si se corta en la minuta 700, arranca en la 701.
 *
 * Se corre programado cada dos minutos para el día a día, y a mano con
 * `--limite` alto (y dos o tres procesos con `--trabajador` distinto)
 * para la carga inicial.
 */
final class ExtraerMinutasCommand extends Command
{
    private const int MAXIMO_INTENTOS = 2;

    public function __construct(
        private readonly ExtraccionMinutaRepository $extracciones,
        private readonly ExtractorMinutaService $extractor,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('cuentas:extraer-minutas')
            ->setDescription('Procesa la cola de minutas pendientes de extracción')
            ->addOption('limite', null, InputOption::VALUE_REQUIRED, 'Cuántas procesar como máximo en esta corrida', '5')
            ->addOption('trabajador', null, InputOption::VALUE_REQUIRED, 'Etiqueta del proceso, para distinguir varios en paralelo', '1')
            ->addOption('colgadas', null, InputOption::VALUE_REQUIRED, 'Minutos tras los que una extracción PROCESANDO vuelve a la cola', '15');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limite = max(1, (int) $input->getOption('limite'));
        $trabajador = trim((string) $input->getOption('trabajador'));

        // Antes de tomar nada: devolver a la cola lo que otro proceso dejó
        // colgado al morirse, y cerrar lo que ya agotó sus intentos.
        $liberadas = $this->extracciones->liberarColgadas(max(1, (int) $input->getOption('colgadas')));
        $agotadas = $this->extracciones->marcarAgotadas(self::MAXIMO_INTENTOS);

        if ($liberadas > 0) {
            $io->text(sprintf('%d extracción(es) colgada(s) devuelta(s) a la cola.', $liberadas));
        }

        if ($agotadas > 0) {
            $io->warning(sprintf('%d extracción(es) agotaron sus %d intentos y quedaron en ERROR.', $agotadas, self::MAXIMO_INTENTOS));
        }

        $procesadas = 0;
        $fallidas = 0;

        while ($procesadas + $fallidas < $limite) {
            $extraccion = $this->extracciones->reclamarPendiente(self::MAXIMO_INTENTOS);

            if ($extraccion === null) {
                break;
            }

            $id = (int) $extraccion['id'];

            // Una minuta ilegible no puede tumbar la corrida entera: se
            // anota en su propia fila y el trabajador sigue con la
            // siguiente. Al tercer intento `marcarAgotadas` la cierra.
            try {
                $resultado = $this->extractor->procesar($id, (int) $extraccion['creado_por']);
            } catch (\Throwable $e) {
                $this->extracciones->actualizar($id, ['estado' => 'ERROR', 'error' => mb_substr($e->getMessage(), 0, 1000)]);
                $resultado = ['ok' => false, 'mensaje' => $e->getMessage()];
            }

            if ($resultado['ok']) {
                $procesadas++;
                $io->text(sprintf('[%s] #%d · %s', $trabajador, $id, $resultado['mensaje']));

                continue;
            }

            $fallidas++;
            $io->text(sprintf('[%s] #%d · FALLÓ: %s', $trabajador, $id, $resultado['mensaje']));

            // El fallo deja la fila en ERROR; si aún le quedan intentos,
            // vuelve a la cola para el siguiente pase.
            if ((int) $extraccion['intentos'] < self::MAXIMO_INTENTOS) {
                $this->extracciones->actualizar($id, ['estado' => 'PENDIENTE', 'reclamado_en' => null]);
            }
        }

        $pendientes = $this->extracciones->conteoPorEstado()['PENDIENTE'] ?? 0;

        $io->success(sprintf(
            '%d extraída(s), %d con error. Quedan %d en cola.',
            $procesadas,
            $fallidas,
            $pendientes,
        ));

        return Command::SUCCESS;
    }
}

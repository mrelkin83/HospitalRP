<?php

declare(strict_types=1);

namespace App\Modules\Sistema\Command;

use App\Modules\Sistema\Repository\RedireccionRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Redirecciones desde el Joomla viejo (inventario en docs/08). Idempotente:
 * un origen existente nunca se sobreescribe. Los prefijos `*` atrapan las
 * sub-URL que no vale la pena mapear una por una; la red de seguridad final
 * lleva cualquier resto de `/sitio/` a la portada en lugar de a un 404.
 */
#[AsCommand(name: 'semillas:redirecciones', description: 'Siembra las redirecciones 301 del sitio Joomla anterior')]
final class SembrarRedireccionesCommand extends Command
{
    private const string JOOMLA = '/sitio/index.php/es';

    /** @var array<string, string> origen (relativo al Joomla) => destino nuevo */
    private const array MAPA = [
        '/peticiones-quejas-y-reclamos' => '/atencion-ciudadania/pqrsdf',
        '/servicio-al-ciudadano/peticiones-quejas-y-reclamos' => '/atencion-ciudadania/pqrsdf',
        '/servicio-al-ciudadano/preguntas-frecuentes' => '/atencion-ciudadania/preguntas-frecuentes',
        '/servicio-al-ciudadano/*' => '/atencion-ciudadania',
        '/transparencia' => '/transparencia',
        '/transparencia/*' => '/transparencia',
        '/normativa' => '/transparencia/normatividad',
        '/normativa/*' => '/transparencia/normatividad',
        '/informes' => '/transparencia/planeacion-presupuesto-informes',
        '/informes/*' => '/transparencia/planeacion-presupuesto-informes',
        '/contratacion' => '/transparencia',
        '/contratacion/*' => '/transparencia',
        '/prensa' => '/noticias',
        '/prensa/*' => '/noticias',
        '/el-hospital' => '/transparencia',
        '/el-hospital/*' => '/transparencia',
        '/directorio' => '/directorio',
        '/servicios' => '/servicios',
        '/servicios/*' => '/servicios',
    ];

    public function __construct(private readonly RedireccionRepository $redirecciones)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $creadas = 0;

        foreach (self::MAPA as $origen => $destino) {
            $creadas += (int) $this->redirecciones->crearSiNoExiste(self::JOOMLA . $origen, $destino);
        }

        // Redes de seguridad, de la más específica a la más amplia.
        $creadas += (int) $this->redirecciones->crearSiNoExiste(self::JOOMLA . '/*', '/');
        $creadas += (int) $this->redirecciones->crearSiNoExiste('/sitio/*', '/');

        $output->writeln(sprintf('<info>Redirecciones: %d nuevas, el resto ya existía.</info>', $creadas));

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Tramites\Command;

use App\Core\Audit\AccionAuditoria;
use App\Core\Audit\AuditoriaService;
use App\Core\Audit\RegistroAuditoria;
use App\Modules\Tramites\Repository\TramiteRepository;
use App\Ports\NotificadorCorreo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Alertas de vencimiento (criterio de la Fase 5): cada responsable recibe
 * un resumen de sus trámites vencidos o que vencen en los próximos 3 días.
 * Programar una vez al día en el programador del sistema:
 *
 *   0 6 * * * php /ruta/bin/consola tramites:alertas
 */
final class AlertasVencimientoCommand extends Command
{
    public function __construct(
        private readonly TramiteRepository $tramites,
        private readonly NotificadorCorreo $correo,
        private readonly AuditoriaService $auditoria,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('tramites:alertas')
            ->setDescription('Notifica a los responsables los trámites vencidos o por vencer (3 días)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $porVencer = $this->tramites->porVencer(3);

        if ($porVencer === []) {
            $io->success('Sin trámites vencidos ni por vencer. Nada que notificar.');

            return Command::SUCCESS;
        }

        // Un resumen por destinatario: el correo del asignado y los correos
        // configurados en el tipo de trámite.
        $porDestinatario = [];
        $hoy = date('Y-m-d');

        foreach ($porVencer as $tramite) {
            $destinatarios = [];

            if (($tramite['asignado_correo'] ?? null) !== null) {
                $destinatarios[] = (string) $tramite['asignado_correo'];
            }

            foreach (explode(',', (string) ($tramite['tipo_notificar_correos'] ?? '')) as $correo) {
                $correo = trim($correo);

                if ($correo !== '' && filter_var($correo, FILTER_VALIDATE_EMAIL) !== false) {
                    $destinatarios[] = $correo;
                }
            }

            $linea = sprintf(
                '%s · %s · %s · vence %s%s',
                (string) $tramite['radicado'],
                (string) $tramite['tipo_nombre'],
                mb_substr((string) $tramite['asunto'], 0, 80),
                (string) $tramite['vence_en'],
                (string) $tramite['vence_en'] < $hoy ? ' [VENCIDO]' : '',
            );

            foreach (array_unique($destinatarios) as $destinatario) {
                $porDestinatario[$destinatario][] = $linea;
            }
        }

        $enviados = 0;

        foreach ($porDestinatario as $destinatario => $lineas) {
            try {
                $this->correo->enviar(
                    $destinatario,
                    sprintf('[Trámites] %d solicitud(es) vencidas o por vencer', count($lineas)),
                    "Estas solicitudes requieren atención:\n\n- " . implode("\n- ", $lineas)
                    . "\n\nGestión: https://hrplopez.gov.co/admin/tramites",
                );
                $enviados++;
            } catch (Throwable $e) {
                $io->warning(sprintf('No se pudo notificar a %s: %s', $destinatario, $e->getMessage()));
            }
        }

        $this->auditoria->registrar(new RegistroAuditoria(
            accion: AccionAuditoria::CambioEstado,
            entidad: 'tramites',
            descripcion: sprintf(
                'Alertas de vencimiento: %d trámites, %d correos enviados',
                count($porVencer),
                $enviados,
            ),
            usuarioNombre: 'tarea programada',
        ));

        $io->success(sprintf('%d trámites por vencer; %d resúmenes enviados.', count($porVencer), $enviados));

        return Command::SUCCESS;
    }
}

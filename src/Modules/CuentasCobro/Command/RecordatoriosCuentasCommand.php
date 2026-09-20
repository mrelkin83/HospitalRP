<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Command;

use App\Modules\Tramites\Service\CalculadoraDiasHabiles;
use App\Ports\NotificadorCorreo;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Recordatorios automáticos del módulo de cuentas de cobro (fase 4).
 * Pensado para correr a diario por cron:
 *
 * - Al CONTRATISTA cuya cuota ya puede cobrarse y no ha presentado la
 *   cuenta: el primer día y luego cada semana, para acompañar sin acosar.
 * - Al SUPERVISOR con cuentas ENVIADAS sin revisar a los 3 días HÁBILES
 *   (calculados con la tabla de festivos de la plataforma), y luego cada
 *   3 hábiles.
 *
 * Un correo caído no interrumpe la tanda: se cuenta y se sigue.
 */
final class RecordatoriosCuentasCommand extends Command
{
    public function __construct(
        private readonly Connection $conexion,
        private readonly NotificadorCorreo $correo,
        private readonly CalculadoraDiasHabiles $habiles,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('cuentas:recordatorios')
            ->setDescription('Recuerda cuentas por presentar (contratistas) y por revisar (supervisores)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $aContratistas = $this->recordarAContratistas();
        $aSupervisores = $this->recordarASupervisores();

        $io->success(sprintf(
            'Recordatorios: %d a contratistas, %d a supervisores.',
            $aContratistas,
            $aSupervisores,
        ));

        return Command::SUCCESS;
    }

    /** Cuotas vencidas de contratos activos sin cuenta viva, con contratista con correo. */
    private function recordarAContratistas(): int
    {
        $filas = $this->conexion->fetchAllAssociative(
            "SELECT p.periodo_hasta, p.numero_cuota, k.numero AS contrato,
                    t.correo, t.nombres, DATEDIFF(CURDATE(), p.periodo_hasta) AS dias_vencida
               FROM cc_contrato_pagos p
               JOIN cc_contratos k ON k.id = p.contrato_id AND k.estado = 'ACTIVO'
               JOIN cc_contratistas t ON t.id = k.contratista_id AND t.activo = 1
              WHERE p.periodo_hasta IS NOT NULL
                AND p.periodo_hasta < CURDATE()
                AND t.correo IS NOT NULL AND t.correo <> ''
                AND NOT EXISTS (
                    SELECT 1 FROM cc_cuentas c WHERE c.pago_id = p.id AND c.estado <> 'ANULADA'
                )
              ORDER BY p.periodo_hasta",
        );
        $enviados = 0;

        foreach ($filas as $fila) {
            // El día siguiente al vencimiento y luego cada 7: acompañar,
            // no acosar (§2: el contratista es un adulto mayor, no un moroso).
            $dias = (int) $fila['dias_vencida'];

            if ($dias < 1 || ($dias - 1) % 7 !== 0) {
                continue;
            }

            try {
                $this->correo->enviar(
                    (string) $fila['correo'],
                    'Ya puede presentar su cuenta de cobro',
                    'Hola, ' . $fila['nombres'] . ".\n\n"
                    . 'El periodo de su cuota N° ' . (int) $fila['numero_cuota'] . ' del contrato '
                    . $fila['contrato'] . " ya terminó y todavía no hemos recibido su cuenta de cobro.\n\n"
                    . "Entre al portal cuando pueda; el sistema le tiene casi todo listo.\n\n"
                    . 'E.S.E. Hospital Rosario Pumarejo de López',
                );
                $enviados++;
            } catch (\Throwable) {
                // Un correo caído no frena la tanda.
            }
        }

        return $enviados;
    }

    /** Cuentas ENVIADAS con 3+ días hábiles esperando, cada 3 hábiles. */
    private function recordarASupervisores(): int
    {
        $filas = $this->conexion->fetchAllAssociative(
            "SELECT c.radicado, c.consecutivo, c.enviada_en, k.numero AS contrato,
                    t.nombre_completo AS contratista, u.correo
               FROM cc_cuentas c
               JOIN cc_contratos k ON k.id = c.contrato_id
               JOIN cc_contratistas t ON t.id = k.contratista_id
               JOIN cc_supervisores s ON s.id = k.supervisor_id AND s.activo = 1
               JOIN usuarios u ON u.id = s.usuario_id
              WHERE c.estado = 'ENVIADA' AND c.enviada_en IS NOT NULL",
        );
        $enviados = 0;

        foreach ($filas as $fila) {
            $habiles = $this->habiles->habilesEntre(
                new \DateTimeImmutable((string) $fila['enviada_en']),
                new \DateTimeImmutable('today'),
            );

            if ($habiles < 3 || ($habiles - 3) % 3 !== 0) {
                continue;
            }

            try {
                $this->correo->enviar(
                    (string) $fila['correo'],
                    'Cuenta de cobro ' . $fila['radicado'] . ' lleva ' . $habiles . ' días hábiles sin revisión',
                    'La cuenta N° ' . (int) $fila['consecutivo'] . ' del contrato ' . $fila['contrato']
                    . ' (' . $fila['contratista'] . ') fue enviada el ' . substr((string) $fila['enviada_en'], 0, 10)
                    . " y sigue pendiente de revisión.\n\n"
                    . "Revísela en el panel: Cuentas de cobro → Bandeja de supervisión.\n\n"
                    . 'E.S.E. Hospital Rosario Pumarejo de López',
                );
                $enviados++;
            } catch (\Throwable) {
                // Ídem: se cuenta la falla en silencio y se sigue.
            }
        }

        return $enviados;
    }
}

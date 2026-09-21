<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Service;

use App\Modules\CuentasCobro\Entity\EstadoCuenta;
use App\Modules\CuentasCobro\Repository\CuentaRepository;
use App\Ports\NotificadorCorreo;
use Doctrine\DBAL\Connection;

/**
 * La revisión previa: el equipo de revisores verifica cada cuenta ENVIADA
 * y la firma antes de pasarla al supervisor (ENVIADA → REVISADA), o la
 * devuelve al contratista con el motivo (ENVIADA → DEVUELTA).
 *
 * La firma del revisor queda en cc_cuenta_firmas como documento REVISION:
 * no hay PDF nuevo —los documentos ya los firmó el contratista—, así que
 * el hash sella la evidencia de la revisión (cuenta, revisor y momento).
 */
final readonly class RevisionCuentaService
{
    public function __construct(
        private Connection $conexion,
        private CuentaRepository $cuentas,
        private NotificadorCorreo $correo,
    ) {
    }

    /**
     * @param array<string, mixed> $cuenta  Fila de cc_cuentas.
     * @param array<string, mixed> $revisor Fila de cc_revisores.
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function marcarRevisada(array $cuenta, array $revisor, ?string $observacion, string $ip, ?string $userAgent): array
    {
        $cuentaId = (int) $cuenta['id'];

        if ((string) $cuenta['estado'] !== EstadoCuenta::ENVIADA->value) {
            return ['ok' => false, 'mensaje' => 'Solo una cuenta enviada (y aún sin revisar) se puede marcar como revisada.'];
        }

        if (!is_string($revisor['firma_archivo'] ?? null) || $revisor['firma_archivo'] === '') {
            return ['ok' => false, 'mensaje' => 'Registre su firma antes de revisar: la revisión sale firmada.'];
        }

        $ahora = date('Y-m-d H:i:s');
        $observacion = trim((string) $observacion);

        $this->cuentas->registrarFirma($cuentaId, [
            'documento' => 'REVISION',
            'firmante_rol' => 'REVISOR',
            'usuario_id' => (int) $revisor['usuario_id'],
            'revisor_id' => (int) $revisor['id'],
            'firma_archivo' => (string) $revisor['firma_archivo'],
            'hash_pdf' => hash('sha256', json_encode([
                'cuenta_id' => $cuentaId,
                'radicado' => (string) ($cuenta['radicado'] ?? ''),
                'revisor_id' => (int) $revisor['id'],
                'momento' => $ahora,
            ], JSON_THROW_ON_ERROR)),
            'ip' => $ip,
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 300),
        ]);

        $this->cuentas->transicionar(
            $cuentaId,
            EstadoCuenta::ENVIADA,
            EstadoCuenta::REVISADA,
            'REVISOR',
            (int) $revisor['usuario_id'],
            null,
            $observacion !== '' ? $observacion : null,
            $ip,
            ['revisada_en' => $ahora, 'revisada_por' => (int) $revisor['id']],
        );

        $this->avisarAlSupervisor($cuenta, $revisor);

        return ['ok' => true, 'mensaje' => 'Cuenta revisada y firmada: quedó en la bandeja del supervisor.'];
    }

    /**
     * @param array<string, mixed> $cuenta
     * @param array<string, mixed> $revisor
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function devolver(array $cuenta, array $revisor, string $observacion, string $ip): array
    {
        $cuentaId = (int) $cuenta['id'];

        if ((string) $cuenta['estado'] !== EstadoCuenta::ENVIADA->value) {
            return ['ok' => false, 'mensaje' => 'Solo una cuenta enviada se puede devolver desde la revisión.'];
        }

        $observacion = trim($observacion);

        if (mb_strlen($observacion) < 15) {
            return ['ok' => false, 'mensaje' => 'Escriba el motivo completo de la devolución: el contratista debe entender qué corregir.'];
        }

        $this->cuentas->transicionar(
            $cuentaId,
            EstadoCuenta::ENVIADA,
            EstadoCuenta::DEVUELTA,
            'REVISOR',
            (int) $revisor['usuario_id'],
            null,
            $observacion,
            $ip,
            ['observacion_supervisor' => $observacion],
        );

        $this->avisarAlContratista($cuenta, $observacion);

        return ['ok' => true, 'mensaje' => 'Cuenta devuelta. El contratista verá el motivo en su portal y por correo.'];
    }

    /** @param array<string, mixed> $cuenta @param array<string, mixed> $revisor */
    private function avisarAlSupervisor(array $cuenta, array $revisor): void
    {
        $destinatario = $this->conexion->fetchOne(
            'SELECT u.correo FROM cc_contratos k
               JOIN cc_supervisores s ON s.id = k.supervisor_id
               JOIN usuarios u ON u.id = s.usuario_id
              WHERE k.id = ?',
            [(int) $cuenta['contrato_id']],
        );

        if (!is_string($destinatario) || $destinatario === '') {
            return;
        }

        try {
            $this->correo->enviar(
                $destinatario,
                'Cuenta de cobro ' . (string) ($cuenta['radicado'] ?? '') . ' revisada y lista para su aprobación',
                'La cuenta de cobro N° ' . (int) $cuenta['consecutivo'] . ' (radicado '
                . (string) ($cuenta['radicado'] ?? '') . ') pasó la revisión previa de '
                . (string) $revisor['nombre_completo'] . " y espera su aprobación.\n\n"
                . "Revísela en el panel: Cuentas de cobro → Bandeja de supervisión.\n\n"
                . 'E.S.E. Hospital Rosario Pumarejo de López',
            );
        } catch (\Throwable) {
            // La revisión ya quedó en firme; el correo caído no la revierte.
        }
    }

    /** @param array<string, mixed> $cuenta */
    private function avisarAlContratista(array $cuenta, string $observacion): void
    {
        $destinatario = $this->conexion->fetchOne(
            'SELECT t.correo FROM cc_contratos k
               JOIN cc_contratistas t ON t.id = k.contratista_id
              WHERE k.id = ?',
            [(int) $cuenta['contrato_id']],
        );

        if (!is_string($destinatario) || $destinatario === '') {
            return;
        }

        try {
            $this->correo->enviar(
                $destinatario,
                'Su cuenta de cobro fue devuelta para corrección',
                'Su cuenta de cobro N° ' . (int) $cuenta['consecutivo'] . ' fue devuelta en la revisión previa.'
                . "\n\nMotivo: {$observacion}\n\n"
                . "Corríjala y vuelva a enviarla desde el portal: /cuentas/ingreso\n\n"
                . 'E.S.E. Hospital Rosario Pumarejo de López',
            );
        } catch (\Throwable) {
        }
    }
}

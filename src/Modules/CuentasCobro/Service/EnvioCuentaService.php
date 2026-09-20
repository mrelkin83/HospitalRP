<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Service;

use App\Modules\CuentasCobro\Entity\EstadoCuenta;
use App\Modules\CuentasCobro\Repository\CuentaRepository;
use App\Modules\Tramites\Service\GeneradorRadicado;
use App\Ports\NotificadorCorreo;
use Doctrine\DBAL\Connection;

/**
 * El paso de BORRADOR/DEVUELTA a ENVIADA (§5 y §7.1 P5): radicado, PDF
 * definitivos con la firma del contratista, evidencia de firma electrónica
 * simple y aviso al supervisor.
 *
 * El radicado reutiliza el generador de la plataforma (servicio público
 * del módulo de trámites, como manda CLAUDE.md §4): «CC-2026-000123»,
 * secuencia global con bloqueo transaccional. Al reenviar una cuenta
 * DEVUELTA se conserva el radicado: es la misma cuenta corregida, no un
 * trámite nuevo.
 *
 * El renglón de verificación impreso en cada PDF lleva el SHA-256
 * abreviado del CONTENIDO del documento (el HTML con los datos, antes de
 * añadir el propio renglón): un hash no puede contenerse a sí mismo. El
 * hash completo del PDF final queda en cc_cuenta_firmas.
 */
final readonly class EnvioCuentaService
{
    public function __construct(
        private Connection $conexion,
        private CuentaRepository $cuentas,
        private GeneradorDocumentosCuenta $generador,
        private GeneradorRadicado $radicados,
        private NotificadorCorreo $correo,
    ) {
    }

    /**
     * @param array<string, mixed> $cuenta      Fila de cc_cuentas.
     * @param array<string, mixed> $contratista Fila de cc_contratistas.
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function enviar(array $cuenta, array $contratista, string $ip, ?string $userAgent): array
    {
        $cuentaId = (int) $cuenta['id'];
        $estado = EstadoCuenta::from((string) $cuenta['estado']);

        if (!in_array($estado, [EstadoCuenta::BORRADOR, EstadoCuenta::DEVUELTA], true)) {
            return ['ok' => false, 'mensaje' => 'Esta cuenta ya fue enviada.'];
        }

        if (!is_string($contratista['firma_archivo'] ?? null) || $contratista['firma_archivo'] === '') {
            return ['ok' => false, 'mensaje' => 'Falta su firma. Regístrela en «Mis datos» antes de enviar.'];
        }

        if ($this->cuentas->seguridadSocial($cuentaId) === null) {
            return ['ok' => false, 'mensaje' => 'Faltan los datos de su planilla de seguridad social (paso 3).'];
        }

        if ($this->cuentas->adjuntos($cuentaId, 'PLANILLA_PILA') === []) {
            return ['ok' => false, 'mensaje' => 'Falta adjuntar la planilla PILA (paso 3). Sin soporte no hay cuenta que enviar.'];
        }

        $radicado = is_string($cuenta['radicado'] ?? null) && $cuenta['radicado'] !== ''
            ? (string) $cuenta['radicado']
            : $this->radicados->siguiente('CC');

        $ahora = date('Y-m-d H:i:s');
        $rutas = [];

        foreach (['CUENTA_COBRO' => 'pdf_cuenta_cobro', 'INFORME_ACTIVIDADES' => 'pdf_informe_actividades'] as $clave => $columna) {
            $contenidoFirmado = $this->generador->vistaPrevia($clave, $cuentaId);
            $verificacion = strtoupper(substr(hash('sha256', $contenidoFirmado), 0, 12));
            $renglon = sprintf(
                'Documento generado electrónicamente. Radicado %s. Firmado por %s el %s. Verificación: %s. Firma electrónica simple, Ley 527 de 1999 y Decreto 2364 de 2012.',
                $radicado,
                (string) $contratista['nombre_completo'],
                date('d/m/Y H:i'),
                $verificacion,
            );

            $documento = $this->generador->generar($clave, $cuentaId, $renglon);
            $rutas[$columna] = $documento['ruta'];

            $this->cuentas->registrarFirma($cuentaId, [
                'documento' => $clave,
                'firmante_rol' => 'CONTRATISTA',
                'contratista_id' => (int) $contratista['id'],
                'firma_archivo' => (string) $contratista['firma_archivo'],
                'hash_pdf' => $documento['hash'],
                'ip' => $ip,
                'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 300),
            ]);
        }

        $this->cuentas->transicionar(
            $cuentaId,
            $estado,
            EstadoCuenta::ENVIADA,
            'CONTRATISTA',
            null,
            (int) $contratista['id'],
            $estado === EstadoCuenta::DEVUELTA ? 'Reenviada tras corrección' : null,
            $ip,
            $rutas + ['radicado' => $radicado, 'enviada_en' => $ahora],
        );

        $this->avisarAlSupervisor($cuenta, $contratista, $radicado);

        return ['ok' => true, 'mensaje' => $radicado];
    }

    /** El aviso al supervisor no rompe el envío si el correo está caído. */
    private function avisarAlSupervisor(array $cuenta, array $contratista, string $radicado): void
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
                'Cuenta de cobro ' . $radicado . ' pendiente de revisión',
                'El contratista ' . $contratista['nombre_completo'] . ' envió la cuenta de cobro N° '
                . (int) $cuenta['consecutivo'] . " (radicado {$radicado}).\n\n"
                . "Revísela en el panel: Cuentas de cobro.\n\n"
                . 'E.S.E. Hospital Rosario Pumarejo de López',
            );
        } catch (\Throwable) {
            // El envío de la cuenta ya quedó en firme; el correo caído no lo revierte.
        }
    }
}

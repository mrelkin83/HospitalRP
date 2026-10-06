<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Service;

use App\Modules\CuentasCobro\Entity\EstadoCuenta;
use App\Modules\CuentasCobro\Repository\CuentaRepository;
use App\Modules\CuentasCobro\Repository\EvidenciaActividadRepository;
use App\Ports\NotificadorCorreo;
use Doctrine\DBAL\Connection;

/**
 * Lo que hace el supervisor con una cuenta ENVIADA (§5 y §7.2): aprobarla
 * —genera el informe de supervisión GJ-FR-004 con su firma y arma el
 * paquete— o devolverla con una observación en lenguaje claro.
 *
 * Al aprobar se CONGELAN «pagos anteriores acumulados» y «saldo por
 * ejecutar» (§8.2): una corrección posterior de otra cuenta no puede
 * reescribir un informe ya firmado.
 */
final readonly class SupervisionCuentaService
{
    public function __construct(
        private Connection $conexion,
        private CuentaRepository $cuentas,
        private GeneradorDocumentosCuenta $generador,
        private CombinadorPaquete $combinador,
        private NotificadorCorreo $correo,
        private EvidenciaActividadRepository $evidencias,
    ) {
    }

    /**
     * @param array<string, mixed>    $cuenta       Fila de cc_cuentas.
     * @param array<string, mixed>    $supervisor   Fila de cc_supervisores.
     * @param array<int, string>      $cumplimiento obligacion_id => SI|NO|PARCIAL.
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function aprobar(
        array $cuenta,
        array $supervisor,
        array $cumplimiento,
        ?string $observacion,
        string $fechaInforme,
        string $ip,
        ?string $userAgent,
    ): array {
        $cuentaId = (int) $cuenta['id'];

        // El equipo de revisores va primero: sin su firma (REVISADA), el
        // supervisor no aprueba.
        if ((string) $cuenta['estado'] !== EstadoCuenta::REVISADA->value) {
            return ['ok' => false, 'mensaje' => 'Solo una cuenta que ya pasó la revisión previa se puede aprobar.'];
        }

        if (!is_string($supervisor['firma_archivo'] ?? null) || $supervisor['firma_archivo'] === '') {
            return ['ok' => false, 'mensaje' => 'Registre su firma antes de aprobar: el informe de supervisión sale firmado.'];
        }

        // El cumplimiento por obligación; No o Parcial exigen observación (§7.2).
        $hayReparo = false;

        foreach ($this->cuentas->actividades($cuentaId) as $actividad) {
            $marca = $cumplimiento[(int) $actividad['obligacion_id']] ?? 'SI';
            $marca = in_array($marca, ['SI', 'NO', 'PARCIAL'], true) ? $marca : 'SI';
            $hayReparo = $hayReparo || $marca !== 'SI';

            $this->conexion->update('cc_cuenta_actividades', ['cumplimiento' => $marca], [
                'cuenta_id' => $cuentaId,
                'obligacion_id' => (int) $actividad['obligacion_id'],
            ]);
        }

        if ($hayReparo && trim((string) $observacion) === '') {
            return ['ok' => false, 'mensaje' => 'Marcó obligaciones en No o Parcial: escriba la observación que lo sustenta.'];
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInforme) !== 1) {
            $fechaInforme = date('Y-m-d');
        }

        // La fecha y la observación entran ANTES de renderizar: el PDF las lleva.
        $this->cuentas->actualizar($cuentaId, [
            'fecha_informe_supervision' => $fechaInforme,
            'observacion_supervisor' => trim((string) $observacion) !== '' ? trim((string) $observacion) : null,
        ]);

        // Montos que se congelan (§8.2).
        $congelados = $this->montosCongelados($cuenta);

        // El informe de supervisión, firmado.
        $cuentaFresca = $this->cuentas->porId($cuentaId) ?? $cuenta;
        $contenidoFirmado = $this->generador->vistaPrevia('INFORME_SUPERVISION', $cuentaId);
        $verificacion = strtoupper(substr(hash('sha256', $contenidoFirmado), 0, 12));
        $renglon = sprintf(
            'Documento generado electrónicamente. Radicado %s. Firmado por %s el %s. Verificación: %s. Firma electrónica simple, Ley 527 de 1999 y Decreto 2364 de 2012.',
            (string) $cuentaFresca['radicado'],
            (string) $supervisor['nombre_completo'],
            date('d/m/Y H:i'),
            $verificacion,
        );
        $informe = $this->generador->generar('INFORME_SUPERVISION', $cuentaId, $renglon);

        $this->cuentas->registrarFirma($cuentaId, [
            'documento' => 'INFORME_SUPERVISION',
            'firmante_rol' => 'SUPERVISOR',
            'usuario_id' => (int) $supervisor['usuario_id'],
            'firma_archivo' => (string) $supervisor['firma_archivo'],
            'hash_pdf' => $informe['hash'],
            'ip' => $ip,
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 300),
        ]);

        // El paquete: los tres documentos y los anexos, en el orden de §11.4.
        $paquete = $this->combinador->combinar($this->documentosDelPaquete($cuentaFresca, $informe['ruta']));

        $this->cuentas->transicionar(
            $cuentaId,
            EstadoCuenta::REVISADA,
            EstadoCuenta::APROBADA,
            'SUPERVISOR',
            (int) $supervisor['usuario_id'],
            null,
            trim((string) $observacion) !== '' ? trim((string) $observacion) : null,
            $ip,
            [
                'aprobada_en' => date('Y-m-d H:i:s'),
                'aprobada_por' => (int) $supervisor['id'],
                'pagos_anteriores' => $congelados['pagos_anteriores'],
                'saldo_por_ejecutar' => $congelados['saldo'],
                'pdf_informe_supervision' => $informe['ruta'],
                'pdf_paquete' => $paquete['ruta'],
            ],
        );

        $mensaje = 'Cuenta aprobada. El paquete quedó listo para descargar.';

        if ($paquete['omitidos'] !== []) {
            $mensaje .= ' OJO: ' . count($paquete['omitidos']) . ' anexo(s) no pudieron entrar al combinado; descárguelos por separado.';
        }

        return ['ok' => true, 'mensaje' => $mensaje];
    }

    /**
     * Devolver al contratista, con el motivo en lenguaje claro (§7.2). El
     * contratista lo verá en su pantalla de inicio y por correo.
     *
     * @param array<string, mixed> $cuenta
     * @param array<string, mixed> $supervisor
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function devolver(array $cuenta, array $supervisor, string $observacion, string $ip): array
    {
        $estado = EstadoCuenta::from((string) $cuenta['estado']);

        // El supervisor puede devolver tanto la recién enviada como la que
        // ya pasó revisión y él encontró un reparo.
        if (!in_array($estado, [EstadoCuenta::ENVIADA, EstadoCuenta::REVISADA], true)) {
            return ['ok' => false, 'mensaje' => 'Solo una cuenta enviada o revisada se puede devolver.'];
        }

        $observacion = trim($observacion);

        if ($observacion === '') {
            return ['ok' => false, 'mensaje' => 'Escriba el motivo de la devolución: es lo que el contratista leerá para corregir.'];
        }

        $this->cuentas->transicionar(
            (int) $cuenta['id'],
            $estado,
            EstadoCuenta::DEVUELTA,
            'SUPERVISOR',
            (int) $supervisor['usuario_id'],
            null,
            $observacion,
            $ip,
            ['observacion_supervisor' => $observacion],
        );

        $this->avisarAlContratista($cuenta, $observacion);

        return ['ok' => true, 'mensaje' => 'Cuenta devuelta. El contratista verá el motivo en su portal.'];
    }

    /**
     * Marca informativa «Entregada a Financiera» (§5): no cambia nada más.
     *
     * @param array<string, mixed> $cuenta
     * @param array<string, mixed> $supervisor
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function marcarEntregada(array $cuenta, array $supervisor, string $ip): array
    {
        if ((string) $cuenta['estado'] !== EstadoCuenta::APROBADA->value) {
            return ['ok' => false, 'mensaje' => 'Solo una cuenta aprobada se marca como entregada a Financiera.'];
        }

        $this->cuentas->transicionar(
            (int) $cuenta['id'],
            EstadoCuenta::APROBADA,
            EstadoCuenta::ENTREGADA_FINANCIERA,
            'SUPERVISOR',
            (int) $supervisor['usuario_id'],
            null,
            null,
            $ip,
        );

        return ['ok' => true, 'mensaje' => 'Marcada como entregada a Financiera.'];
    }

    /**
     * Los documentos del paquete en el orden de §11.4: cuenta de cobro,
     * informe de actividades, informe de supervisión, planilla PILA y los
     * demás anexos.
     *
     * @param array<string, mixed> $cuenta
     *
     * @return list<array{ruta: string, mime: string}>
     */
    private function documentosDelPaquete(array $cuenta, string $rutaInformeSupervision): array
    {
        $documentos = [];

        foreach ([(string) $cuenta['pdf_cuenta_cobro'], (string) $cuenta['pdf_informe_actividades'], $rutaInformeSupervision] as $ruta) {
            if ($ruta !== '') {
                $documentos[] = ['ruta' => $ruta, 'mime' => 'application/pdf'];
            }
        }

        $adjuntos = $this->cuentas->adjuntos((int) $cuenta['id']);
        usort($adjuntos, static fn (array $a, array $b): int =>
            ((int) ($b['tipo'] === 'PLANILLA_PILA')) <=> ((int) ($a['tipo'] === 'PLANILLA_PILA')));

        foreach ($adjuntos as $adjunto) {
            $documentos[] = ['ruta' => (string) $adjunto['archivo'], 'mime' => (string) $adjunto['mime']];
        }

        // Las evidencias de las actividades van al final y en el orden de
        // las obligaciones, para que el paquete se lea igual que el
        // informe: fila 3 del informe, tercer bloque de anexos. Los
        // enlaces no se anexan —no son un archivo— y quedan en el informe.
        foreach ($this->evidencias->porCuenta((int) $cuenta['id']) as $deLaActividad) {
            foreach ($deLaActividad as $evidencia) {
                if ((string) $evidencia['tipo'] === 'ARCHIVO' && is_string($evidencia['archivo'])) {
                    $documentos[] = [
                        'ruta' => (string) $evidencia['archivo'],
                        'mime' => (string) ($evidencia['mime'] ?? 'application/pdf'),
                    ];
                }
            }
        }

        return $documentos;
    }

    /**
     * Pagos anteriores acumulados y saldo por ejecutar, listos para
     * congelarse (§8.2).
     *
     * @param array<string, mixed> $cuenta
     *
     * @return array{pagos_anteriores: int, saldo: int}
     */
    private function montosCongelados(array $cuenta): array
    {
        $contratoId = (int) $cuenta['contrato_id'];
        $valorTotal = (int) explode('.', (string) $this->conexion->fetchOne(
            'SELECT valor_total FROM cc_contratos WHERE id = ?',
            [$contratoId],
        ), 2)[0];
        $adiciones = (int) explode('.', (string) $this->conexion->fetchOne(
            "SELECT COALESCE(SUM(valor), 0) FROM cc_contrato_novedades WHERE contrato_id = ? AND tipo = 'ADICION'",
            [$contratoId],
        ), 2)[0];
        $pagosAnteriores = (int) explode('.', (string) $this->conexion->fetchOne(
            "SELECT COALESCE(SUM(valor), 0) FROM cc_cuentas
              WHERE contrato_id = ? AND estado IN ('APROBADA', 'ENTREGADA_FINANCIERA') AND consecutivo < ?",
            [$contratoId, (int) $cuenta['consecutivo']],
        ), 2)[0];
        $valorCuenta = (int) explode('.', (string) $cuenta['valor'], 2)[0];

        return [
            'pagos_anteriores' => $pagosAnteriores,
            'saldo' => $valorTotal + $adiciones - $pagosAnteriores - $valorCuenta,
        ];
    }

    /** El aviso de devolución no rompe la devolución si el correo falla. */
    private function avisarAlContratista(array $cuenta, string $observacion): void
    {
        $correo = $this->conexion->fetchOne(
            'SELECT t.correo FROM cc_contratos k JOIN cc_contratistas t ON t.id = k.contratista_id WHERE k.id = ?',
            [(int) $cuenta['contrato_id']],
        );

        if (!is_string($correo) || $correo === '') {
            return;
        }

        try {
            $this->correo->enviar(
                $correo,
                'Su cuenta de cobro necesita una corrección',
                'Su cuenta de cobro N° ' . (int) $cuenta['consecutivo'] . " fue devuelta por el supervisor.\n\n"
                . 'Motivo: ' . $observacion . "\n\n"
                . "Entre al portal, corrija y vuelva a enviarla:\n"
                . "solo tiene que ajustar lo señalado, lo demás queda guardado.\n\n"
                . 'E.S.E. Hospital Rosario Pumarejo de López',
            );
        } catch (\Throwable) {
            // La devolución ya quedó registrada; el portal la muestra igual.
        }
    }
}

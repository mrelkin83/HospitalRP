<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Service;

use App\Modules\CuentasCobro\Entity\EstadoContrato;
use App\Modules\CuentasCobro\Repository\ContratoRepository;
use Doctrine\DBAL\Connection;

/**
 * Las novedades del contrato (§8.1 y fase 4 del documento): adiciones que
 * agregan cuotas, prórrogas, suspensiones que prorratean y desplazan, el
 * reinicio y la terminación anticipada con prorrateo.
 *
 * Regla de oro heredada de §8.1: las cuotas que ya tienen una cuenta viva
 * (no anulada) NO se tocan. Solo se recalculan las futuras; si la novedad
 * cae sobre una cuota ya cobrada, se rechaza con un mensaje claro.
 */
final readonly class NovedadesContratoService
{
    public function __construct(
        private Connection $conexion,
        private ContratoRepository $contratos,
    ) {
    }

    /**
     * Adición: más valor y más plazo. El valor se reparte en cuotas
     * mensuales nuevas a continuación de la última, y los días sueltos
     * quedan como cuota parcial prorrateada (§8.2).
     *
     * @param array<string, mixed> $contrato
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function registrarAdicion(array $contrato, int $valor, int $plazoMeses, int $plazoDias, string $fecha, string $descripcion, int $usuarioId): array
    {
        if ((string) $contrato['estado'] !== EstadoContrato::ACTIVO->value) {
            return ['ok' => false, 'mensaje' => 'Solo un contrato activo admite adiciones.'];
        }

        if ($valor <= 0 || ($plazoMeses <= 0 && $plazoDias <= 0)) {
            return ['ok' => false, 'mensaje' => 'La adición necesita un valor y un plazo (meses o días).'];
        }

        $pagos = $this->contratos->pagos((int) $contrato['id']);

        if ($pagos === [] || $pagos[array_key_last($pagos)]['periodo_hasta'] === null) {
            return ['ok' => false, 'mensaje' => 'Registre primero el acta de inicio: sin periodos no hay dónde encadenar la adición.'];
        }

        // El valor mensual de la adición: el de sus meses completos. Con
        // solo días, el valor completo es el de la mensualidad del contrato.
        $mensualContrato = $this->valorMensualCompleto((int) $contrato['id']);
        $valorDias = $plazoDias > 0
            ? ($plazoMeses > 0 ? CalculosCuenta::prorrateo(intdiv($valor * 30, $plazoMeses * 30 + $plazoDias), $plazoDias) : $valor)
            : 0;
        $valorMensual = $plazoMeses > 0 ? intdiv($valor - $valorDias, max(1, $plazoMeses)) : 0;
        // El peso perdido por la división entera va a la primera cuota nueva.
        $ajuste = $valor - $valorDias - $valorMensual * $plazoMeses;

        return (array) $this->conexion->transactional(function () use ($contrato, $valor, $plazoMeses, $plazoDias, $fecha, $descripcion, $usuarioId, $pagos, $valorMensual, $valorDias, $ajuste): array {
            $contratoId = (int) $contrato['id'];

            $this->conexion->insert('cc_contrato_novedades', [
                'contrato_id' => $contratoId,
                'tipo' => 'ADICION',
                'fecha' => $fecha,
                'valor' => $valor,
                'plazo_meses' => $plazoMeses,
                'plazo_dias' => $plazoDias,
                'descripcion' => $descripcion !== '' ? mb_substr($descripcion, 0, 400) : null,
                'creado_por' => $usuarioId,
            ]);

            $ultima = $pagos[array_key_last($pagos)];
            $numero = (int) $ultima['numero_cuota'];
            $hastaAnterior = FechasCuenta::desdeMysql((string) $ultima['periodo_hasta']);
            $aniversario = (string) $contrato['modalidad_periodos'] === 'ANIVERSARIO';

            for ($mes = 1; $mes <= $plazoMeses; $mes++) {
                $desde = $hastaAnterior->modify('+1 day');
                $hasta = $aniversario
                    ? CalculosCuenta::sumarMeses($desde, 1)->modify('-1 day')
                    : $desde->modify('last day of this month');

                $this->conexion->insert('cc_contrato_pagos', [
                    'contrato_id' => $contratoId,
                    'numero_cuota' => ++$numero,
                    'descripcion' => ucfirst(FechasCuenta::mes($desde)) . ' de ' . $desde->format('Y') . ' (adición)',
                    'tipo' => 'COMPLETA',
                    'mes' => $desde->format('Y-m'),
                    'periodo_desde' => $desde->format('Y-m-d'),
                    'periodo_hasta' => $hasta->format('Y-m-d'),
                    'dias' => CalculosCuenta::diasEntre($desde, $hasta),
                    'valor' => $valorMensual + ($mes === 1 ? $ajuste : 0),
                ]);
                $hastaAnterior = $hasta;
            }

            if ($plazoDias > 0) {
                $desde = $hastaAnterior->modify('+1 day');
                $hasta = $desde->modify(sprintf('+%d days', $plazoDias - 1));

                $this->conexion->insert('cc_contrato_pagos', [
                    'contrato_id' => $contratoId,
                    'numero_cuota' => ++$numero,
                    'descripcion' => $plazoDias . ' días de ' . FechasCuenta::mes($desde) . ' de ' . $desde->format('Y') . ' (adición)',
                    'tipo' => 'PARCIAL',
                    'mes' => $desde->format('Y-m'),
                    'periodo_desde' => $desde->format('Y-m-d'),
                    'periodo_hasta' => $hasta->format('Y-m-d'),
                    'dias' => $plazoDias,
                    'valor' => $valorDias + ($plazoMeses === 0 ? 0 : 0),
                ]);
                $hastaAnterior = $hasta;
            }

            $this->conexion->update('cc_contratos', [
                'fecha_terminacion' => $hastaAnterior->format('Y-m-d'),
            ], ['id' => $contratoId]);

            return ['ok' => true, 'mensaje' => 'Adición registrada: el cronograma ganó sus cuotas y la terminación se movió al ' . $hastaAnterior->format('Y-m-d') . '. Registre el CDP y el RP de la adición.'];
        });
    }

    /**
     * Prórroga: más plazo sin más valor. Mueve la fecha de terminación;
     * el cronograma pactado no cambia (las cuotas valen lo mismo), así
     * que aquí no se recalcula nada más.
     *
     * @param array<string, mixed> $contrato
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function registrarProrroga(array $contrato, int $plazoMeses, int $plazoDias, string $fecha, string $descripcion, int $usuarioId): array
    {
        if ((string) $contrato['estado'] !== EstadoContrato::ACTIVO->value) {
            return ['ok' => false, 'mensaje' => 'Solo un contrato activo admite prórrogas.'];
        }

        if (($plazoMeses <= 0 && $plazoDias <= 0) || $contrato['fecha_terminacion'] === null) {
            return ['ok' => false, 'mensaje' => 'La prórroga necesita un plazo, y el contrato una fecha de terminación.'];
        }

        $nueva = CalculosCuenta::sumarMeses(FechasCuenta::desdeMysql((string) $contrato['fecha_terminacion']), $plazoMeses)
            ->modify(sprintf('+%d days', $plazoDias));

        $this->conexion->insert('cc_contrato_novedades', [
            'contrato_id' => (int) $contrato['id'],
            'tipo' => 'PRORROGA',
            'fecha' => $fecha,
            'plazo_meses' => $plazoMeses,
            'plazo_dias' => $plazoDias,
            'descripcion' => $descripcion !== '' ? mb_substr($descripcion, 0, 400) : null,
            'creado_por' => $usuarioId,
        ]);
        $this->conexion->update('cc_contratos', ['fecha_terminacion' => $nueva->format('Y-m-d')], ['id' => (int) $contrato['id']]);

        return ['ok' => true, 'mensaje' => 'Prórroga registrada: la terminación pasó al ' . $nueva->format('Y-m-d') . '.'];
    }

    /**
     * Suspensión (§8.1, parágrafo 2 de la cláusula 3): la cuota que
     * contiene la suspensión se prorratea a los días efectivamente
     * trabajados, las cuotas posteriores se desplazan tantos días como
     * duró la suspensión, y el valor no pagado queda como cuota remanente
     * al final. Ninguna cuenta ya presentada cambia.
     *
     * @param array<string, mixed> $contrato
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function registrarSuspension(array $contrato, string $desde, string $hasta, string $descripcion, int $usuarioId): array
    {
        if ((string) $contrato['estado'] !== EstadoContrato::ACTIVO->value) {
            return ['ok' => false, 'mensaje' => 'Solo un contrato activo se puede suspender.'];
        }

        try {
            $inicio = FechasCuenta::desdeMysql($desde);
            $fin = FechasCuenta::desdeMysql($hasta);
        } catch (\InvalidArgumentException) {
            return ['ok' => false, 'mensaje' => 'Las fechas de la suspensión no son válidas.'];
        }

        if ($fin <= $inicio) {
            return ['ok' => false, 'mensaje' => 'La suspensión debe terminar después de empezar.'];
        }

        // Días suspendidos: del inicio al reinicio, sin contar el día en
        // que se reanuda. Del 10 al 20 son 10 días, y la cuota de 30 queda
        // en 20 (criterio de aceptación de la fase).
        $diasSuspendidos = (int) $inicio->diff($fin)->days;
        $contratoId = (int) $contrato['id'];

        $contenedora = $this->conexion->fetchAssociative(
            'SELECT * FROM cc_contrato_pagos WHERE contrato_id = ? AND periodo_desde <= ? AND periodo_hasta >= ? ORDER BY numero_cuota LIMIT 1',
            [$contratoId, $inicio->format('Y-m-d'), $inicio->format('Y-m-d')],
        );

        if ($contenedora === false) {
            return ['ok' => false, 'mensaje' => 'La suspensión no cae dentro de ninguna cuota del cronograma.'];
        }

        if ($this->cuotaTieneCuentaViva((int) $contenedora['id'])) {
            return ['ok' => false, 'mensaje' => 'La cuota que contiene la suspensión ya tiene una cuenta presentada; anúlela primero o ajuste las fechas.'];
        }

        $mensual = $this->valorMensualCompleto($contratoId);
        $descuento = CalculosCuenta::prorrateo($mensual, $diasSuspendidos);

        return (array) $this->conexion->transactional(function () use ($contratoId, $inicio, $fin, $descripcion, $usuarioId, $contenedora, $diasSuspendidos, $descuento): array {
            $this->conexion->insert('cc_contrato_novedades', [
                'contrato_id' => $contratoId,
                'tipo' => 'SUSPENSION',
                'fecha' => $inicio->format('Y-m-d'),
                'fecha_fin' => $fin->format('Y-m-d'),
                'descripcion' => $descripcion !== '' ? mb_substr($descripcion, 0, 400) : null,
                'creado_por' => $usuarioId,
            ]);

            // La contenedora se prorratea a los días trabajados.
            $valorOriginal = ContratoRepository::pesos((string) $contenedora['valor']);
            $this->conexion->update('cc_contrato_pagos', [
                'dias' => max(0, (int) $contenedora['dias'] - $diasSuspendidos),
                'valor' => $valorOriginal - $descuento,
                'descripcion' => $contenedora['descripcion'] . ' (suspendido ' . $diasSuspendidos . ' días)',
                'tipo' => 'PARCIAL',
            ], ['id' => (int) $contenedora['id']]);

            // Las posteriores sin cuenta viva se desplazan.
            $posteriores = $this->conexion->fetchAllAssociative(
                'SELECT * FROM cc_contrato_pagos WHERE contrato_id = ? AND numero_cuota > ? ORDER BY numero_cuota',
                [$contratoId, (int) $contenedora['numero_cuota']],
            );
            $ultimoHasta = FechasCuenta::desdeMysql((string) $contenedora['periodo_hasta']);
            $numero = (int) $contenedora['numero_cuota'];

            foreach ($posteriores as $pago) {
                if ($this->cuotaTieneCuentaViva((int) $pago['id'])) {
                    // No debería pasar (las cuentas van en orden), pero si
                    // pasa, mejor frenar que pisar una cuenta en firme.
                    throw new \RuntimeException('Hay cuentas presentadas después de la suspensión; no se puede desplazar el cronograma.');
                }

                $desdeNuevo = FechasCuenta::desdeMysql((string) $pago['periodo_desde'])->modify(sprintf('+%d days', $diasSuspendidos));
                $hastaNuevo = FechasCuenta::desdeMysql((string) $pago['periodo_hasta'])->modify(sprintf('+%d days', $diasSuspendidos));

                $this->conexion->update('cc_contrato_pagos', [
                    'periodo_desde' => $desdeNuevo->format('Y-m-d'),
                    'periodo_hasta' => $hastaNuevo->format('Y-m-d'),
                ], ['id' => (int) $pago['id']]);
                $ultimoHasta = $hastaNuevo;
                $numero = (int) $pago['numero_cuota'];
            }

            // El valor descontado se recupera al final como remanente.
            $desdeRemanente = $ultimoHasta->modify('+1 day');
            $hastaRemanente = $desdeRemanente->modify(sprintf('+%d days', $diasSuspendidos - 1));
            $this->conexion->insert('cc_contrato_pagos', [
                'contrato_id' => $contratoId,
                'numero_cuota' => $numero + 1,
                'descripcion' => 'Remanente por suspensión (' . $diasSuspendidos . ' días)',
                'tipo' => 'PARCIAL',
                'mes' => $desdeRemanente->format('Y-m'),
                'periodo_desde' => $desdeRemanente->format('Y-m-d'),
                'periodo_hasta' => $hastaRemanente->format('Y-m-d'),
                'dias' => $diasSuspendidos,
                'valor' => $descuento,
            ]);

            $this->conexion->update('cc_contratos', [
                'estado' => EstadoContrato::SUSPENDIDO->value,
                'fecha_terminacion' => $hastaRemanente->format('Y-m-d'),
            ], ['id' => $contratoId]);

            return ['ok' => true, 'mensaje' => 'Suspensión registrada: la cuota afectada quedó prorrateada, las siguientes se desplazaron ' . $diasSuspendidos . ' días y el contrato quedó SUSPENDIDO. Registre el reinicio cuando se reanude.'];
        });
    }

    /**
     * Reinicio tras una suspensión: el contrato vuelve a ACTIVO. Los
     * periodos ya quedaron desplazados al registrar la suspensión.
     *
     * @param array<string, mixed> $contrato
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function registrarReinicio(array $contrato, string $fecha, int $usuarioId): array
    {
        if ((string) $contrato['estado'] !== EstadoContrato::SUSPENDIDO->value) {
            return ['ok' => false, 'mensaje' => 'Solo un contrato suspendido se puede reiniciar.'];
        }

        $this->conexion->insert('cc_contrato_novedades', [
            'contrato_id' => (int) $contrato['id'],
            'tipo' => 'REINICIO',
            'fecha' => $fecha,
            'creado_por' => $usuarioId,
        ]);
        $this->conexion->update('cc_contratos', ['estado' => EstadoContrato::ACTIVO->value], ['id' => (int) $contrato['id']]);

        return ['ok' => true, 'mensaje' => 'Contrato reiniciado: el contratista puede volver a presentar cuentas.'];
    }

    /**
     * Terminación anticipada (§8.2): la cuota que contiene la fecha se
     * prorratea a los días trabajados, las posteriores sin cuenta viva se
     * eliminan del cronograma y el contrato queda TERMINADO.
     *
     * @param array<string, mixed> $contrato
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function registrarTerminacionAnticipada(array $contrato, string $fecha, string $descripcion, int $usuarioId): array
    {
        if (!in_array((string) $contrato['estado'], [EstadoContrato::ACTIVO->value, EstadoContrato::SUSPENDIDO->value], true)) {
            return ['ok' => false, 'mensaje' => 'Solo un contrato activo o suspendido se puede terminar anticipadamente.'];
        }

        try {
            $corte = FechasCuenta::desdeMysql($fecha);
        } catch (\InvalidArgumentException) {
            return ['ok' => false, 'mensaje' => 'La fecha de terminación no es válida.'];
        }

        $contratoId = (int) $contrato['id'];
        $contenedora = $this->conexion->fetchAssociative(
            'SELECT * FROM cc_contrato_pagos WHERE contrato_id = ? AND periodo_desde <= ? AND periodo_hasta >= ? ORDER BY numero_cuota LIMIT 1',
            [$contratoId, $corte->format('Y-m-d'), $corte->format('Y-m-d')],
        );

        if ($contenedora === false) {
            return ['ok' => false, 'mensaje' => 'La fecha no cae dentro de ninguna cuota del cronograma.'];
        }

        if ($this->cuotaTieneCuentaViva((int) $contenedora['id'])) {
            return ['ok' => false, 'mensaje' => 'La cuota que contiene esa fecha ya tiene una cuenta presentada.'];
        }

        $mensual = $this->valorMensualCompleto($contratoId);

        return (array) $this->conexion->transactional(function () use ($contratoId, $corte, $fecha, $descripcion, $usuarioId, $contenedora, $mensual): array {
            $this->conexion->insert('cc_contrato_novedades', [
                'contrato_id' => $contratoId,
                'tipo' => 'TERMINACION_ANTICIPADA',
                'fecha' => $fecha,
                'descripcion' => $descripcion !== '' ? mb_substr($descripcion, 0, 400) : null,
                'creado_por' => $usuarioId,
            ]);

            // La contenedora se prorratea a los días realmente trabajados.
            $desde = FechasCuenta::desdeMysql((string) $contenedora['periodo_desde']);
            $dias = CalculosCuenta::diasEntre($desde, $corte);
            $this->conexion->update('cc_contrato_pagos', [
                'periodo_hasta' => $corte->format('Y-m-d'),
                'dias' => $dias,
                'valor' => CalculosCuenta::prorrateo($mensual, $dias),
                'descripcion' => $contenedora['descripcion'] . ' (terminación anticipada)',
                'tipo' => 'PARCIAL',
            ], ['id' => (int) $contenedora['id']]);

            // Las posteriores sin cuenta viva desaparecen del cronograma.
            $posteriores = $this->conexion->fetchAllAssociative(
                'SELECT id FROM cc_contrato_pagos WHERE contrato_id = ? AND numero_cuota > ?',
                [$contratoId, (int) $contenedora['numero_cuota']],
            );

            foreach ($posteriores as $pago) {
                if ($this->cuotaTieneCuentaViva((int) $pago['id'])) {
                    throw new \RuntimeException('Hay cuentas presentadas después de la fecha de terminación.');
                }

                $this->conexion->delete('cc_contrato_pagos', ['id' => (int) $pago['id']]);
            }

            $this->conexion->update('cc_contratos', [
                'estado' => EstadoContrato::TERMINADO->value,
                'fecha_terminacion' => $corte->format('Y-m-d'),
            ], ['id' => $contratoId]);

            return ['ok' => true, 'mensaje' => 'Terminación anticipada registrada: la última cuota quedó prorrateada y el contrato TERMINADO.'];
        });
    }

    /** @return list<array<string, mixed>> */
    public function novedadesDe(int $contratoId): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT n.*, u.nombre AS registrado_por
               FROM cc_contrato_novedades n
               LEFT JOIN usuarios u ON u.id = n.creado_por
              WHERE n.contrato_id = ?
              ORDER BY n.fecha, n.id',
            [$contratoId],
        );
    }

    // ------------------------------------------------------------------
    // Ayudas
    // ------------------------------------------------------------------

    private function cuotaTieneCuentaViva(int $pagoId): bool
    {
        return (int) $this->conexion->fetchOne(
            "SELECT COUNT(*) FROM cc_cuentas WHERE pago_id = ? AND estado <> 'ANULADA'",
            [$pagoId],
        ) > 0;
    }

    /** La mensualidad completa del contrato, base de todo prorrateo (§8.2). */
    private function valorMensualCompleto(int $contratoId): int
    {
        $valor = $this->conexion->fetchOne(
            "SELECT MAX(valor) FROM cc_contrato_pagos WHERE contrato_id = ? AND tipo = 'COMPLETA'",
            [$contratoId],
        );

        return $valor === false || $valor === null ? 0 : ContratoRepository::pesos((string) $valor);
    }
}

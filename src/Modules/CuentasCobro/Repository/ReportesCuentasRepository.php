<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Repository;

use Doctrine\DBAL\Connection;

/**
 * Los reportes de la pantalla A8 (fase 4): lo que la administración
 * necesita ver de un vistazo y exportar a CSV para sus informes.
 */
final readonly class ReportesCuentasRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    /** @return list<array<string, mixed>> Cuentas por estado y por mes de envío. */
    public function cuentasPorEstadoYMes(): array
    {
        return $this->conexion->fetchAllAssociative(
            "SELECT DATE_FORMAT(COALESCE(c.enviada_en, c.creado_en), '%Y-%m') AS mes,
                    c.estado, COUNT(*) AS cuentas, SUM(c.valor) AS valor_total
               FROM cc_cuentas c
              GROUP BY mes, c.estado
              ORDER BY mes DESC, c.estado",
        );
    }

    /**
     * Tiempo promedio entre envío y aprobación, por supervisor: el
     * termómetro de la fase (criterio de aceptación: exporta CSV).
     *
     * @return list<array<string, mixed>>
     */
    public function tiemposPorSupervisor(): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT s.nombre_completo AS supervisor, s.cargo,
                    COUNT(*) AS cuentas_aprobadas,
                    ROUND(AVG(TIMESTAMPDIFF(HOUR, c.enviada_en, c.aprobada_en)) / 24, 1) AS dias_promedio,
                    ROUND(MAX(TIMESTAMPDIFF(HOUR, c.enviada_en, c.aprobada_en)) / 24, 1) AS dias_maximo
               FROM cc_cuentas c
               JOIN cc_supervisores s ON s.id = c.aprobada_por
              WHERE c.aprobada_en IS NOT NULL AND c.enviada_en IS NOT NULL
              GROUP BY s.id
              ORDER BY dias_promedio DESC',
        );
    }

    /** @return list<array<string, mixed>> Contratos activos sin acta de inicio. */
    public function contratosSinActa(): array
    {
        return $this->conexion->fetchAllAssociative(
            "SELECT k.numero, k.fecha_suscripcion, t.nombre_completo AS contratista,
                    s.nombre_completo AS supervisor
               FROM cc_contratos k
               JOIN cc_contratistas t ON t.id = k.contratista_id
               LEFT JOIN cc_supervisores s ON s.id = k.supervisor_id
              WHERE k.estado = 'ACTIVO' AND k.fecha_acta_inicio IS NULL
              ORDER BY k.fecha_suscripcion",
        );
    }

    /** @return list<array<string, mixed>> Cuotas ya cobrables sin cuenta presentada. */
    public function cuotasVencidasSinCuenta(): array
    {
        return $this->conexion->fetchAllAssociative(
            "SELECT k.numero AS contrato, t.nombre_completo AS contratista,
                    p.numero_cuota, p.periodo_desde, p.periodo_hasta, p.valor,
                    DATEDIFF(CURDATE(), p.periodo_hasta) AS dias_vencida
               FROM cc_contrato_pagos p
               JOIN cc_contratos k ON k.id = p.contrato_id AND k.estado = 'ACTIVO'
               JOIN cc_contratistas t ON t.id = k.contratista_id
              WHERE p.periodo_hasta IS NOT NULL AND p.periodo_hasta < CURDATE()
                AND NOT EXISTS (
                    SELECT 1 FROM cc_cuentas c WHERE c.pago_id = p.id AND c.estado <> 'ANULADA'
                )
              ORDER BY p.periodo_hasta",
        );
    }
}

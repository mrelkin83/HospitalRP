<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Service;

use Doctrine\DBAL\Connection;

/**
 * Estado de ejecución de los contratos, para publicarlo (ITA 5.3).
 *
 * Es el **servicio público del módulo** que exige CLAUDE.md §4: el módulo
 * Transparencia necesita este dato pero no puede importar los repositorios de
 * CuentasCobro, así que entra por aquí.
 *
 * El criterio 5.3 pide seis cosas por contrato: fecha de inicio y finalización
 * (a), valor (b), porcentaje de ejecución (c), recursos desembolsados (d),
 * pendientes de ejecutar (e) y cantidad de otrosíes y adiciones con sus montos
 * (f). Los seis salen de datos que el módulo ya tenía; lo que faltaba era
 * exponerlos.
 *
 * **No se publica el nombre del contratista.** El criterio no lo pide —la
 * identidad y los honorarios de los contratistas son el criterio 3.5.j, que se
 * publica en el directorio de servidores públicos—, y repetir el dato personal
 * en una segunda pantalla sería tratarlo sin necesidad.
 *
 * Solo entran contratos ya perfeccionados. Los `EN_REVISION` todavía se están
 * cargando desde la minuta y publicar sus cifras sería publicar un borrador;
 * los `ANULADO` nunca existieron.
 */
final readonly class EjecucionContractualPublica
{
    private const string ESTADOS_PUBLICABLES = "'ACTIVO','SUSPENDIDO','TERMINADO','LIQUIDADO'";

    /** Una cuenta cuenta como desembolso cuando el supervisor ya la aprobó. */
    private const string ESTADOS_PAGADOS = "'APROBADA','ENTREGADA_FINANCIERA'";

    public function __construct(private Connection $conexion)
    {
    }

    /**
     * Una fila por contrato, con los seis literales del criterio ya calculados.
     *
     * @return list<array<string, mixed>>
     */
    public function contratos(): array
    {
        $filas = $this->conexion->fetchAllAssociative(sprintf(
            "SELECT c.numero,
                    c.tipo_contrato,
                    c.seccion_ubicacion,
                    c.fecha_acta_inicio,
                    c.fecha_terminacion,
                    c.valor_total,
                    c.estado,
                    COALESCE(p.pagado, 0)           AS pagado,
                    COALESCE(n.cantidad, 0)         AS novedades,
                    COALESCE(n.valor_adiciones, 0)  AS valor_adiciones
               FROM cc_contratos c
               LEFT JOIN (
                    SELECT contrato_id, SUM(valor) AS pagado
                      FROM cc_cuentas
                     WHERE estado IN (%s)
                     GROUP BY contrato_id
               ) p ON p.contrato_id = c.id
               LEFT JOIN (
                    SELECT contrato_id,
                           COUNT(*) AS cantidad,
                           SUM(COALESCE(valor, 0)) AS valor_adiciones
                      FROM cc_contrato_novedades
                     WHERE tipo IN ('ADICION', 'PRORROGA')
                     GROUP BY contrato_id
               ) n ON n.contrato_id = c.id
              WHERE c.estado IN (%s)
              ORDER BY c.fecha_acta_inicio DESC, c.numero",
            self::ESTADOS_PAGADOS,
            self::ESTADOS_PUBLICABLES,
        ));

        return array_map(static function (array $f): array {
            $valor = (float) $f['valor_total'];
            $pagado = (float) $f['pagado'];

            // El valor del contrato ya incluye sus adiciones, así que el
            // pendiente es valor menos pagado y no hay que sumarlas otra vez.
            // Un contrato sin valor (no debería existir) daría división por
            // cero: se reporta 0 % en vez de reventar la página pública.
            $f['pendiente'] = max(0.0, $valor - $pagado);
            $f['porcentaje'] = $valor > 0 ? min(100.0, round($pagado / $valor * 100, 1)) : 0.0;

            return $f;
        }, $filas);
    }

    /**
     * Totales de la vigencia, para encabezar la página.
     *
     * @return array{contratos: int, valor: float, pagado: float, pendiente: float}
     */
    public function resumen(): array
    {
        $contratos = $this->contratos();

        $valor = array_sum(array_map(static fn (array $c): float => (float) $c['valor_total'], $contratos));
        $pagado = array_sum(array_map(static fn (array $c): float => (float) $c['pagado'], $contratos));

        return [
            'contratos' => count($contratos),
            'valor' => $valor,
            'pagado' => $pagado,
            'pendiente' => max(0.0, $valor - $pagado),
        ];
    }
}

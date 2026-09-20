<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Repository;

use App\Modules\CuentasCobro\Entity\Cuota;
use App\Modules\CuentasCobro\Entity\EstadoContrato;
use App\Modules\CuentasCobro\Entity\PeriodoCuota;
use App\Modules\CuentasCobro\Entity\TipoCuota;
use Doctrine\DBAL\Connection;

/**
 * El contrato y sus tres listas satélite: imputaciones (CDP/RP),
 * obligaciones y cronograma de pagos.
 *
 * El contrato se carga UNA vez y de aquí beben los tres PDF de cada cuenta
 * mensual; por eso las escrituras fuertes (reemplazar obligaciones o
 * cuotas) solo proceden mientras no haya cuentas en firme.
 */
final readonly class ContratoRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    /** @return list<array<string, mixed>> */
    public function listar(?string $estado = null, ?string $busqueda = null): array
    {
        $sql = 'SELECT k.*, c.nombre_completo AS contratista_nombre, c.numero_documento,
                       s.nombre_completo AS supervisor_nombre
                  FROM cc_contratos k
                  JOIN cc_contratistas c ON c.id = k.contratista_id
                  LEFT JOIN cc_supervisores s ON s.id = k.supervisor_id';
        $condiciones = [];
        $parametros = [];

        if ($estado !== null && $estado !== '') {
            $condiciones[] = 'k.estado = ?';
            $parametros[] = $estado;
        }

        if ($busqueda !== null && trim($busqueda) !== '') {
            $condiciones[] = '(k.numero LIKE ? OR c.nombre_completo LIKE ? OR c.numero_documento LIKE ?)';
            $comodin = '%' . trim($busqueda) . '%';
            array_push($parametros, $comodin, $comodin, $comodin);
        }

        if ($condiciones !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $condiciones);
        }

        return $this->conexion->fetchAllAssociative($sql . ' ORDER BY k.fecha_suscripcion DESC, k.numero DESC', $parametros);
    }

    /** @return ?array<string, mixed> */
    public function porId(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT k.*, c.nombre_completo AS contratista_nombre, c.numero_documento, c.tipo_documento,
                    c.clase_riesgo_arl, s.nombre_completo AS supervisor_nombre, s.cargo AS supervisor_cargo
               FROM cc_contratos k
               JOIN cc_contratistas c ON c.id = k.contratista_id
               LEFT JOIN cc_supervisores s ON s.id = k.supervisor_id
              WHERE k.id = ?',
            [$id],
        );

        return $fila === false ? null : $fila;
    }

    /** @return ?array<string, mixed> */
    public function porNumero(string $numero): ?array
    {
        $fila = $this->conexion->fetchAssociative('SELECT * FROM cc_contratos WHERE numero = ?', [$numero]);

        return $fila === false ? null : $fila;
    }

    /** @param array<string, mixed> $campos */
    public function crear(array $campos): int
    {
        $this->conexion->insert('cc_contratos', $campos);

        return (int) $this->conexion->lastInsertId();
    }

    /** @param array<string, mixed> $campos */
    public function actualizar(int $id, array $campos): void
    {
        if ($campos !== []) {
            $this->conexion->update('cc_contratos', $campos, ['id' => $id]);
        }
    }

    // ------------------------------------------------------------------
    // Imputaciones (CDP y RP)
    // ------------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    public function imputaciones(int $contratoId): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT * FROM cc_contrato_imputaciones WHERE contrato_id = ? ORDER BY tipo, fecha, id',
            [$contratoId],
        );
    }

    /** @param array<string, mixed> $campos */
    public function agregarImputacion(int $contratoId, array $campos): int
    {
        $this->conexion->insert('cc_contrato_imputaciones', ['contrato_id' => $contratoId] + $campos);

        return (int) $this->conexion->lastInsertId();
    }

    public function contarImputaciones(int $contratoId, string $tipo): int
    {
        return (int) $this->conexion->fetchOne(
            'SELECT COUNT(*) FROM cc_contrato_imputaciones WHERE contrato_id = ? AND tipo = ?',
            [$contratoId, $tipo],
        );
    }

    // ------------------------------------------------------------------
    // Obligaciones
    // ------------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    public function obligaciones(int $contratoId, ?string $grupo = null): array
    {
        $sql = 'SELECT * FROM cc_contrato_obligaciones WHERE contrato_id = ?';
        $parametros = [$contratoId];

        if ($grupo !== null) {
            $sql .= ' AND grupo = ?';
            $parametros[] = $grupo;
        }

        return $this->conexion->fetchAllAssociative($sql . ' ORDER BY grupo, orden', $parametros);
    }

    /**
     * Reemplaza la lista completa de un grupo. Solo tiene sentido mientras
     * el contrato está EN_REVISION: después, las cuentas ya las referencian.
     *
     * @param list<string> $textos En el orden de la minuta.
     */
    public function reemplazarObligaciones(int $contratoId, array $textos, string $grupo = 'ESPECIFICA'): void
    {
        $this->conexion->executeStatement(
            'DELETE FROM cc_contrato_obligaciones WHERE contrato_id = ? AND grupo = ?',
            [$contratoId, $grupo],
        );

        foreach (array_values($textos) as $i => $texto) {
            $this->conexion->insert('cc_contrato_obligaciones', [
                'contrato_id' => $contratoId,
                'orden' => $i + 1,
                'grupo' => $grupo,
                'texto' => $texto,
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Cronograma de pagos
    // ------------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    public function pagos(int $contratoId): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT * FROM cc_contrato_pagos WHERE contrato_id = ? ORDER BY numero_cuota',
            [$contratoId],
        );
    }

    /**
     * @param list<array{numero_cuota: int, descripcion: string, tipo: string, mes: ?string, valor: int}> $cuotas
     */
    public function reemplazarPagos(int $contratoId, array $cuotas): void
    {
        $this->conexion->executeStatement('DELETE FROM cc_contrato_pagos WHERE contrato_id = ?', [$contratoId]);

        foreach ($cuotas as $cuota) {
            $this->conexion->insert('cc_contrato_pagos', ['contrato_id' => $contratoId] + $cuota);
        }
    }

    /**
     * Las cuotas guardadas, como objetos listos para CalculosCuenta.
     *
     * @return list<Cuota>
     */
    public function cuotas(int $contratoId): array
    {
        return array_map(
            static fn (array $fila): Cuota => new Cuota(
                numero: (int) $fila['numero_cuota'],
                descripcion: (string) $fila['descripcion'],
                valor: self::pesos((string) $fila['valor']),
                tipo: TipoCuota::from((string) $fila['tipo']),
                mes: $fila['mes'] === null ? null : (string) $fila['mes'],
            ),
            $this->pagos($contratoId),
        );
    }

    /**
     * Registra el acta de inicio: fija los periodos de cada cuota y la
     * fecha de terminación, todo en una transacción para que un fallo a
     * mitad no deje medio cronograma.
     *
     * @param list<PeriodoCuota> $periodos
     */
    public function aplicarActaInicio(int $contratoId, \DateTimeImmutable $fechaActa, array $periodos, \DateTimeImmutable $fechaTerminacion): void
    {
        $this->conexion->transactional(function () use ($contratoId, $fechaActa, $periodos, $fechaTerminacion): void {
            foreach ($periodos as $periodo) {
                $this->conexion->update('cc_contrato_pagos', [
                    'periodo_desde' => $periodo->desde->format('Y-m-d'),
                    'periodo_hasta' => $periodo->hasta->format('Y-m-d'),
                    'dias' => $periodo->dias,
                ], [
                    'contrato_id' => $contratoId,
                    'numero_cuota' => $periodo->numeroCuota,
                ]);
            }

            $this->conexion->update('cc_contratos', [
                'fecha_acta_inicio' => $fechaActa->format('Y-m-d'),
                'fecha_terminacion' => $fechaTerminacion->format('Y-m-d'),
            ], ['id' => $contratoId]);
        });
    }

    // ------------------------------------------------------------------
    // Estado y validaciones
    // ------------------------------------------------------------------

    public function cambiarEstado(int $id, EstadoContrato $estado): void
    {
        $this->conexion->update('cc_contratos', ['estado' => $estado->value], ['id' => $id]);
    }

    public function activar(int $id, int $usuarioId): void
    {
        $this->conexion->update('cc_contratos', [
            'estado' => EstadoContrato::ACTIVO->value,
            'activado_en' => date('Y-m-d H:i:s'),
            'activado_por' => $usuarioId,
        ], ['id' => $id]);
    }

    /** Cuentas que ya no se pueden deshacer: bloquean recálculos totales. */
    public function tieneCuentasEnFirme(int $contratoId): bool
    {
        return (int) $this->conexion->fetchOne(
            "SELECT COUNT(*) FROM cc_cuentas
              WHERE contrato_id = ? AND estado IN ('ENVIADA', 'APROBADA', 'ENTREGADA_FINANCIERA')",
            [$contratoId],
        ) > 0;
    }

    /** Suma de adiciones registradas, en pesos (0 mientras no haya novedades). */
    public function totalAdiciones(int $contratoId): int
    {
        $suma = $this->conexion->fetchOne(
            "SELECT COALESCE(SUM(valor), 0) FROM cc_contrato_novedades
              WHERE contrato_id = ? AND tipo = 'ADICION'",
            [$contratoId],
        );

        return self::pesos((string) $suma);
    }

    /**
     * DECIMAL(14,2) llega como cadena «2850000.00»; a pesos enteros sin
     * pasar por float. Los contratos del hospital no traen centavos.
     */
    public static function pesos(string $decimal): int
    {
        return (int) explode('.', $decimal, 2)[0];
    }
}

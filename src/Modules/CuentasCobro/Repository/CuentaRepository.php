<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Repository;

use App\Modules\CuentasCobro\Entity\EstadoCuenta;
use Doctrine\DBAL\Connection;

/**
 * Las cuentas de cobro y sus satélites: actividades por obligación,
 * seguridad social, adjuntos e historial.
 *
 * El borrador se crea al entrar a «Qué hizo este mes» (§5) y guarda cada
 * pocos segundos: si el contratista cierra el navegador, al volver retoma
 * donde iba. Nada se borra; una cuenta equivocada se anula.
 */
final readonly class CuentaRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    /** @return ?array<string, mixed> */
    public function porId(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative('SELECT * FROM cc_cuentas WHERE id = ?', [$id]);

        return $fila === false ? null : $fila;
    }

    /** @return list<array<string, mixed>> Todas las del contrato, recientes primero. */
    public function delContrato(int $contratoId): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT * FROM cc_cuentas WHERE contrato_id = ? ORDER BY consecutivo DESC',
            [$contratoId],
        );
    }

    /** La cuenta que el contratista puede seguir editando, si existe. */
    public function editableDelContrato(int $contratoId): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            "SELECT * FROM cc_cuentas WHERE contrato_id = ? AND estado IN ('BORRADOR', 'DEVUELTA') ORDER BY consecutivo DESC LIMIT 1",
            [$contratoId],
        );

        return $fila === false ? null : $fila;
    }

    /** La cuenta en manos del supervisor, si existe. */
    public function enRevisionDelContrato(int $contratoId): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            "SELECT * FROM cc_cuentas WHERE contrato_id = ? AND estado = 'ENVIADA' ORDER BY consecutivo DESC LIMIT 1",
            [$contratoId],
        );

        return $fila === false ? null : $fila;
    }

    /**
     * La siguiente cuota que ya puede cobrarse: la primera sin cuenta viva
     * cuyo periodo termina dentro de 5 días o menos (§7.1 P1).
     *
     * @return ?array<string, mixed> Fila de cc_contrato_pagos.
     */
    public function proximaCuotaDisponible(int $contratoId): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            "SELECT p.* FROM cc_contrato_pagos p
              WHERE p.contrato_id = ?
                AND p.periodo_hasta IS NOT NULL
                AND p.periodo_hasta <= DATE_ADD(CURDATE(), INTERVAL 5 DAY)
                AND NOT EXISTS (
                    SELECT 1 FROM cc_cuentas c
                     WHERE c.pago_id = p.id AND c.estado <> 'ANULADA'
                )
              ORDER BY p.numero_cuota LIMIT 1",
            [$contratoId],
        );

        return $fila === false ? null : $fila;
    }

    /** La primera cuota futura sin cuenta, para decir «podrá presentarse desde…». */
    public function proximaCuotaFutura(int $contratoId): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            "SELECT p.* FROM cc_contrato_pagos p
              WHERE p.contrato_id = ?
                AND p.periodo_hasta IS NOT NULL
                AND NOT EXISTS (
                    SELECT 1 FROM cc_cuentas c
                     WHERE c.pago_id = p.id AND c.estado <> 'ANULADA'
                )
              ORDER BY p.numero_cuota LIMIT 1",
            [$contratoId],
        );

        return $fila === false ? null : $fila;
    }

    /**
     * Crea el borrador de la cuenta para una cuota, con su consecutivo y
     * una fila de actividad por obligación específica, precargada con lo
     * escrito en la última cuenta aprobada del contrato (§7.1 P3).
     *
     * @param array<string, mixed> $pago Fila de cc_contrato_pagos.
     */
    public function crearBorrador(int $contratoId, array $pago, string $valorLetras): int
    {
        return (int) $this->conexion->transactional(function () use ($contratoId, $pago, $valorLetras): int {
            $consecutivo = 1 + (int) $this->conexion->fetchOne(
                "SELECT COUNT(*) FROM cc_cuentas WHERE contrato_id = ? AND estado <> 'ANULADA'",
                [$contratoId],
            );

            $this->conexion->insert('cc_cuentas', [
                'contrato_id' => $contratoId,
                'pago_id' => (int) $pago['id'],
                'consecutivo' => $consecutivo,
                'periodo_desde' => $pago['periodo_desde'],
                'periodo_hasta' => $pago['periodo_hasta'],
                'dias_periodo' => (int) ($pago['dias'] ?? 30),
                'fecha_cobro' => $pago['periodo_hasta'],
                'valor' => $pago['valor'],
                'valor_letras' => $valorLetras,
            ]);
            $cuentaId = (int) $this->conexion->lastInsertId();

            // Precarga: lo del mes pasado aprobado, obligación por
            // obligación. «Copiamos lo del mes pasado; cámbielo si hizo
            // algo diferente.»
            $previas = $this->conexion->fetchAllKeyValue(
                "SELECT a.obligacion_id, a.descripcion_ejecucion
                   FROM cc_cuenta_actividades a
                   JOIN cc_cuentas c ON c.id = a.cuenta_id
                  WHERE c.contrato_id = ? AND c.estado = 'APROBADA'
                  ORDER BY c.consecutivo",
                [$contratoId],
            );

            $obligaciones = $this->conexion->fetchAllAssociative(
                "SELECT id FROM cc_contrato_obligaciones
                  WHERE contrato_id = ? AND grupo = 'ESPECIFICA' AND activa = 1
                  ORDER BY orden",
                [$contratoId],
            );

            foreach ($obligaciones as $obligacion) {
                $this->conexion->insert('cc_cuenta_actividades', [
                    'cuenta_id' => $cuentaId,
                    'obligacion_id' => (int) $obligacion['id'],
                    'descripcion_ejecucion' => $previas[(int) $obligacion['id']] ?? null,
                ]);
            }

            return $cuentaId;
        });
    }

    /**
     * La bandeja del supervisor (S1): las cuentas de SUS contratos, con
     * los días que llevan esperando.
     *
     * @param ?list<string> $estados Filtro de pestaña; nulo = todas.
     *
     * @return list<array<string, mixed>>
     */
    public function bandejaDelSupervisor(int $supervisorId, ?array $estados = null): array
    {
        $sql = "SELECT c.*, k.numero AS numero_contrato, t.nombre_completo AS contratista_nombre,
                       DATEDIFF(NOW(), c.enviada_en) AS dias_en_espera
                  FROM cc_cuentas c
                  JOIN cc_contratos k ON k.id = c.contrato_id
                  JOIN cc_contratistas t ON t.id = k.contratista_id
                 WHERE k.supervisor_id = ?";
        $parametros = [$supervisorId];

        if ($estados !== null && $estados !== []) {
            $sql .= ' AND c.estado IN (' . implode(', ', array_fill(0, count($estados), '?')) . ')';
            array_push($parametros, ...$estados);
        }

        return $this->conexion->fetchAllAssociative(
            $sql . ' ORDER BY c.enviada_en IS NULL, c.enviada_en, c.id',
            $parametros,
        );
    }

    /** @return ?array<string, mixed> Un adjunto con la cuenta y el contrato a los que pertenece. */
    public function adjuntoPorId(int $adjuntoId): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT a.*, c.contrato_id, k.supervisor_id
               FROM cc_cuenta_adjuntos a
               JOIN cc_cuentas c ON c.id = a.cuenta_id
               JOIN cc_contratos k ON k.id = c.contrato_id
              WHERE a.id = ?',
            [$adjuntoId],
        );

        return $fila === false ? null : $fila;
    }

    /** @return list<array<string, mixed>> Actividades con su obligación, en orden. */
    public function actividades(int $cuentaId): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT a.id, a.obligacion_id, a.descripcion_ejecucion, a.cumplimiento, o.orden, o.texto
               FROM cc_cuenta_actividades a
               JOIN cc_contrato_obligaciones o ON o.id = a.obligacion_id
              WHERE a.cuenta_id = ?
              ORDER BY o.orden',
            [$cuentaId],
        );
    }

    public function guardarActividad(int $cuentaId, int $obligacionId, ?string $descripcion): void
    {
        $this->conexion->update('cc_cuenta_actividades', [
            'descripcion_ejecucion' => $descripcion,
        ], ['cuenta_id' => $cuentaId, 'obligacion_id' => $obligacionId]);
    }

    /** @return ?array<string, mixed> */
    public function seguridadSocial(int $cuentaId): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM cc_cuenta_seguridad_social WHERE cuenta_id = ?',
            [$cuentaId],
        );

        return $fila === false ? null : $fila;
    }

    /** @param array<string, mixed> $campos */
    public function guardarSeguridadSocial(int $cuentaId, array $campos): void
    {
        if ($this->seguridadSocial($cuentaId) !== null) {
            $this->conexion->update('cc_cuenta_seguridad_social', $campos, ['cuenta_id' => $cuentaId]);
        } else {
            $this->conexion->insert('cc_cuenta_seguridad_social', ['cuenta_id' => $cuentaId] + $campos);
        }
    }

    /** @param array<string, mixed> $campos */
    public function agregarAdjunto(int $cuentaId, array $campos): int
    {
        $this->conexion->insert('cc_cuenta_adjuntos', ['cuenta_id' => $cuentaId] + $campos);

        return (int) $this->conexion->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function adjuntos(int $cuentaId, ?string $tipo = null): array
    {
        $sql = 'SELECT * FROM cc_cuenta_adjuntos WHERE cuenta_id = ?';
        $parametros = [$cuentaId];

        if ($tipo !== null) {
            $sql .= ' AND tipo = ?';
            $parametros[] = $tipo;
        }

        return $this->conexion->fetchAllAssociative($sql . ' ORDER BY id', $parametros);
    }

    /** @param array<string, mixed> $campos */
    public function actualizar(int $cuentaId, array $campos): void
    {
        if ($campos !== []) {
            $this->conexion->update('cc_cuentas', $campos, ['id' => $cuentaId]);
        }
    }

    /**
     * Una transición del flujo con su rastro (§5): estado nuevo en la
     * cuenta y fila en el historial, juntos o ninguno.
     *
     * @param array<string, mixed> $camposExtra Columnas que cambian con el estado.
     */
    public function transicionar(
        int $cuentaId,
        ?EstadoCuenta $anterior,
        EstadoCuenta $nuevo,
        string $rol,
        ?int $usuarioId,
        ?int $contratistaId,
        ?string $observacion,
        ?string $ip,
        array $camposExtra = [],
    ): void {
        $this->conexion->transactional(function () use ($cuentaId, $anterior, $nuevo, $rol, $usuarioId, $contratistaId, $observacion, $ip, $camposExtra): void {
            $this->conexion->update('cc_cuentas', ['estado' => $nuevo->value] + $camposExtra, ['id' => $cuentaId]);
            $this->conexion->insert('cc_cuenta_historial', [
                'cuenta_id' => $cuentaId,
                'estado_anterior' => $anterior?->value,
                'estado_nuevo' => $nuevo->value,
                'usuario_id' => $usuarioId,
                'contratista_id' => $contratistaId,
                'rol' => $rol,
                'observacion' => $observacion,
                'ip' => $ip,
            ]);
        });
    }

    /** @param array<string, mixed> $campos */
    public function registrarFirma(int $cuentaId, array $campos): void
    {
        $this->conexion->insert('cc_cuenta_firmas', ['cuenta_id' => $cuentaId] + $campos);
    }

    /** Crea un token de descarga de un solo uso y devuelve el token en claro. */
    public function crearTokenDescarga(int $cuentaId, string $documento, int $diasVigencia = 7): string
    {
        $token = bin2hex(random_bytes(32));

        $this->conexion->insert('cc_descarga_tokens', [
            'cuenta_id' => $cuentaId,
            'documento' => $documento,
            'token_hash' => hash('sha256', $token),
            'expira_en' => date('Y-m-d H:i:s', time() + $diasVigencia * 86400),
        ]);

        return $token;
    }

    /**
     * Consume un token de descarga: válido, vigente y sin usar. Devuelve
     * la fila del token o nulo. Consumir y validar van juntos a propósito:
     * el enlace de WhatsApp es de UN SOLO USO (§9).
     *
     * @return ?array<string, mixed>
     */
    public function consumirTokenDescarga(string $token): ?array
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return null;
        }

        return $this->conexion->transactional(function () use ($token): ?array {
            $fila = $this->conexion->fetchAssociative(
                'SELECT * FROM cc_descarga_tokens WHERE token_hash = ? AND usado_en IS NULL AND expira_en > NOW() FOR UPDATE',
                [hash('sha256', $token)],
            );

            if ($fila === false) {
                return null;
            }

            $this->conexion->update('cc_descarga_tokens', ['usado_en' => date('Y-m-d H:i:s')], ['id' => $fila['id']]);

            return $fila;
        });
    }
}

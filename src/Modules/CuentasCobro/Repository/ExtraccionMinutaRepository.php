<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Repository;

use Doctrine\DBAL\Connection;

/**
 * Los trabajos de extracción de minutas (`cc_extracciones`): cada PDF
 * subido, su estado, el JSON extraído y las alertas de inconsistencia.
 */
final readonly class ExtraccionMinutaRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    public function crear(string $minutaArchivo, int $usuarioId): int
    {
        $this->conexion->insert('cc_extracciones', [
            'minuta_archivo' => $minutaArchivo,
            'creado_por' => $usuarioId,
        ]);

        return (int) $this->conexion->lastInsertId();
    }

    /**
     * Una extracción con el nombre de quien la tiene tomada: la pantalla
     * de revisión necesita decir «la tiene Fulano», no un id.
     *
     * @return ?array<string, mixed>
     */
    public function porId(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT e.*, a.nombre AS asignada_a_nombre
               FROM cc_extracciones e
               LEFT JOIN usuarios a ON a.id = e.asignado_a
              WHERE e.id = ?',
            [$id],
        );

        return $fila === false ? null : $fila;
    }

    /** @return list<array<string, mixed>> */
    public function recientes(int $limite = 30): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT e.*, u.nombre AS subida_por, k.numero AS numero_contrato
               FROM cc_extracciones e
               LEFT JOIN usuarios u ON u.id = e.creado_por
               LEFT JOIN cc_contratos k ON k.id = e.contrato_id
              ORDER BY e.id DESC
              LIMIT ' . max(1, min(200, $limite)),
        );
    }

    /**
     * Reclama UNA extracción pendiente para este trabajador.
     *
     * `FOR UPDATE SKIP LOCKED` es la pieza clave: el segundo trabajador no
     * espera a que el primero suelte la fila —se la salta y toma la
     * siguiente—. Sin eso, o los procesos se serializan (y la carga de
     * 1.500 minutas deja de ser paralela) o dos pagan la misma minuta al
     * proveedor de IA.
     *
     * @return ?array<string, mixed> La fila reclamada, o null si no había.
     */
    public function reclamarPendiente(int $maximoIntentos = 2): ?array
    {
        return $this->conexion->transactional(function () use ($maximoIntentos): ?array {
            $id = $this->conexion->fetchOne(
                "SELECT id
                   FROM cc_extracciones
                  WHERE estado = 'PENDIENTE' AND intentos < ?
                  ORDER BY id
                  LIMIT 1
                    FOR UPDATE SKIP LOCKED",
                [$maximoIntentos],
            );

            if ($id === false) {
                return null;
            }

            $this->conexion->executeStatement(
                "UPDATE cc_extracciones
                    SET estado = 'PROCESANDO', reclamado_en = NOW(), intentos = intentos + 1
                  WHERE id = ?",
                [$id],
            );

            $fila = $this->conexion->fetchAssociative('SELECT * FROM cc_extracciones WHERE id = ?', [$id]);

            return $fila === false ? null : $fila;
        });
    }

    /**
     * Devuelve a la cola lo que quedó colgado.
     *
     * Un trabajador que muere a mitad de camino deja su fila en
     * PROCESANDO para siempre. Pasado el margen, vuelve a PENDIENTE (su
     * contador de intentos ya subió, así que no entra en bucle).
     */
    public function liberarColgadas(int $minutos = 15): int
    {
        return (int) $this->conexion->executeStatement(
            "UPDATE cc_extracciones
                SET estado = 'PENDIENTE', reclamado_en = NULL
              WHERE estado = 'PROCESANDO'
                AND reclamado_en IS NOT NULL
                AND reclamado_en < (NOW() - INTERVAL ? MINUTE)",
            [max(1, $minutos)],
        );
    }

    /** Las que agotaron sus intentos y nadie va a volver a procesar. */
    public function marcarAgotadas(int $maximoIntentos = 2): int
    {
        return (int) $this->conexion->executeStatement(
            "UPDATE cc_extracciones
                SET estado = 'ERROR',
                    error = CONCAT('Se agotaron los ', ?, ' intentos de extracción. Revise el PDF o cargue el contrato a mano.')
              WHERE estado = 'PENDIENTE' AND intentos >= ?",
            [$maximoIntentos, $maximoIntentos],
        );
    }

    /**
     * Toma la revisión de una extracción para un usuario.
     *
     * Solo prospera si está libre o ya era suya: cuatro revisores sobre
     * la misma bandeja se pisan sin esto.
     */
    public function asignar(int $id, int $usuarioId): bool
    {
        return $this->conexion->executeStatement(
            'UPDATE cc_extracciones
                SET asignado_a = ?
              WHERE id = ? AND (asignado_a IS NULL OR asignado_a = ?)',
            [$usuarioId, $id, $usuarioId],
        ) === 1;
    }

    public function soltar(int $id, int $usuarioId): void
    {
        $this->conexion->executeStatement(
            'UPDATE cc_extracciones SET asignado_a = NULL WHERE id = ? AND asignado_a = ?',
            [$id, $usuarioId],
        );
    }

    /**
     * Cuántas hay en cada estado, para el encabezado de la bandeja.
     *
     * @return array<string, int>
     */
    public function conteoPorEstado(): array
    {
        /** @var array<string, int> $filas */
        $filas = $this->conexion->fetchAllKeyValue(
            'SELECT estado, COUNT(*) FROM cc_extracciones GROUP BY estado',
        );

        return array_map(intval(...), $filas);
    }

    /**
     * La bandeja de revisión.
     *
     * Ordena por «parecido» antes que por fecha: las minutas de la misma
     * plantilla caen juntas, y revisar cuarenta contratos iguales seguidos
     * es mucho más rápido que cuarenta salteados. El parecido se aproxima
     * con los primeros caracteres del objeto extraído, que es lo que
     * distingue una plantilla de otra.
     *
     * @return list<array<string, mixed>>
     */
    public function bandeja(?string $estado = null, int $limite = 100): array
    {
        $condicion = $estado !== null ? 'WHERE e.estado = ?' : '';
        $parametros = $estado !== null ? [$estado] : [];

        /** @var list<array<string, mixed>> $filas */
        $filas = $this->conexion->fetchAllAssociative(
            "SELECT e.*, u.nombre AS subida_por, a.nombre AS asignada_a_nombre, k.numero AS numero_contrato,
                    LEFT(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(e.resultado_json, '$.objeto')), ''), 60) AS familia
               FROM cc_extracciones e
               LEFT JOIN usuarios u ON u.id = e.creado_por
               LEFT JOIN usuarios a ON a.id = e.asignado_a
               LEFT JOIN cc_contratos k ON k.id = e.contrato_id
              {$condicion}
           ORDER BY familia, e.id DESC
              LIMIT " . max(1, min(500, $limite)),
            $parametros,
        );

        return $filas;
    }

    /** @param array<string, mixed> $campos */
    public function actualizar(int $id, array $campos): void
    {
        if ($campos !== []) {
            $this->conexion->update('cc_extracciones', $campos, ['id' => $id]);
        }
    }

    /**
     * El resultado extraído, ya decodificado (o vacío).
     *
     * @param array<string, mixed> $extraccion
     *
     * @return array<string, mixed>
     */
    public static function resultado(array $extraccion): array
    {
        if (!is_string($extraccion['resultado_json'] ?? null)) {
            return [];
        }

        $datos = json_decode((string) $extraccion['resultado_json'], true);

        return is_array($datos) ? $datos : [];
    }

    /**
     * @param array<string, mixed> $extraccion
     *
     * @return list<string>
     */
    public static function alertas(array $extraccion): array
    {
        if (!is_string($extraccion['alertas_json'] ?? null)) {
            return [];
        }

        $datos = json_decode((string) $extraccion['alertas_json'], true);

        return is_array($datos) ? array_values(array_map(strval(...), $datos)) : [];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Tramites\Repository;

use Doctrine\DBAL\Connection;

/**
 * Acceso a trámites radicados: bandeja del panel y consulta pública.
 * Las filas viajan como arrays asociativos con nombres de columnas; los
 * campos dinámicos y seguimientos se cargan aparte.
 */
final readonly class TramiteRepository
{
    private const string SELECT_BANDEJA = 'SELECT t.*, tt.nombre AS tipo_nombre, e.codigo AS estado_codigo,
            e.nombre AS estado_nombre, e.color AS estado_color, e.es_final AS estado_es_final,
            u.nombre AS asignado_nombre, dep.nombre AS dependencia_nombre
       FROM tramites t
       JOIN tipos_tramite tt ON tt.id = t.tipo_tramite_id
       JOIN tramite_estados e ON e.id = t.estado_id
       LEFT JOIN usuarios u ON u.id = t.asignado_a
       LEFT JOIN dependencias dep ON dep.id = t.dependencia_id';

    public function __construct(private Connection $conexion)
    {
    }

    /**
     * @param array<string, mixed> $fila
     */
    public function crear(array $fila): int
    {
        $this->conexion->insert('tramites', $fila);

        return (int) $this->conexion->lastInsertId();
    }

    public function guardarValor(int $tramiteId, int $campoId, string $valor): void
    {
        $this->conexion->insert('tramite_valores', [
            'tramite_id' => $tramiteId,
            'campo_id' => $campoId,
            'valor' => $valor,
        ]);
    }

    public function guardarAdjunto(int $tramiteId, int $archivoId, string $origen, ?string $descripcion = null): void
    {
        $this->conexion->insert('tramite_adjuntos', [
            'tramite_id' => $tramiteId,
            'archivo_id' => $archivoId,
            'origen' => $origen,
            'descripcion' => $descripcion,
        ]);
    }

    public function agregarSeguimiento(
        int $tramiteId,
        ?int $estadoAnteriorId,
        ?int $estadoNuevoId,
        ?string $nota,
        bool $visibleCiudadano,
        ?int $usuarioId,
    ): void {
        $this->conexion->insert('tramite_seguimiento', [
            'tramite_id' => $tramiteId,
            'estado_anterior_id' => $estadoAnteriorId,
            'estado_nuevo_id' => $estadoNuevoId,
            'nota' => $nota,
            'visible_ciudadano' => $visibleCiudadano ? 1 : 0,
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Consulta pública: radicado + número de documento. Para trámites
     * anónimos basta el radicado (no hay documento contra el cual validar).
     *
     * @return ?array<string, mixed>
     */
    public function consultaPublica(string $radicado, string $numeroDocumento): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            self::SELECT_BANDEJA . ' WHERE t.radicado = ? AND t.eliminado_en IS NULL',
            [trim($radicado)],
        );

        if ($fila === false) {
            return null;
        }

        $esAnonimo = ((int) $fila['anonimo']) === 1;

        if (!$esAnonimo && !hash_equals((string) $fila['numero_documento'], trim($numeroDocumento))) {
            return null;
        }

        return $fila;
    }

    /**
     * @return ?array<string, mixed>
     */
    public function porId(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            self::SELECT_BANDEJA . ' WHERE t.id = ? AND t.eliminado_en IS NULL',
            [$id],
        );

        return $fila === false ? null : $fila;
    }

    /**
     * Bandeja del panel con filtros. Los vencidos y próximos a vencer van
     * primero: es lo que no puede esperar.
     *
     * @param array{tipo?: ?int, estado?: ?string, asignado?: ?int, vencimiento?: ?string, q?: ?string} $filtros
     * @return list<array<string, mixed>>
     */
    public function bandeja(array $filtros = []): array
    {
        $condiciones = ['t.eliminado_en IS NULL'];
        $parametros = [];

        if (!empty($filtros['tipo'])) {
            $condiciones[] = 't.tipo_tramite_id = ?';
            $parametros[] = $filtros['tipo'];
        }

        if (!empty($filtros['estado'])) {
            $condiciones[] = 'e.codigo = ?';
            $parametros[] = $filtros['estado'];
        }

        if (!empty($filtros['asignado'])) {
            $condiciones[] = 't.asignado_a = ?';
            $parametros[] = $filtros['asignado'];
        }

        if (($filtros['vencimiento'] ?? null) === 'vencidos') {
            $condiciones[] = 't.respondido_en IS NULL AND e.es_final = 0 AND t.vence_en < CURDATE()';
        } elseif (($filtros['vencimiento'] ?? null) === 'proximos') {
            $condiciones[] = 't.respondido_en IS NULL AND e.es_final = 0 AND t.vence_en BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)';
        }

        if (!empty($filtros['q'])) {
            $condiciones[] = '(t.radicado LIKE ? OR t.asunto LIKE ? OR t.nombre LIKE ? OR t.numero_documento = ?)';
            $texto = '%' . $filtros['q'] . '%';
            array_push($parametros, $texto, $texto, $texto, $filtros['q']);
        }

        return $this->conexion->fetchAllAssociative(sprintf(
            '%s WHERE %s
             ORDER BY (t.respondido_en IS NULL AND e.es_final = 0 AND t.vence_en < CURDATE()) DESC,
                      (t.respondido_en IS NULL AND e.es_final = 0) DESC,
                      t.vence_en ASC, t.id DESC
             LIMIT 200',
            self::SELECT_BANDEJA,
            implode(' AND ', $condiciones),
        ), $parametros);
    }

    /**
     * Próximos a vencer o vencidos, sin responder, para las alertas.
     *
     * @return list<array<string, mixed>>
     */
    public function porVencer(int $diasAviso = 3): array
    {
        return $this->conexion->fetchAllAssociative(
            self::SELECT_BANDEJA . ' WHERE t.eliminado_en IS NULL AND t.respondido_en IS NULL
                AND e.es_final = 0
                AND t.vence_en <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
              ORDER BY t.vence_en ASC',
            [$diasAviso],
        );
    }

    /**
     * @return list<array<string, mixed>> Seguimientos, opcionalmente solo los públicos
     */
    public function seguimientos(int $tramiteId, bool $soloPublicos): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT s.*, en.nombre AS estado_nuevo_nombre, en.descripcion_publica, u.nombre AS usuario_nombre
               FROM tramite_seguimiento s
               LEFT JOIN tramite_estados en ON en.id = s.estado_nuevo_id
               LEFT JOIN usuarios u ON u.id = s.usuario_id
              WHERE s.tramite_id = ?' . ($soloPublicos ? ' AND s.visible_ciudadano = 1' : '') . '
              ORDER BY s.creado_en, s.id',
            [$tramiteId],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function valores(int $tramiteId): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT v.valor, c.etiqueta, c.codigo
               FROM tramite_valores v
               JOIN tramite_campos c ON c.id = v.campo_id
              WHERE v.tramite_id = ?
              ORDER BY c.orden',
            [$tramiteId],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function adjuntos(int $tramiteId): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT a.*, ar.nombre_original, ar.tamano
               FROM tramite_adjuntos a
               JOIN archivos ar ON ar.id = a.archivo_id
              WHERE a.tramite_id = ?
              ORDER BY a.id',
            [$tramiteId],
        );
    }

    /**
     * @param array<string, mixed> $campos
     */
    public function actualizar(int $id, array $campos): void
    {
        $this->conexion->update('tramites', $campos, ['id' => $id]);
    }
}

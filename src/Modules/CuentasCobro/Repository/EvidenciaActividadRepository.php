<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Repository;

use Doctrine\DBAL\Connection;

/**
 * El catálogo de productos y las evidencias que respaldan cada actividad
 * del informe (§fase 6, bloque B).
 */
final readonly class EvidenciaActividadRepository
{
    /** Tope por actividad: catorce obligaciones × cinco ya son setenta archivos por cuenta. */
    public const int MAXIMO_POR_ACTIVIDAD = 5;

    /** El producto que no exige evidencia; el único. */
    public const string CLAVE_SIN_ENTREGABLE = 'no-aplica';

    /** El que se respalda con una URL en vez de un archivo. */
    public const string CLAVE_ENLACE = 'link-acceso';

    public function __construct(private Connection $conexion)
    {
    }

    /** @return list<array<string, mixed>> El catálogo activo, en su orden institucional. */
    public function productos(): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT id, clave, nombre FROM cc_productos_actividad WHERE activo = 1 ORDER BY orden, nombre',
        );
    }

    /** @return ?array<string, mixed> */
    public function producto(int $id): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT id, clave, nombre FROM cc_productos_actividad WHERE id = ? AND activo = 1',
            [$id],
        );

        return $fila === false ? null : $fila;
    }

    public function idPorClave(string $clave): ?int
    {
        $id = $this->conexion->fetchOne('SELECT id FROM cc_productos_actividad WHERE clave = ?', [$clave]);

        return $id === false ? null : (int) $id;
    }

    /** @return list<array<string, mixed>> El catálogo completo, para administrarlo. */
    public function catalogo(): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT id, clave, nombre, orden, activo FROM cc_productos_actividad ORDER BY orden, nombre',
        );
    }

    public function crearProducto(string $clave, string $nombre): int
    {
        $ultimo = (int) $this->conexion->fetchOne('SELECT COALESCE(MAX(orden), 0) FROM cc_productos_actividad');

        $this->conexion->insert('cc_productos_actividad', [
            'clave' => $clave,
            'nombre' => $nombre,
            'orden' => $ultimo + 10,
        ]);

        return (int) $this->conexion->lastInsertId();
    }

    /**
     * Activa o retira un producto. No se borra: las cuentas ya radicadas
     * lo citan en su informe y una fila menos dejaría el histórico
     * apuntando al vacío.
     *
     * @return bool El estado en que quedó.
     */
    public function alternarProducto(int $id): bool
    {
        $this->conexion->executeStatement(
            'UPDATE cc_productos_actividad SET activo = 1 - activo WHERE id = ?',
            [$id],
        );

        return (bool) $this->conexion->fetchOne('SELECT activo FROM cc_productos_actividad WHERE id = ?', [$id]);
    }

    /**
     * Las evidencias de todas las actividades de una cuenta, agrupadas por
     * actividad: la pantalla las pinta en un solo recorrido.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    public function porCuenta(int $cuentaId): array
    {
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT e.*
               FROM cc_actividad_evidencias e
               JOIN cc_cuenta_actividades a ON a.id = e.actividad_id
              WHERE a.cuenta_id = ?
              ORDER BY e.actividad_id, e.id',
            [$cuentaId],
        );
        $agrupadas = [];

        foreach ($filas as $fila) {
            $agrupadas[(int) $fila['actividad_id']][] = $fila;
        }

        return $agrupadas;
    }

    /** @return list<array<string, mixed>> */
    public function deActividad(int $actividadId): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT * FROM cc_actividad_evidencias WHERE actividad_id = ? ORDER BY id',
            [$actividadId],
        );
    }

    public function contar(int $actividadId): int
    {
        return (int) $this->conexion->fetchOne(
            'SELECT COUNT(*) FROM cc_actividad_evidencias WHERE actividad_id = ?',
            [$actividadId],
        );
    }

    public function agregarArchivo(int $actividadId, string $ruta, string $nombreOriginal, string $mime, int $tamano): int
    {
        $this->conexion->insert('cc_actividad_evidencias', [
            'actividad_id' => $actividadId,
            'tipo' => 'ARCHIVO',
            'archivo' => $ruta,
            'nombre_original' => mb_substr($nombreOriginal, 0, 255),
            'mime' => $mime,
            'tamano' => $tamano,
        ]);

        return (int) $this->conexion->lastInsertId();
    }

    public function agregarEnlace(int $actividadId, string $enlace): int
    {
        $this->conexion->insert('cc_actividad_evidencias', [
            'actividad_id' => $actividadId,
            'tipo' => 'ENLACE',
            'enlace' => mb_substr($enlace, 0, 500),
        ]);

        return (int) $this->conexion->lastInsertId();
    }

    /**
     * Borra una evidencia SI pertenece a la cuenta indicada.
     *
     * La comprobación de pertenencia va en el SQL y no en el controlador:
     * es el único sitio por donde no se puede colar un id ajeno.
     *
     * @return ?string La ruta del archivo borrado, para retirarlo del disco.
     */
    public function eliminarDeCuenta(int $evidenciaId, int $cuentaId): ?string
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT e.id, e.archivo
               FROM cc_actividad_evidencias e
               JOIN cc_cuenta_actividades a ON a.id = e.actividad_id
              WHERE e.id = ? AND a.cuenta_id = ?',
            [$evidenciaId, $cuentaId],
        );

        if ($fila === false) {
            return null;
        }

        $this->conexion->delete('cc_actividad_evidencias', ['id' => (int) $fila['id']]);

        return is_string($fila['archivo']) ? $fila['archivo'] : null;
    }

    /** @return ?array<string, mixed> Una evidencia con la cuenta y el contrato a los que pertenece. */
    public function porIdConContexto(int $evidenciaId): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT e.*, a.cuenta_id, c.contrato_id, k.supervisor_id, k.contratista_id
               FROM cc_actividad_evidencias e
               JOIN cc_cuenta_actividades a ON a.id = e.actividad_id
               JOIN cc_cuentas c ON c.id = a.cuenta_id
               JOIN cc_contratos k ON k.id = c.contrato_id
              WHERE e.id = ?',
            [$evidenciaId],
        );

        return $fila === false ? null : $fila;
    }
}

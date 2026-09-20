<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Repository;

use App\Modules\CuentasCobro\Entity\ParametrosAnuales;
use Doctrine\DBAL\Connection;

/**
 * Parámetros de ley por año (SMLV, tarifas), clases ARL y la identidad SGC
 * de las tres plantillas PDF. Lo que cambia cada enero se cambia aquí desde
 * el panel, no en código (regla de oro #2 de la plataforma).
 */
final readonly class ParametrosCuentasRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    /** @return list<array<string, mixed>> */
    public function anuales(): array
    {
        return $this->conexion->fetchAllAssociative(
            'SELECT * FROM cc_parametros_anuales ORDER BY anio DESC',
        );
    }

    public function porAnio(int $anio): ?ParametrosAnuales
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM cc_parametros_anuales WHERE anio = ?',
            [$anio],
        );

        if ($fila === false) {
            return null;
        }

        return new ParametrosAnuales(
            anio: (int) $fila['anio'],
            smlv: ContratoRepository::pesos((string) $fila['smlv']),
            porcentajeIbc: (string) $fila['porcentaje_ibc'],
            tarifaSalud: (string) $fila['tarifa_salud'],
            tarifaPension: (string) $fila['tarifa_pension'],
            redondeoAportes: (int) $fila['redondeo_aportes'],
        );
    }

    /** @param array<string, mixed> $campos */
    public function guardarAnio(int $anio, array $campos): void
    {
        $existe = (int) $this->conexion->fetchOne(
            'SELECT COUNT(*) FROM cc_parametros_anuales WHERE anio = ?',
            [$anio],
        ) > 0;

        if ($existe) {
            $this->conexion->update('cc_parametros_anuales', $campos, ['anio' => $anio]);
        } else {
            $this->conexion->insert('cc_parametros_anuales', ['anio' => $anio] + $campos);
        }
    }

    /** @return list<array<string, mixed>> */
    public function clasesArl(): array
    {
        return $this->conexion->fetchAllAssociative('SELECT * FROM cc_arl_clases ORDER BY clase');
    }

    /** La tarifa de una clase, como decimal exacto («0.02436»), o nula. */
    public function tarifaClase(int $clase): ?string
    {
        $tarifa = $this->conexion->fetchOne('SELECT tarifa FROM cc_arl_clases WHERE clase = ?', [$clase]);

        return $tarifa === false ? null : (string) $tarifa;
    }

    /** @return list<array<string, mixed>> */
    public function plantillas(): array
    {
        return $this->conexion->fetchAllAssociative('SELECT * FROM cc_plantillas ORDER BY clave');
    }

    /** @param array<string, mixed> $campos */
    public function actualizarPlantilla(string $clave, array $campos): void
    {
        if ($campos !== []) {
            $this->conexion->update('cc_plantillas', $campos, ['clave' => $clave]);
        }
    }
}

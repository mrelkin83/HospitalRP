<?php

declare(strict_types=1);

namespace App\Modules\Tramites\Service;

use Doctrine\DBAL\Connection;

/**
 * Informe trimestral sobre acceso a la información, quejas y reclamos
 * (Ley 1712 art. 11 lit. h, ITA 6.10).
 *
 * El criterio pide cuatro cifras por periodo: solicitudes recibidas (a),
 * trasladadas a otra entidad (b), tiempo de respuesta (c) y solicitudes en las
 * que se negó el acceso (d).
 *
 * Las cuatro ya estaban en la tabla `tramites`; lo que no había era forma de
 * publicarlas. Antes el informe se escribía a mano cada trimestre, que es
 * exactamente el tipo de tarea que se deja de hacer cuando cambia el personal
 * —y así se pierde el punto en el índice—. Aquí se calcula solo.
 *
 * Devuelve **únicamente cifras agregadas**. Las solicitudes llevan nombre,
 * documento y correo de quien las radica: ninguno de esos datos sale de este
 * servicio.
 */
final readonly class InformeAccesoInformacion
{
    public function __construct(private Connection $conexion)
    {
    }

    /**
     * Una fila por trimestre, de la más reciente a la más antigua.
     *
     * @return list<array<string, mixed>>
     */
    public function porTrimestre(): array
    {
        $filas = $this->conexion->fetchAllAssociative(
            "SELECT YEAR(t.creado_en)    AS anio,
                    QUARTER(t.creado_en) AS trimestre,
                    COUNT(*)                                   AS recibidas,
                    SUM(t.respondido_en IS NOT NULL)           AS respondidas,
                    SUM(t.trasladado_a IS NOT NULL)            AS trasladadas,
                    SUM(t.acceso_negado)                       AS negadas,
                    AVG(CASE
                          WHEN t.respondido_en IS NOT NULL
                          THEN TIMESTAMPDIFF(DAY, t.creado_en, t.respondido_en)
                        END)                                   AS dias_promedio
               FROM tramites t
              WHERE t.eliminado_en IS NULL
              GROUP BY anio, trimestre
              ORDER BY anio DESC, trimestre DESC",
        );

        return array_map(static function (array $f): array {
            $f['anio'] = (int) $f['anio'];
            $f['trimestre'] = (int) $f['trimestre'];
            $f['recibidas'] = (int) $f['recibidas'];
            $f['respondidas'] = (int) $f['respondidas'];
            $f['trasladadas'] = (int) $f['trasladadas'];
            $f['negadas'] = (int) $f['negadas'];

            // Sin ninguna respuesta todavía, el promedio es NULL. Se reporta
            // así y no como «0 días», que diría lo contrario de la verdad.
            $f['dias_promedio'] = $f['dias_promedio'] !== null
                ? round((float) $f['dias_promedio'], 1)
                : null;

            $f['periodo'] = sprintf('%d — Trimestre %d', $f['anio'], $f['trimestre']);

            return $f;
        }, $filas);
    }

    /**
     * Causales por las que se negó el acceso, agregadas.
     *
     * El criterio 6.10.d pide el número de solicitudes negadas; publicar
     * además el motivo es lo que le sirve a quien lee el informe.
     *
     * @return list<array{causal: string, total: int}>
     */
    public function causalesDeNegacion(): array
    {
        $filas = $this->conexion->fetchAllAssociative(
            "SELECT COALESCE(NULLIF(TRIM(causal_negacion), ''), 'Sin causal registrada') AS causal,
                    COUNT(*) AS total
               FROM tramites
              WHERE acceso_negado = 1 AND eliminado_en IS NULL
              GROUP BY causal
              ORDER BY total DESC",
        );

        return array_map(
            static fn (array $f): array => ['causal' => (string) $f['causal'], 'total' => (int) $f['total']],
            $filas,
        );
    }
}

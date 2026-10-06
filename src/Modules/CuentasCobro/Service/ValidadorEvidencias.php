<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Service;

use App\Modules\CuentasCobro\Repository\CuentaRepository;
use App\Modules\CuentasCobro\Repository\EvidenciaActividadRepository;

/**
 * La regla del bloque B: ninguna actividad se certifica sin producto, y
 * ningún producto —salvo NO APLICA— sin evidencia.
 *
 * Vive en un servicio y no en el controlador del portal porque la
 * radicación tiene una sola puerta (`EnvioCuentaService`) y ahí es donde
 * la regla tiene que morder: una validación que solo esté en la pantalla
 * se esquiva con un formulario a mano.
 */
final readonly class ValidadorEvidencias
{
    public function __construct(
        private CuentaRepository $cuentas,
        private EvidenciaActividadRepository $evidencias,
    ) {
    }

    /**
     * Qué le falta a la cuenta para poder radicarse, en el lenguaje del
     * contratista (nunca «actividad id 42»: «la obligación 3»).
     *
     * @return list<string>
     */
    public function problemas(int $cuentaId): array
    {
        $actividades = $this->cuentas->actividades($cuentaId);
        $porActividad = $this->evidencias->porCuenta($cuentaId);
        $sinEntregable = $this->evidencias->idPorClave(EvidenciaActividadRepository::CLAVE_SIN_ENTREGABLE);
        $problemas = [];

        foreach ($actividades as $indice => $actividad) {
            $rotulo = $this->rotulo($actividad, $indice);
            $productoId = $actividad['producto_id'] === null ? null : (int) $actividad['producto_id'];

            if ($productoId === null) {
                $problemas[] = sprintf('%s: falta decir con qué producto la respalda.', $rotulo);

                continue;
            }

            if ($productoId === $sinEntregable) {
                continue;
            }

            if (($porActividad[(int) $actividad['id']] ?? []) === []) {
                $problemas[] = sprintf('%s: falta adjuntar la evidencia del producto.', $rotulo);
            }
        }

        return $problemas;
    }

    /**
     * Cómo se nombra una actividad ante el contratista: por el número de
     * su obligación cuando viene de la minuta, y por su título cuando la
     * escribió a mano.
     *
     * @param array<string, mixed> $actividad
     */
    private function rotulo(array $actividad, int $indice): string
    {
        if ($actividad['obligacion_id'] !== null) {
            return 'La obligación ' . (int) ($actividad['orden'] ?? $indice + 1);
        }

        $titulo = trim((string) ($actividad['titulo_manual'] ?? ''));

        return $titulo === ''
            ? 'La actividad ' . ($indice + 1)
            : 'La actividad «' . mb_substr($titulo, 0, 60) . '»';
    }
}

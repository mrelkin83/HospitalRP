<?php

declare(strict_types=1);

namespace App\Modules\CuentasCobro\Service;

/**
 * El motor de marcadores de las plantillas PDF (§11 del documento):
 * `{{campo}}` se reemplaza con escapado HTML y `{{#lista}}…{{/lista}}`
 * repite su bloque una vez por elemento.
 *
 * Es deliberadamente pequeño: tres documentos institucionales de formato
 * fijo no justifican traer un motor de plantillas completo, y cada cosa
 * que este motor NO hace (condicionales anidados, expresiones) es una
 * forma menos de que un formato oficial salga distinto de lo esperado.
 *
 * Convención: `{{{campo}}}` (tres llaves) inserta sin escapar; solo lo usa
 * el sistema para fragmentos que él mismo genera (la imagen de la firma).
 */
final readonly class PlantillaPdf
{
    /**
     * @param array<string, mixed> $datos Escalares para {{campo}} y listas
     *                                    de arreglos para {{#lista}}.
     */
    public static function rellenar(string $plantilla, array $datos): string
    {
        // Primero los bucles, de adentro hacia afuera no hace falta: los
        // formatos reales no anidan listas.
        $resultado = preg_replace_callback(
            '/\{\{#([a-z_]+)\}\}(.*?)\{\{\/\1\}\}/s',
            static function (array $m) use ($datos): string {
                $lista = $datos[$m[1]] ?? [];

                if (!is_array($lista)) {
                    return '';
                }

                $piezas = [];

                foreach ($lista as $elemento) {
                    $piezas[] = self::rellenar($m[2], is_array($elemento) ? $elemento : []);
                }

                return implode('', $piezas);
            },
            $plantilla,
        ) ?? $plantilla;

        // Tres llaves: crudo (solo para HTML generado por el sistema).
        $resultado = preg_replace_callback(
            '/\{\{\{([a-z_]+)\}\}\}/',
            static fn (array $m): string => self::escalar($datos[$m[1]] ?? ''),
            $resultado,
        ) ?? $resultado;

        // Dos llaves: escapado siempre.
        return preg_replace_callback(
            '/\{\{([a-z_]+)\}\}/',
            static fn (array $m): string => htmlspecialchars(self::escalar($datos[$m[1]] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $resultado,
        ) ?? $resultado;
    }

    private static function escalar(mixed $valor): string
    {
        if (is_string($valor)) {
            return $valor;
        }

        if (is_int($valor) || is_float($valor)) {
            return (string) $valor;
        }

        if ($valor instanceof \Stringable) {
            return (string) $valor;
        }

        return '';
    }
}

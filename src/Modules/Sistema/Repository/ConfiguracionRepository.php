<?php

declare(strict_types=1);

namespace App\Modules\Sistema\Repository;

use Doctrine\DBAL\Connection;

/**
 * La tabla `configuracion` vista desde el panel.
 *
 * ConfigDinamica lee valores sueltos para el sitio público; esto administra
 * las filas completas —tipo, grupo, etiqueta, ayuda— que la pantalla de
 * ajustes necesita para dibujar el campo correcto en cada caso.
 */
final readonly class ConfiguracionRepository
{
    /** Orden de presentación. Lo no listado va al final, alfabético. */
    private const array ORDEN_GRUPOS = [
        'entidad' => 'Identidad de la entidad',
        'contacto' => 'Contacto y atención',
        'redes' => 'Redes sociales',
        'sitio' => 'Sitio web',
        'hero' => 'Portada: héroe principal',
        'portada' => 'Portada: campañas y comunicados',
        'pie' => 'Pie de página y barra GOV.CO',
        'tramites' => 'Trámites y PQRSDF',
        'ia' => 'Asistente de IA',
    ];

    public function __construct(private Connection $conexion)
    {
    }

    /**
     * @return array<string, array{titulo: string, ajustes: list<array<string, mixed>>}>
     */
    public function porGrupos(): array
    {
        /** @var list<array<string, mixed>> $filas */
        $filas = $this->conexion->fetchAllAssociative(
            'SELECT * FROM configuracion WHERE editable = 1 ORDER BY grupo, orden, etiqueta',
        );

        $grupos = [];

        foreach ($filas as $fila) {
            $grupo = (string) $fila['grupo'];
            $grupos[$grupo] ??= [
                'titulo' => self::ORDEN_GRUPOS[$grupo] ?? ucfirst($grupo),
                'ajustes' => [],
            ];
            $grupos[$grupo]['ajustes'][] = $fila;
        }

        // Los grupos conocidos primero y en el orden pensado para el
        // funcionario: la identidad de la entidad antes que los ajustes de IA.
        uksort($grupos, static function (string $a, string $b): int {
            $conocidos = array_keys(self::ORDEN_GRUPOS);
            $posA = array_search($a, $conocidos, true);
            $posB = array_search($b, $conocidos, true);

            return match (true) {
                $posA !== false && $posB !== false => $posA <=> $posB,
                $posA !== false => -1,
                $posB !== false => 1,
                default => strcmp($a, $b),
            };
        });

        return $grupos;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function porClave(string $clave): ?array
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM configuracion WHERE clave = ?',
            [$clave],
        );

        return $fila === false ? null : $fila;
    }

    /**
     * Guarda lo que de verdad cambió y devuelve el detalle, para que el
     * controlador lo audite clave por clave.
     *
     * @param array<string, string> $valores clave => valor nuevo
     * @return array<string, array{antes: ?string, despues: string, etiqueta: string}>
     */
    public function guardar(array $valores, ?int $usuarioId): array
    {
        /** @var array<string, array{valor: ?string, etiqueta: string}> $actuales */
        $actuales = [];

        foreach ($this->conexion->fetchAllAssociative('SELECT clave, valor, etiqueta FROM configuracion WHERE editable = 1') as $fila) {
            $actuales[(string) $fila['clave']] = [
                'valor' => $fila['valor'] === null ? null : (string) $fila['valor'],
                'etiqueta' => (string) $fila['etiqueta'],
            ];
        }

        $cambios = [];

        foreach ($valores as $clave => $valor) {
            // Una clave que no existe en la tabla no se crea desde el
            // formulario: eso convertiría un campo manipulado en el navegador
            // en una fila nueva de configuración.
            if (!isset($actuales[$clave]) || $actuales[$clave]['valor'] === $valor) {
                continue;
            }

            $this->conexion->update(
                'configuracion',
                ['valor' => $valor, 'actualizado_por' => $usuarioId],
                ['clave' => $clave],
            );

            $cambios[$clave] = [
                'antes' => $actuales[$clave]['valor'],
                'despues' => $valor,
                'etiqueta' => $actuales[$clave]['etiqueta'],
            ];
        }

        return $cambios;
    }
}

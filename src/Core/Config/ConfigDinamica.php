<?php

declare(strict_types=1);

namespace App\Core\Config;

use Doctrine\DBAL\Connection;

/**
 * Configuración administrable desde el panel (regla de oro #2 de CLAUDE.md):
 * teléfonos, dirección, redes, textos del pie. Lo que cambia entre servidores
 * va en ConfigAplicacion; lo que cambia un funcionario, aquí.
 *
 * Toda la tabla se carga en la primera lectura de la petición (decenas de
 * filas): una consulta, no una por clave.
 */
final class ConfigDinamica
{
    /** @var ?array<string, ?string> */
    private ?array $valores = null;

    public function __construct(private readonly Connection $conexion)
    {
    }

    public function texto(string $clave, string $porDefecto = ''): string
    {
        $valor = $this->cargar()[$clave] ?? null;

        return ($valor === null || $valor === '') ? $porDefecto : $valor;
    }

    /**
     * Entero acotado. Los límites no son paranoia: estas claves las edita un
     * funcionario desde el panel, y un «0» o un «500» en «campañas por
     * pestaña» rompería la portada sin que nadie relacione una cosa con la
     * otra. Fuera de rango se recorta al extremo más cercano.
     */
    public function entero(string $clave, int $porDefecto, int $minimo, int $maximo): int
    {
        $valor = $this->cargar()[$clave] ?? null;

        if ($valor === null || $valor === '' || !is_numeric($valor)) {
            return $porDefecto;
        }

        return max($minimo, min($maximo, (int) $valor));
    }

    public function booleano(string $clave, bool $porDefecto = false): bool
    {
        $valor = $this->cargar()[$clave] ?? null;

        if ($valor === null || $valor === '') {
            return $porDefecto;
        }

        return in_array(strtolower($valor), ['1', 'true', 'si', 'sí', 'on'], true);
    }

    /**
     * @return array<string, ?string>
     */
    private function cargar(): array
    {
        if ($this->valores !== null) {
            return $this->valores;
        }

        /** @var array<string, ?string> $filas */
        $filas = $this->conexion->fetchAllKeyValue('SELECT clave, valor FROM configuracion');

        return $this->valores = $filas;
    }
}

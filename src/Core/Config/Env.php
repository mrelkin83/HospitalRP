<?php

declare(strict_types=1);

namespace App\Core\Config;

use App\Core\Config\Exception\VariableEntornoFaltanteException;
use App\Core\Config\Exception\VariableEntornoInvalidaException;

/**
 * Lectura tipada de variables de entorno.
 *
 * Existe para que ningún punto del código haga `$_ENV['ALGO']` y se coma un
 * string vacío como si fuera una configuración válida. Todo acceso devuelve
 * el tipo declarado o lanza una excepción al arrancar.
 */
final readonly class Env
{
    /**
     * @param array<string, string> $variables
     */
    public function __construct(private array $variables)
    {
    }

    /**
     * Factoría pura sobre las superglobales ya pobladas por symfony/dotenv.
     */
    public static function desdeSuperglobales(): self
    {
        /** @var array<string, string> $vars */
        $vars = array_map(
            static fn (mixed $valor): string => is_scalar($valor) ? (string) $valor : '',
            $_ENV + $_SERVER,
        );

        return new self($vars);
    }

    public function tiene(string $clave): bool
    {
        return isset($this->variables[$clave]) && $this->variables[$clave] !== '';
    }

    public function texto(string $clave, ?string $porDefecto = null): string
    {
        if (!$this->tiene($clave)) {
            if ($porDefecto === null) {
                throw VariableEntornoFaltanteException::para($clave);
            }

            return $porDefecto;
        }

        return $this->variables[$clave];
    }

    /**
     * Para valores que legítimamente pueden no existir, como el DSN de Redis:
     * si está vacío, el sistema cae al respaldo en tabla MySQL (CLAUDE.md §3).
     */
    public function textoOpcional(string $clave): ?string
    {
        return $this->tiene($clave) ? $this->variables[$clave] : null;
    }

    public function entero(string $clave, ?int $porDefecto = null): int
    {
        if (!$this->tiene($clave)) {
            if ($porDefecto === null) {
                throw VariableEntornoFaltanteException::para($clave);
            }

            return $porDefecto;
        }

        $valor = $this->variables[$clave];

        if (preg_match('/^-?\d+$/', $valor) !== 1) {
            throw VariableEntornoInvalidaException::noEsEntero($clave, $valor);
        }

        return (int) $valor;
    }

    public function booleano(string $clave, ?bool $porDefecto = null): bool
    {
        if (!$this->tiene($clave)) {
            if ($porDefecto === null) {
                throw VariableEntornoFaltanteException::para($clave);
            }

            return $porDefecto;
        }

        $valor = strtolower(trim($this->variables[$clave]));

        return match ($valor) {
            'true', '1', 'on', 'yes', 'si', 'sí' => true,
            'false', '0', 'off', 'no' => false,
            default => throw VariableEntornoInvalidaException::noEsBooleano(
                $clave,
                $this->variables[$clave],
            ),
        };
    }

    /**
     * @param non-empty-list<string> $permitidos
     */
    public function opcion(string $clave, array $permitidos, ?string $porDefecto = null): string
    {
        $valor = $this->texto($clave, $porDefecto);

        if (!in_array($valor, $permitidos, true)) {
            throw VariableEntornoInvalidaException::fueraDeLista($clave, $valor, $permitidos);
        }

        return $valor;
    }
}

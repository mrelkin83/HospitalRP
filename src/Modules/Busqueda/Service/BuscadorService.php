<?php

declare(strict_types=1);

namespace App\Modules\Busqueda\Service;

use App\Modules\Busqueda\Entity\TipoIndice;
use App\Modules\Busqueda\Repository\IndiceBusquedaRepository;

/**
 * Búsqueda pública: normaliza el término, consulta el índice y agrupa por
 * tipo en el orden del peso editorial. Toda búsqueda real queda en
 * `busquedas_log`; las que no devuelven nada alimentan la creación de FAQ.
 */
final readonly class BuscadorService
{
    private const int MINIMO_CARACTERES = 3;

    public function __construct(private IndiceBusquedaRepository $indice)
    {
    }

    /**
     * @return array{termino: string, total: int, grupos: array<string, array{etiqueta: string, resultados: list<array<string, mixed>>}>}
     */
    public function buscar(string $termino): array
    {
        $termino = trim((string) preg_replace('/\s+/u', ' ', $termino));

        if (mb_strlen($termino) < self::MINIMO_CARACTERES) {
            return ['termino' => $termino, 'total' => 0, 'grupos' => []];
        }

        $filas = $this->indice->buscar($termino);
        $this->indice->registrarBusqueda($termino, count($filas));

        $grupos = [];

        foreach ($filas as $fila) {
            $tipo = TipoIndice::from((string) $fila['tipo']);
            $grupos[$tipo->value] ??= ['etiqueta' => $tipo->etiqueta(), 'peso' => $tipo->peso(), 'resultados' => []];
            $grupos[$tipo->value]['resultados'][] = $fila;
        }

        uasort($grupos, static fn (array $a, array $b): int => $b['peso'] <=> $a['peso']);

        foreach ($grupos as &$grupo) {
            unset($grupo['peso']);
        }

        return ['termino' => $termino, 'total' => count($filas), 'grupos' => $grupos];
    }
}

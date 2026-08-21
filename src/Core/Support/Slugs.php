<?php

declare(strict_types=1);

namespace App\Core\Support;

use Transliterator;

/**
 * Slugs de URL desde títulos en español. Factoría pura (CLAUDE.md §5).
 */
final class Slugs
{
    private function __construct()
    {
    }

    public static function desde(string $texto, int $maximo = 190): string
    {
        $transliterador = Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
        $plano = $transliterador?->transliterate($texto) ?? mb_strtolower($texto);

        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $plano);
        $slug = trim($slug, '-');

        if ($slug === '') {
            $slug = 'sin-titulo';
        }

        return substr($slug, 0, $maximo);
    }
}

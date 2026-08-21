<?php

declare(strict_types=1);

namespace App\Modules\Sistema\Entity;

/**
 * Las cinco funciones de IA permitidas en el panel (CLAUDE.md §6, «IA»).
 * Cualquier otra cosa —chat, respuestas al ciudadano— está fuera de v1.
 */
enum FuncionIa: string
{
    case ResumenDocumento = 'resumen_documento';
    case SugerenciaEtiquetas = 'sugerencia_etiquetas';
    case TextoAlternativo = 'texto_alternativo';
    case MetadatosSeo = 'metadatos_seo';
    case BorradorNoticia = 'borrador_noticia';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ResumenDocumento => 'Resumen de documento',
            self::SugerenciaEtiquetas => 'Sugerencia de etiquetas',
            self::TextoAlternativo => 'Texto alternativo de imagen',
            self::MetadatosSeo => 'Metadatos SEO',
            self::BorradorNoticia => 'Borrador de noticia',
        };
    }
}

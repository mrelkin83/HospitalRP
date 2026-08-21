<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Service;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Lista blanca del HTML que produce CKEditor (CLAUDE.md §6, Contenido):
 * se sanea ANTES de guardar, nunca al mostrar. Todo lo que no esté aquí
 * —scripts, estilos, iframes, atributos on*— desaparece.
 *
 * La lista crece solo cuando el editor gane una capacidad (tablas, video),
 * con su elemento exacto y sus atributos exactos.
 */
final readonly class SanitizadorHtml
{
    private HtmlSanitizer $sanitizador;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig())
            // Bloques de texto
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('h4')
            ->allowElement('blockquote')
            ->allowElement('ul')
            ->allowElement('ol', ['start'])
            ->allowElement('li')
            // Énfasis
            ->allowElement('strong')
            ->allowElement('em')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('sub')
            ->allowElement('sup')
            // Enlaces: rel de seguridad forzado en todos
            ->allowElement('a', ['href', 'title'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            // Imágenes de la biblioteca de medios (rutas relativas /archivos/N)
            ->allowElement('img', ['src', 'alt', 'width', 'height'])
            ->allowElement('figure')
            ->allowElement('figcaption')
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
            ->allowMediaSchemes(['https', 'http'])
            // El límite por defecto del componente corta contenido largo en
            // silencio; MEDIUMTEXT aguanta y el tope real lo pone la validación.
            ->withMaxInputLength(4_000_000);

        $this->sanitizador = new HtmlSanitizer($config);
    }

    public function sanear(string $html): string
    {
        return $this->sanitizador->sanitize($html);
    }
}

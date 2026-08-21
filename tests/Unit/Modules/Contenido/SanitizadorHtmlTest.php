<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Contenido;

use App\Modules\Contenido\Service\SanitizadorHtml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * La lista blanca se aplica antes de guardar: nada de lo que aparezca aquí
 * como "eliminado" puede llegar jamás a la base de datos.
 */
#[CoversClass(SanitizadorHtml::class)]
final class SanitizadorHtmlTest extends TestCase
{
    private SanitizadorHtml $sanitizador;

    protected function setUp(): void
    {
        $this->sanitizador = new SanitizadorHtml();
    }

    public function testEliminaScriptsYManejadoresDeEventos(): void
    {
        $sucio = '<p onclick="alert(1)">Hola</p><script>alert("xss")</script><img src="x" onerror="alert(2)" alt="x">';

        $limpio = $this->sanitizador->sanear($sucio);

        self::assertStringNotContainsString('<script', $limpio);
        self::assertStringNotContainsString('onclick', $limpio);
        self::assertStringNotContainsString('onerror', $limpio);
        self::assertStringNotContainsString('alert', $limpio);
        self::assertStringContainsString('<p>Hola</p>', $limpio);
    }

    public function testEliminaEnlacesJavascript(): void
    {
        $limpio = $this->sanitizador->sanear('<a href="javascript:alert(1)">clic</a>');

        self::assertStringNotContainsString('javascript:', $limpio);
    }

    public function testEliminaIframesEstilosYFormularios(): void
    {
        $sucio = '<iframe src="https://x.com"></iframe><style>p{display:none}</style>'
            . '<form action="/x"><input name="a"></form><div style="color:red">texto</div>';

        $limpio = $this->sanitizador->sanear($sucio);

        self::assertStringNotContainsString('<iframe', $limpio);
        self::assertStringNotContainsString('<style', $limpio);
        self::assertStringNotContainsString('<form', $limpio);
        self::assertStringNotContainsString('style=', $limpio);
    }

    public function testConservaElHtmlLegitimoDelEditor(): void
    {
        $legitimo = '<h2>Título</h2><p><strong>Negrita</strong> y <em>énfasis</em>.</p>'
            . '<ul><li>Uno</li><li>Dos</li></ul>'
            . '<figure><img src="/archivos/12" alt="Fachada de la sede" width="800" height="600">'
            . '<figcaption>La sede principal</figcaption></figure>';

        $limpio = $this->sanitizador->sanear($legitimo);

        self::assertStringContainsString('<h2>Título</h2>', $limpio);
        self::assertStringContainsString('<strong>Negrita</strong>', $limpio);
        self::assertStringContainsString('<li>Uno</li>', $limpio);
        self::assertStringContainsString('src="/archivos/12"', $limpio);
        self::assertStringContainsString('alt="Fachada de la sede"', $limpio);
        self::assertStringContainsString('<figcaption>', $limpio);
    }

    public function testFuerzaRelDeSeguridadEnTodoEnlace(): void
    {
        $limpio = $this->sanitizador->sanear('<a href="https://ejemplo.com" title="Ir">enlace</a>');

        self::assertStringContainsString('rel="noopener noreferrer"', $limpio);
        self::assertStringContainsString('href="https://ejemplo.com"', $limpio);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Config\ConfigAplicacion;
use App\Modules\Busqueda\Repository\IndiceBusquedaRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SEO técnico (Fase 7): robots.txt y sitemap.xml.
 *
 * El sitemap sale del índice de búsqueda: ya contiene toda URL pública
 * visible con su fecha de actualización, y se mantiene solo por los mismos
 * eventos. Las rutas fijas del sitio se añaden aparte.
 */
final readonly class SeoController
{
    private const array RUTAS_FIJAS = [
        '/',
        '/noticias',
        '/sedes',
        '/especialidades',
        '/servicios',
        '/directorio',
        '/documentos',
        // Listados de campañas por audiencia: no son filas de `paginas`, los
        // arma el controlador desde `campanas`, así que no entran solos.
        '/campanas',
        '/trabajadores-y-contratistas',
        '/urgencias',
        '/contacto',
        '/transparencia',
        '/transparencia/datos-abiertos',
        // No es una fila de `paginas`, así que no entra sola al sitemap: la
        // arma el controlador con las cifras de `indicadores`.
        '/estadisticas',
        '/atencion-ciudadania',
        '/atencion-ciudadania/tramites',
        '/atencion-ciudadania/preguntas-frecuentes',
        '/politica-tratamiento-datos',
        '/politica-privacidad-web',
        '/politica-de-cookies',
        '/accesibilidad',
    ];

    public function __construct(
        private ConfigAplicacion $aplicacion,
        private IndiceBusquedaRepository $indice,
    ) {
    }

    public function robots(Request $peticion): Response
    {
        $lineas = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /buscar',
            'Allow: /',
            '',
            'Sitemap: ' . $this->aplicacion->url . '/sitemap.xml',
        ];

        return $this->textoCacheable(implode("\n", $lineas) . "\n", 'text/plain; charset=utf-8');
    }

    public function sitemap(Request $peticion): Response
    {
        $urls = [];

        foreach (self::RUTAS_FIJAS as $ruta) {
            $urls[$ruta] = null;
        }

        foreach ($this->indice->urlsParaSitemap() as $fila) {
            $urls[(string) $fila['url']] ??= substr((string) $fila['actualizado_en'], 0, 10);
        }

        $piezas = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $piezas[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($urls as $ruta => $fecha) {
            $piezas[] = '  <url>';
            $piezas[] = '    <loc>' . htmlspecialchars($this->aplicacion->url . $ruta, ENT_XML1) . '</loc>';

            if ($fecha !== null) {
                $piezas[] = '    <lastmod>' . $fecha . '</lastmod>';
            }

            $piezas[] = '  </url>';
        }

        $piezas[] = '</urlset>';

        return $this->textoCacheable(implode("\n", $piezas) . "\n", 'application/xml; charset=utf-8');
    }

    private function textoCacheable(string $cuerpo, string $tipo): Response
    {
        $respuesta = new Response($cuerpo, Response::HTTP_OK, ['Content-Type' => $tipo]);
        $respuesta->setPublic();
        $respuesta->setMaxAge(3600);

        return $respuesta;
    }
}

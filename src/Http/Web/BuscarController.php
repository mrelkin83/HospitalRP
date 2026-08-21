<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Support\Vistas;
use App\Modules\Busqueda\Service\BuscadorService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Buscador global del sitio (Fase 7): una sola caja que responde por todo
 * lo publicado, con resultados agrupados por tipo y lo accionable primero.
 */
final readonly class BuscarController
{
    public function __construct(
        private LayoutPublico $layout,
        private Vistas $vistas,
        private BuscadorService $buscador,
    ) {
    }

    public function buscar(Request $peticion): Response
    {
        $termino = $peticion->query->getString('q');
        $resultado = $termino !== '' ? $this->buscador->buscar($termino) : null;

        return $this->layout->responder(
            titulo: $resultado !== null && $resultado['termino'] !== ''
                ? sprintf('Resultados para «%s»', $resultado['termino'])
                : 'Buscar en el sitio',
            descripcion: null,
            contenido: $this->vistas->render('publico/buscar', [
                'termino' => $termino,
                'resultado' => $resultado,
            ]),
            noindex: true,
        );
    }
}

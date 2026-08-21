<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Support\Vistas;
use App\Modules\Cumplimiento\Repository\PoliticaRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Páginas públicas de políticas (Res. 1519): siempre la versión activa,
 * con su número de versión y vigencia visibles.
 */
final readonly class PoliticasPublicoController
{
    public function __construct(
        private LayoutPublico $layout,
        private Vistas $vistas,
        private PoliticaRepository $politicas,
    ) {
    }

    public function mostrar(Request $peticion): Response
    {
        $politica = $this->politicas->activaPorTipo((string) $peticion->attributes->get('_tipo_politica'));

        if ($politica === null) {
            return $this->layout->responder(
                titulo: 'Política no disponible',
                descripcion: null,
                contenido: $this->vistas->render('publico/mensaje', [
                    'encabezado' => 'Política no disponible',
                    'mensaje' => 'Este documento aún no está publicado. Comunícate con nuestras líneas de atención.',
                ]),
                noindex: true,
                estado: Response::HTTP_NOT_FOUND,
            );
        }

        return $this->layout->responder(
            titulo: (string) $politica['titulo'],
            descripcion: sprintf('%s de la E.S.E. Hospital Rosario Pumarejo de López.', (string) $politica['titulo']),
            contenido: $this->vistas->render('publico/tramites/politica', ['politica' => $politica]),
        );
    }
}

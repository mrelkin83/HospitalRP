<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Support\Vistas;
use App\Modules\Transparencia\Repository\DatasetRepository;
use App\Modules\Transparencia\Repository\TransparenciaRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menú público de transparencia (Ley 1712, Res. 1519) y datos abiertos.
 * Las secciones vacías también se muestran, con la leyenda «En
 * actualización»: ocultarlas sería fingir cumplimiento.
 */
final readonly class TransparenciaPublicoController
{
    public function __construct(
        private LayoutPublico $layout,
        private Vistas $vistas,
        private TransparenciaRepository $transparencia,
        private DatasetRepository $datasets,
    ) {
    }

    public function index(Request $peticion): Response
    {
        return $this->layout->responder(
            titulo: 'Transparencia y acceso a la información pública',
            descripcion: 'Información pública de la E.S.E. Hospital Rosario Pumarejo de López según la Ley 1712 de 2014 y la Resolución 1519 de 2020.',
            contenido: $this->vistas->render('publico/transparencia/index', [
                'arbol' => $this->transparencia->arbol(soloPublico: true),
            ]),
        );
    }

    public function datosAbiertos(Request $peticion): Response
    {
        return $this->layout->responder(
            titulo: 'Datos abiertos',
            descripcion: 'Conjuntos de datos abiertos de la E.S.E. Hospital Rosario Pumarejo de López, descargables en CSV, JSON y otros formatos abiertos.',
            contenido: $this->vistas->render('publico/transparencia/datos-abiertos', [
                'datasets' => $this->datasets->publicados(),
            ]),
        );
    }

    public function descargarRecurso(Request $peticion): Response
    {
        $recurso = $this->datasets->recursoPublico($peticion->attributes->getInt('id'));

        if ($recurso === null) {
            return new Response('No encontrado', Response::HTTP_NOT_FOUND, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $this->datasets->contarDescarga((int) $recurso['id']);

        if ($recurso['archivo_id'] !== null) {
            return new RedirectResponse('/archivos/' . (int) $recurso['archivo_id'], Response::HTTP_FOUND);
        }

        return new RedirectResponse((string) $recurso['url_externa'], Response::HTTP_FOUND);
    }

    /**
     * Las sub-URL con nombre (/transparencia/normatividad…) llegan en la
     * Fase 7 con las redirecciones; mientras tanto, todas llevan al menú
     * completo en vez de dar 404.
     */
    public function subseccion(Request $peticion): Response
    {
        return new RedirectResponse('/transparencia', Response::HTTP_FOUND);
    }
}

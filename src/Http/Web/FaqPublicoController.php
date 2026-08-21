<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Support\Vistas;
use App\Modules\Contenido\Repository\FaqRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Preguntas frecuentes (Res. 1519, menú de Atención a la Ciudadanía).
 * Se alimentan desde el panel; las búsquedas sin resultado del buscador
 * global le dicen a comunicaciones qué pregunta falta aquí.
 */
final readonly class FaqPublicoController
{
    public function __construct(
        private LayoutPublico $layout,
        private Vistas $vistas,
        private FaqRepository $faq,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        $porCategoria = [];

        foreach ($this->faq->listarPublicadas() as $fila) {
            $porCategoria[(string) ($fila['categoria_nombre'] ?? 'Generales')][] = $fila;
        }

        return $this->layout->responder(
            titulo: 'Preguntas frecuentes',
            descripcion: 'Respuestas a las preguntas más comunes sobre citas, trámites, '
                . 'servicios y atención en el hospital.',
            contenido: $this->vistas->render('publico/faq', [
                'porCategoria' => $porCategoria,
            ]),
        );
    }
}

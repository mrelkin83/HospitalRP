<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Support\Vistas;
use App\Modules\Convocatorias\Repository\ConvocatoriaRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Convocatorias de empleo, contratación y proveedores.
 *
 * En el Joomla esto vivía partido entre «RR HH → Ofertas de empleo» y
 * «Contratación → Invitaciones públicas» (docs/14 §2). Aquí es una sola
 * página filtrable: el ciudadano que busca trabajo no tiene por qué saber en
 * qué dependencia se tramita su vacante.
 */
final readonly class ConvocatoriasPublicoController
{
    private const array TIPOS = [
        'empleo' => 'Empleo',
        'contratacion' => 'Contratación',
        'proveedores' => 'Proveedores',
        'otra' => 'Otras',
    ];

    public function __construct(
        private LayoutPublico $layout,
        private Vistas $vistas,
        private ConvocatoriaRepository $convocatorias,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        $tipo = (string) $peticion->query->get('tipo', '');
        $tipo = isset(self::TIPOS[$tipo]) ? $tipo : null;

        return $this->layout->responder(
            titulo: 'Convocatorias',
            descripcion: 'Convocatorias de empleo, contratación y proveedores de la E.S.E. Hospital Rosario Pumarejo de López.',
            contenido: $this->vistas->render('publico/convocatorias/listado', [
                'convocatorias' => $this->convocatorias->publicadas($tipo),
                'tipos' => self::TIPOS,
                'tipoActivo' => $tipo,
            ]),
        );
    }

    public function detalle(Request $peticion): Response
    {
        $convocatoria = $this->convocatorias->publicaPorSlug((string) $peticion->attributes->get('slug'));

        if ($convocatoria === null) {
            return $this->layout->responder(
                titulo: 'Convocatoria no encontrada',
                descripcion: null,
                contenido: $this->vistas->render('publico/mensaje', [
                    'encabezado' => 'Convocatoria no encontrada',
                    'mensaje' => 'Esta convocatoria no existe o dejó de publicarse. '
                        . 'Revisa el listado completo de convocatorias vigentes.',
                ]),
                noindex: true,
                estado: Response::HTTP_NOT_FOUND,
            );
        }

        return $this->layout->responder(
            titulo: (string) $convocatoria['titulo'],
            descripcion: mb_substr(strip_tags((string) $convocatoria['descripcion']), 0, 300),
            contenido: $this->vistas->render('publico/convocatorias/detalle', [
                'convocatoria' => $convocatoria,
                'documentos' => $this->convocatorias->documentosDe((int) $convocatoria['id']),
                'tipos' => self::TIPOS,
            ]),
        );
    }
}

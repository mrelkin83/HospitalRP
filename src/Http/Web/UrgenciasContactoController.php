<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Config\ConfigDinamica;
use App\Core\Support\Vistas;
use App\Modules\Directorio\Repository\SedeRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Urgencias y contacto: las dos páginas que alguien abre con afán. El
 * teléfono va primero, grande y marcable con un toque.
 */
final readonly class UrgenciasContactoController
{
    public function __construct(
        private LayoutPublico $layout,
        private Vistas $vistas,
        private ConfigDinamica $config,
        private SedeRepository $sedes,
    ) {
    }

    public function urgencias(Request $peticion): Response
    {
        return $this->layout->responder(
            titulo: 'Urgencias',
            descripcion: 'Urgencias 24 horas de la E.S.E. Hospital Rosario Pumarejo de López: teléfonos y sedes.',
            contenido: $this->vistas->render('publico/urgencias', [
                'config' => $this->config,
                'sedes' => $this->sedes->conUrgencias(),
            ]),
        );
    }

    public function contacto(Request $peticion): Response
    {
        return $this->layout->responder(
            titulo: 'Contacto',
            descripcion: 'Canales de atención de la E.S.E. Hospital Rosario Pumarejo de López: teléfonos, correos, sedes y horarios.',
            contenido: $this->vistas->render('publico/contacto', [
                'config' => $this->config,
                'sedes' => $this->sedes->activasPublicas(),
            ]),
        );
    }
}

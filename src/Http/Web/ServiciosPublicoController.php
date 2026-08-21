<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Support\Vistas;
use App\Modules\Directorio\Repository\SedeRepository;
use App\Modules\Directorio\Repository\ServicioRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ServiciosPublicoController
{
    public function __construct(
        private LayoutPublico $layout,
        private Vistas $vistas,
        private ServicioRepository $servicios,
        private SedeRepository $sedes,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        return $this->layout->responder(
            titulo: 'Servicios',
            descripcion: 'Servicios asistenciales de la E.S.E. Hospital Rosario Pumarejo de López: horarios, requisitos y sedes.',
            contenido: $this->vistas->render('publico/servicios/listado', [
                'servicios' => $this->servicios->activosConSedes(),
                'sedes' => $this->sedes->activasPublicas(),
            ]),
        );
    }

    public function detalle(Request $peticion): Response
    {
        $servicio = $this->servicios->publicoPorSlug((string) $peticion->attributes->get('slug'));

        if ($servicio === null) {
            return $this->layout->responder(
                titulo: 'Servicio no encontrado',
                descripcion: null,
                contenido: $this->vistas->render('publico/mensaje', [
                    'encabezado' => 'Servicio no encontrado',
                    'mensaje' => 'El servicio que buscas no existe o dejó de ofrecerse.',
                ]),
                noindex: true,
                estado: Response::HTTP_NOT_FOUND,
            );
        }

        $sedes = array_values(array_filter(
            $this->sedes->activasPublicas(),
            static fn ($s): bool => in_array($s->id, $servicio->sedeIds, true),
        ));

        return $this->layout->responder(
            titulo: $servicio->nombre,
            descripcion: $servicio->descripcion,
            contenido: $this->vistas->render('publico/servicios/detalle', [
                'servicio' => $servicio,
                'sedes' => $sedes,
            ]),
        );
    }
}

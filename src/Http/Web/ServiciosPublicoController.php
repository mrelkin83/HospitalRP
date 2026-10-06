<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Storage\AlmacenArchivos;
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
        private AlmacenArchivos $almacen,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        $servicios = $this->servicios->activosConSedes();

        return $this->layout->responder(
            titulo: 'Servicios',
            descripcion: 'Servicios asistenciales de la E.S.E. Hospital Rosario Pumarejo de López: horarios, requisitos y sedes.',
            contenido: $this->vistas->render('publico/servicios/listado', [
                'servicios' => $servicios,
                'sedes' => $this->sedes->activasPublicas(),
                // Una sola consulta para las fotos de toda la rejilla, no una
                // por tarjeta.
                'imagenes' => $this->almacen->porIds(array_values(array_filter(array_map(
                    static fn ($s): ?int => $s->imagenId,
                    $servicios,
                )))),
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
                'imagen' => $servicio->imagenId !== null ? $this->almacen->porId($servicio->imagenId) : null,
            ]),
        );
    }
}

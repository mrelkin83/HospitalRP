<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Support\Vistas;
use App\Modules\Directorio\Repository\EspecialidadRepository;
use App\Modules\Directorio\Repository\ProfesionalRepository;
use App\Modules\Directorio\Repository\SedeRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EspecialidadesPublicoController
{
    public function __construct(
        private LayoutPublico $layout,
        private Vistas $vistas,
        private EspecialidadRepository $especialidades,
        private SedeRepository $sedes,
        private ProfesionalRepository $profesionales,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        return $this->layout->responder(
            titulo: 'Especialidades',
            descripcion: 'Especialidades médicas de la E.S.E. Hospital Rosario Pumarejo de López y sedes donde se prestan.',
            contenido: $this->vistas->render('publico/especialidades/listado', [
                'especialidades' => $this->especialidades->activasConSedes(),
                'sedes' => $this->sedes->activasPublicas(),
            ]),
        );
    }

    public function detalle(Request $peticion): Response
    {
        $especialidad = $this->especialidades->publicaPorSlug((string) $peticion->attributes->get('slug'));

        if ($especialidad === null) {
            return $this->layout->responder(
                titulo: 'Especialidad no encontrada',
                descripcion: null,
                contenido: $this->vistas->render('publico/mensaje', [
                    'encabezado' => 'Especialidad no encontrada',
                    'mensaje' => 'La especialidad que buscas no existe o dejó de ofrecerse.',
                ]),
                noindex: true,
                estado: Response::HTTP_NOT_FOUND,
            );
        }

        $sedes = array_values(array_filter(
            $this->sedes->activasPublicas(),
            static fn ($s): bool => in_array($s->id, $especialidad->sedeIds, true),
        ));

        return $this->layout->responder(
            titulo: $especialidad->nombre,
            descripcion: $especialidad->descripcion,
            contenido: $this->vistas->render('publico/especialidades/detalle', [
                'especialidad' => $especialidad,
                'sedes' => $sedes,
                'profesionales' => $this->profesionales->publicosDeEspecialidad($especialidad->id),
            ]),
        );
    }
}

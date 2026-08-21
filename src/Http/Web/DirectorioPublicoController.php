<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Storage\AlmacenArchivos;
use App\Core\Support\Vistas;
use App\Modules\Directorio\Repository\EspecialidadRepository;
use App\Modules\Directorio\Repository\ProfesionalRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Directorio médico público: solo profesionales con autorización expresa y
 * activos (el repositorio lo garantiza en la consulta, no la vista).
 */
final readonly class DirectorioPublicoController
{
    public function __construct(
        private LayoutPublico $layout,
        private Vistas $vistas,
        private ProfesionalRepository $profesionales,
        private EspecialidadRepository $especialidades,
        private AlmacenArchivos $almacen,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        $profesionales = $this->profesionales->publicosConEspecialidades();

        return $this->layout->responder(
            titulo: 'Directorio médico',
            descripcion: 'Profesionales de la E.S.E. Hospital Rosario Pumarejo de López, por especialidad.',
            contenido: $this->vistas->render('publico/directorio/listado', [
                'profesionales' => $profesionales,
                'especialidades' => $this->especialidades->activasConSedes(),
                'fotos' => $this->almacen->porIds(array_values(array_filter(array_map(
                    static fn ($p): ?int => $p->fotoId,
                    $profesionales,
                )))),
            ]),
        );
    }

    public function detalle(Request $peticion): Response
    {
        $profesional = $this->profesionales->publicoPorSlug((string) $peticion->attributes->get('slug'));

        if ($profesional === null) {
            return $this->layout->responder(
                titulo: 'Profesional no encontrado',
                descripcion: null,
                contenido: $this->vistas->render('publico/mensaje', [
                    'encabezado' => 'Profesional no encontrado',
                    'mensaje' => 'Este perfil no existe o no está disponible para consulta pública.',
                ]),
                noindex: true,
                estado: Response::HTTP_NOT_FOUND,
            );
        }

        return $this->layout->responder(
            titulo: $profesional->nombre,
            descripcion: sprintf('%s — %s', $profesional->nombre, implode(', ', $profesional->especialidadNombres)),
            contenido: $this->vistas->render('publico/directorio/detalle', [
                'profesional' => $profesional,
                'foto' => $profesional->fotoId !== null ? $this->almacen->porId($profesional->fotoId) : null,
            ]),
        );
    }
}

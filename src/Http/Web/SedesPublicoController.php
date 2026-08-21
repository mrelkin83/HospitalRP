<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Storage\AlmacenArchivos;
use App\Core\Support\Vistas;
use App\Modules\Directorio\Repository\SedeRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class SedesPublicoController
{
    public function __construct(
        private LayoutPublico $layout,
        private Vistas $vistas,
        private SedeRepository $sedes,
        private AlmacenArchivos $almacen,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        $sedes = $this->sedes->activasPublicas();

        return $this->layout->responder(
            titulo: 'Sedes',
            descripcion: 'Sedes, direcciones, teléfonos y horarios de la E.S.E. Hospital Rosario Pumarejo de López.',
            contenido: $this->vistas->render('publico/sedes/listado', [
                'sedes' => $sedes,
                'imagenes' => $this->almacen->porIds(array_values(array_filter(array_map(
                    static fn ($s): ?int => $s->imagenId,
                    $sedes,
                )))),
            ]),
        );
    }

    public function detalle(Request $peticion): Response
    {
        $sede = $this->sedes->publicaPorSlug((string) $peticion->attributes->get('slug'));

        if ($sede === null) {
            return $this->layout->responder(
                titulo: 'Sede no encontrada',
                descripcion: null,
                contenido: $this->vistas->render('publico/mensaje', [
                    'encabezado' => 'Sede no encontrada',
                    'mensaje' => 'La sede que buscas no existe o cambió de dirección.',
                ]),
                noindex: true,
                estado: Response::HTTP_NOT_FOUND,
            );
        }

        return $this->layout->responder(
            titulo: $sede->nombre,
            descripcion: sprintf('%s: %s, %s. Teléfono %s.', $sede->nombre, $sede->direccion, $sede->municipio, $sede->telefono ?? ''),
            contenido: $this->vistas->render('publico/sedes/detalle', [
                'sede' => $sede,
                'imagen' => $sede->imagenId !== null ? $this->almacen->porId($sede->imagenId) : null,
            ]),
        );
    }
}

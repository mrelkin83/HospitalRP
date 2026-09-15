<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Storage\AlmacenArchivos;
use App\Core\Support\Vistas;
use App\Modules\Contenido\Entity\Audiencia;
use App\Modules\Contenido\Entity\Campana;
use App\Modules\Contenido\Repository\CampanaRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Campañas y comunicados de cara al público.
 *
 * Existen estas páginas porque la portada solo enseña tres piezas por pestaña
 * y hasta hoy no había dónde ver el resto: las campañas se administraban en
 * el panel y no tenían URL, así que el botón de una campaña no llevaba a
 * ninguna parte.
 *
 * `/trabajadores-y-contratistas` es la cara pública de la pestaña «Portal de
 * Empleados». No es una intranet ni pide credenciales —la intranet la mandó
 * retirar el hospital el 2026-09-14—: es el tablón de convocatorias,
 * capacitación y bienestar del talento humano, que además es información
 * pública por la Ley 1712.
 */
final readonly class CampanasPublicoController
{
    /** Un listado completo cabe de sobra: son campañas vigentes, no archivo. */
    private const int MAXIMO = 24;

    public function __construct(
        private LayoutPublico $layout,
        private Vistas $vistas,
        private CampanaRepository $campanas,
        private AlmacenArchivos $almacen,
    ) {
    }

    public function listado(Request $peticion): Response
    {
        return $this->paginaDeAudiencia(
            Audiencia::Comunidad,
            'Campañas de salud',
            'Jornadas de vacunación, prevención y promoción de la salud de la '
                . 'E.S.E. Hospital Rosario Pumarejo de López.',
        );
    }

    public function trabajadores(Request $peticion): Response
    {
        return $this->paginaDeAudiencia(
            Audiencia::Personal,
            'Trabajadores y contratistas',
            'Convocatorias, capacitación, bienestar y comunicados para el talento '
                . 'humano de la E.S.E. Hospital Rosario Pumarejo de López.',
        );
    }

    public function detalle(Request $peticion): Response
    {
        $campana = $this->campanas->publicaPorSlug((string) $peticion->attributes->get('slug'));

        if ($campana === null) {
            return $this->layout->responder(
                titulo: 'Campaña no encontrada',
                descripcion: null,
                contenido: $this->vistas->render('publico/mensaje', [
                    'encabezado' => 'Campaña no encontrada',
                    'mensaje' => 'La campaña que buscas no existe, ya terminó su vigencia o fue retirada.',
                ]),
                noindex: true,
                estado: Response::HTTP_NOT_FOUND,
            );
        }

        return $this->layout->responder(
            titulo: $campana->titulo,
            descripcion: $campana->resumen,
            contenido: $this->vistas->render('publico/campanas/detalle', [
                'campana' => $campana,
                'imagen' => $campana->imagenId !== null ? $this->almacen->porId($campana->imagenId) : null,
            ]),
        );
    }

    private function paginaDeAudiencia(Audiencia $audiencia, string $titulo, string $descripcion): Response
    {
        $campanas = $this->campanas->publicasVigentes(self::MAXIMO, $audiencia);

        return $this->layout->responder(
            titulo: $titulo,
            descripcion: $descripcion,
            contenido: $this->vistas->render(
                $audiencia === Audiencia::Personal ? 'publico/campanas/trabajadores' : 'publico/campanas/listado',
                [
                    'campanas' => $campanas,
                    'imagenes' => $this->imagenesDe($campanas),
                    'titulo' => $titulo,
                    'descripcion' => $descripcion,
                ],
            ),
        );
    }

    /**
     * @param list<Campana> $campanas
     *
     * @return array<int, mixed>
     */
    private function imagenesDe(array $campanas): array
    {
        return $this->almacen->porIds(array_values(array_filter(array_map(
            static fn (Campana $campana): ?int => $campana->imagenId,
            $campanas,
        ))));
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Support\Vistas;
use App\Modules\Sistema\Repository\MenuRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mapa del sitio en HTML. El pie lo enlazaba desde la Fase 0 sin que
 * existiera (docs/13 §2.6 y docs/14 §5.1): respondía 404.
 *
 * Se genera desde los menús vigentes más las rutas fijas del sitio, para que
 * añadir un ítem en el panel lo incluya aquí sin tocar código —regla de oro
 * n.º 2 de CLAUDE.md—. Las rutas fijas sí van en código porque son rutas del
 * router, no contenido administrable.
 */
final readonly class MapaSitioController
{
    /** @var array<string, array<string, string>> grupo => [ruta => etiqueta] */
    private const array RUTAS_FIJAS = [
        'Atención a la ciudadanía' => [
            '/atencion-ciudadania' => 'Atención y servicios a la ciudadanía',
            '/atencion-ciudadania/pqrsdf' => 'Radicar PQRSDF',
            '/atencion-ciudadania/pqrsdf/consultar' => 'Consultar el estado de un radicado',
            '/atencion-ciudadania/tramites' => 'Trámites y servicios',
            '/atencion-ciudadania/preguntas-frecuentes' => 'Preguntas frecuentes',
            '/citas' => 'Solicitud de cita',
            '/urgencias' => 'Urgencias',
            '/contacto' => 'Contacto',
        ],
        'Servicios y directorio' => [
            '/servicios' => 'Servicios',
            '/especialidades' => 'Especialidades',
            '/directorio' => 'Directorio médico',
            '/sedes' => 'Sedes y horarios',
        ],
        'Información pública' => [
            '/transparencia' => 'Transparencia y acceso a la información pública',
            '/transparencia/datos-abiertos' => 'Datos abiertos',
            '/servidores-publicos' => 'Directorio de servidores públicos',
            '/documentos' => 'Documentos',
            '/participa' => 'Participa',
            '/convocatorias' => 'Convocatorias',
            '/noticias' => 'Noticias',
            '/eventos' => 'Calendario de eventos',
            '/encuestas' => 'Encuestas de satisfacción',
            '/buscar' => 'Buscador',
        ],
        'Políticas y condiciones' => [
            '/politica-privacidad-web' => 'Política de privacidad web',
            '/politica-tratamiento-datos' => 'Política de tratamiento de datos',
            '/politica-de-cookies' => 'Política de cookies',
            '/accesibilidad' => 'Accesibilidad',
            '/notificaciones-judiciales' => 'Notificaciones judiciales',
        ],
    ];

    public function __construct(
        private LayoutPublico $layout,
        private Vistas $vistas,
        private MenuRepository $menus,
    ) {
    }

    public function mostrar(Request $peticion): Response
    {
        return $this->layout->responder(
            titulo: 'Mapa del sitio',
            descripcion: 'Índice completo de las secciones del sitio web de la E.S.E. Hospital Rosario Pumarejo de López.',
            contenido: $this->vistas->render('publico/mapa-del-sitio', [
                'menus' => $this->menus->arbolPublico(),
                'fijas' => self::RUTAS_FIJAS,
            ]),
        );
    }
}

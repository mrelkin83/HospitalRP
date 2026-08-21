<?php

declare(strict_types=1);

namespace App\Modules\Busqueda\Entity;

/**
 * Tipos de entidad que entran al índice unificado (enum de `indice_busqueda`).
 *
 * El peso inclina el orden a favor de lo que el ciudadano viene a HACER:
 * un trámite pesa más que una noticia aunque la noticia repita más el término.
 */
enum TipoIndice: string
{
    case Pagina = 'pagina';
    case Noticia = 'noticia';
    case Documento = 'documento';
    case Especialidad = 'especialidad';
    case Servicio = 'servicio';
    case Profesional = 'profesional';
    case Sede = 'sede';
    case Faq = 'faq';
    case Convocatoria = 'convocatoria';
    case TipoTramite = 'tramite_tipo';
    case Dataset = 'dataset';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pagina => 'Páginas del sitio',
            self::Noticia => 'Noticias',
            self::Documento => 'Documentos',
            self::Especialidad => 'Especialidades',
            self::Servicio => 'Servicios',
            self::Profesional => 'Profesionales',
            self::Sede => 'Sedes',
            self::Faq => 'Preguntas frecuentes',
            self::Convocatoria => 'Convocatorias',
            self::TipoTramite => 'Trámites y solicitudes',
            self::Dataset => 'Datos abiertos',
        };
    }

    public function peso(): int
    {
        return match ($this) {
            self::TipoTramite => 100,
            self::Servicio => 80,
            self::Especialidad => 80,
            self::Sede => 75,
            self::Faq => 70,
            self::Pagina => 60,
            self::Documento => 50,
            self::Convocatoria => 45,
            self::Noticia => 40,
            self::Profesional => 35,
            self::Dataset => 30,
        };
    }
}

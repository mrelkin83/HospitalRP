<?php

declare(strict_types=1);

namespace App\Modules\Transparencia\Entity;

/**
 * Los tres instrumentos de gestión de la información que exige la Ley 1712
 * art. 6 lit. d y evalúa el ITA en su criterio 9.1.
 *
 * Cada uno es una TABLA con columnas nombradas por la norma, no un documento
 * adjunto. Aquí vive esa lista de columnas: `columnas()` es la fuente de verdad
 * que usan la vista pública, la descarga CSV y el formulario del panel, de modo
 * que los tres no puedan desalinearse entre sí ni respecto del criterio ITA.
 *
 * El orden de las columnas es el del formulario de la Procuraduría a propósito:
 * quien verifica el índice recorre la tabla de izquierda a derecha con el
 * cuestionario al lado.
 */
enum TipoInstrumento: string
{
    case RegistroActivos = 'registro_activos';
    case IndiceClasificada = 'indice_clasificada';
    case EsquemaPublicacion = 'esquema_publicacion';

    public function nombre(): string
    {
        return match ($this) {
            self::RegistroActivos => 'Registro de Activos de Información',
            self::IndiceClasificada => 'Índice de Información Clasificada y Reservada',
            self::EsquemaPublicacion => 'Esquema de Publicación de Información',
        };
    }

    /** Ruta pública del instrumento, también usada para la descarga CSV. */
    public function slug(): string
    {
        return match ($this) {
            self::RegistroActivos => 'registro-de-activos-de-informacion',
            self::IndiceClasificada => 'indice-de-informacion-clasificada-y-reservada',
            self::EsquemaPublicacion => 'esquema-de-publicacion-de-informacion',
        };
    }

    /** Ítem del menú de Transparencia bajo el que cuelga. */
    public function codigoTransparencia(): string
    {
        return match ($this) {
            self::RegistroActivos => '7.3',
            self::IndiceClasificada => '7.4',
            self::EsquemaPublicacion => '7.5',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::RegistroActivos => 'Inventario de las categorías de información que produce y '
                . 'administra la entidad, con su soporte, formato y disponibilidad.',
            self::IndiceClasificada => 'Información que la entidad reserva o clasifica, con el '
                . 'fundamento jurídico de la excepción y el plazo de la reserva.',
            self::EsquemaPublicacion => 'Qué información publica la entidad, con qué frecuencia '
                . 'se actualiza y dónde se consulta.',
        };
    }

    /**
     * Columnas del instrumento: `campo => encabezado`, en el orden del ITA.
     *
     * @return array<string, string>
     */
    public function columnas(): array
    {
        return match ($this) {
            self::RegistroActivos => [
                'categoria' => 'Nombre o título de la categoría de información',
                'descripcion' => 'Descripción del contenido',
                'idioma' => 'Idioma',
                'medio_conservacion' => 'Medio de conservación y/o soporte',
                'formato' => 'Formato',
                'disponibilidad' => 'Información publicada o disponible',
                'url_datos_abiertos' => 'Enlace a datos.gov.co',
            ],
            self::IndiceClasificada => [
                'categoria' => 'Nombre o título de la categoría de información',
                'nombre' => 'Nombre o título de la información',
                'idioma' => 'Idioma',
                'medio_conservacion' => 'Medio de conservación y/o soporte',
                'fecha_generacion' => 'Fecha de generación de la información',
                'responsable_produccion' => 'Responsable de la producción',
                'responsable_informacion' => 'Responsable de la información',
                'objetivo_excepcion' => 'Objetivo legítimo de la excepción',
                'fundamento_legal' => 'Fundamento constitucional o legal',
                'fundamento_juridico' => 'Fundamento jurídico de la excepción',
                'alcance_excepcion' => 'Excepción total o parcial',
                'plazo_reserva' => 'Plazo de la clasificación o reserva',
                'url_datos_abiertos' => 'Enlace a datos.gov.co',
            ],
            self::EsquemaPublicacion => [
                'nombre' => 'Nombre o título de la información',
                'idioma' => 'Idioma',
                'medio_conservacion' => 'Medio de conservación y/o soporte',
                'formato' => 'Formato',
                'fecha_generacion' => 'Fecha de generación de la información',
                'frecuencia_actualizacion' => 'Frecuencia de actualización',
                'lugar_consulta' => 'Lugar de consulta',
                'responsable_produccion' => 'Responsable de la producción',
                'responsable_informacion' => 'Responsable de la información',
            ],
        };
    }

    /** @return list<self> */
    public static function todos(): array
    {
        return [self::RegistroActivos, self::IndiceClasificada, self::EsquemaPublicacion];
    }

    public static function porSlug(string $slug): ?self
    {
        foreach (self::todos() as $tipo) {
            if ($tipo->slug() === $slug) {
                return $tipo;
            }
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Entity;

/**
 * Público al que va dirigida una campaña o comunicado.
 *
 * Las dos son piezas públicas: un comunicado al personal de una E.S.E.
 * —convocatorias, capacitación, bienestar— no es información reservada, y la
 * Ley 1712 empuja justo en sentido contrario. La audiencia decide dónde se
 * muestra (pestaña «Salud y Prevención» o «Portal de Empleados», y su
 * listado), no si se muestra.
 */
enum Audiencia: string
{
    case Comunidad = 'comunidad';
    case Personal = 'personal';

    /** Tolerante a propósito: una fila vieja sin columna vale como comunidad. */
    public static function desde(?string $valor): self
    {
        return self::tryFrom((string) $valor) ?? self::Comunidad;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Comunidad => 'Comunidad y pacientes',
            self::Personal => 'Personal de la entidad',
        };
    }

    /** Nombre de la pestaña en la portada. */
    public function pestana(): string
    {
        return match ($this) {
            self::Comunidad => 'Salud y Prevención',
            self::Personal => 'Portal de Empleados',
        };
    }

    /** Listado público donde vive esta audiencia. */
    public function ruta(): string
    {
        return match ($this) {
            self::Comunidad => '/campanas',
            self::Personal => '/trabajadores-y-contratistas',
        };
    }
}

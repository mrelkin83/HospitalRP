<?php

declare(strict_types=1);

namespace App\Modules\Transparencia\Entity;

/**
 * Tipo de control que ejerce cada ente de vigilancia (ITA 3.13.f).
 *
 * El criterio exige «informar el tipo de control (fiscal, social, político,
 * regulatorio, etc.)». Se modela como enum y no como texto libre porque es
 * justo el literal que el evaluador busca: un campo abierto acabaría con
 * «Supersalud» escrito donde va el tipo de control.
 */
enum TipoControl: string
{
    case Fiscal = 'fiscal';
    case Social = 'social';
    case Politico = 'politico';
    case Regulatorio = 'regulatorio';
    case Disciplinario = 'disciplinario';
    case Administrativo = 'administrativo';
    case Otro = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Fiscal => 'Control fiscal',
            self::Social => 'Control social',
            self::Politico => 'Control político',
            self::Regulatorio => 'Control regulatorio',
            self::Disciplinario => 'Control disciplinario',
            self::Administrativo => 'Inspección, vigilancia y control administrativo',
            self::Otro => 'Otro tipo de control',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Fiscal => 'Vigila el manejo de los recursos públicos.',
            self::Social => 'Ejercido por la ciudadanía y las veedurías.',
            self::Politico => 'Ejercido por corporaciones de elección popular.',
            self::Regulatorio => 'Fija las reglas técnicas del sector.',
            self::Disciplinario => 'Investiga la conducta de los servidores públicos.',
            self::Administrativo => 'Inspecciona y vigila el funcionamiento de la entidad.',
            self::Otro => 'Según la competencia de la autoridad.',
        };
    }

    /** @return list<self> */
    public static function todos(): array
    {
        return self::cases();
    }
}

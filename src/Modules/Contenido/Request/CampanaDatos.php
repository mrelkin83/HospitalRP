<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Request;

use App\Modules\Contenido\Entity\Audiencia;
use Symfony\Component\HttpFoundation\Request;

final readonly class CampanaDatos
{
    public function __construct(
        public string $titulo,
        public ?string $resumen,
        public string $contenido,
        public ?string $poblacionObjetivo,
        public Audiencia $audiencia,
        public ?string $inicio,
        public ?string $fin,
    ) {
    }

    public static function desdePeticion(Request $peticion): self
    {
        $r = $peticion->request;
        $fecha = static fn (string $v): ?string => trim($v) === '' ? null : trim($v);

        return new self(
            titulo: trim($r->getString('titulo')),
            resumen: $r->getString('resumen') !== '' ? trim($r->getString('resumen')) : null,
            contenido: trim($r->getString('contenido')),
            poblacionObjetivo: $r->getString('poblacion_objetivo') !== '' ? trim($r->getString('poblacion_objetivo')) : null,
            // Un valor que no sea de la lista cae en «comunidad»: el formulario
            // solo ofrece dos, y ante un `audiencia=loquesea` inventado a mano
            // el destino menos dañino es la pestaña pública.
            audiencia: Audiencia::desde($r->getString('audiencia')),
            inicio: $fecha($r->getString('inicio')),
            fin: $fecha($r->getString('fin')),
        );
    }

    /**
     * @return array<string, string>
     */
    public function validar(): array
    {
        $errores = [];

        if ($this->titulo === '') {
            $errores['titulo'] = 'Escribe el título de la campaña.';
        } elseif (mb_strlen($this->titulo) > 255) {
            $errores['titulo'] = 'El título no puede pasar de 255 caracteres.';
        }

        if ($this->contenido === '') {
            $errores['contenido'] = 'La campaña necesita contenido.';
        }

        if ($this->inicio !== null && $this->fin !== null && $this->fin < $this->inicio) {
            $errores['fin'] = 'La fecha de fin debe ser posterior a la de inicio.';
        }

        return $errores;
    }
}

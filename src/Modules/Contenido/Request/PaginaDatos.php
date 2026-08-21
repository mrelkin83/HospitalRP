<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Request;

use Symfony\Component\HttpFoundation\Request;

/**
 * Datos del formulario de página institucional.
 */
final readonly class PaginaDatos
{
    public function __construct(
        public string $titulo,
        public string $ruta,
        public ?string $resumen,
        public string $contenido,
        public bool $noindex,
        public ?string $metaTitulo,
        public ?string $metaDescripcion,
        public ?string $publicarEn,
        public ?string $despublicarEn,
    ) {
    }

    public static function desdePeticion(Request $peticion): self
    {
        $r = $peticion->request;
        $ruta = strtolower(trim($r->getString('ruta')));

        if ($ruta !== '' && !str_starts_with($ruta, '/')) {
            $ruta = '/' . $ruta;
        }

        return new self(
            titulo: trim($r->getString('titulo')),
            ruta: rtrim($ruta, '/') ?: $ruta,
            resumen: $r->getString('resumen') !== '' ? trim($r->getString('resumen')) : null,
            contenido: trim($r->getString('contenido')),
            noindex: $r->getBoolean('noindex'),
            metaTitulo: $r->getString('meta_titulo') !== '' ? trim($r->getString('meta_titulo')) : null,
            metaDescripcion: $r->getString('meta_descripcion') !== '' ? trim($r->getString('meta_descripcion')) : null,
            publicarEn: self::fecha($r->getString('publicar_en')),
            despublicarEn: self::fecha($r->getString('despublicar_en')),
        );
    }

    /**
     * @return array<string, string>
     */
    public function validar(): array
    {
        $errores = [];

        if ($this->titulo === '') {
            $errores['titulo'] = 'Escribe el título de la página.';
        } elseif (mb_strlen($this->titulo) > 255) {
            $errores['titulo'] = 'El título no puede pasar de 255 caracteres.';
        }

        if ($this->ruta === '' || $this->ruta === '/') {
            $errores['ruta'] = 'Escribe la dirección de la página, por ejemplo /institucional/quienes-somos.';
        } elseif (preg_match('#^/[a-z0-9\-]+(/[a-z0-9\-]+)*$#', $this->ruta) !== 1) {
            $errores['ruta'] = 'La dirección solo puede tener minúsculas, números y guiones, separados por «/». Ejemplo: /institucional/quienes-somos.';
        } elseif (str_starts_with($this->ruta, '/admin') || str_starts_with($this->ruta, '/archivos')) {
            $errores['ruta'] = 'Esa dirección está reservada por el sistema.';
        }

        if ($this->contenido === '') {
            $errores['contenido'] = 'La página necesita contenido.';
        }

        if ($this->publicarEn !== null && $this->despublicarEn !== null
            && $this->despublicarEn <= $this->publicarEn
        ) {
            $errores['despublicar_en'] = 'La fecha de despublicación debe ser posterior a la de publicación.';
        }

        return $errores;
    }

    private static function fecha(string $valor): ?string
    {
        $valor = trim($valor);

        if ($valor === '') {
            return null;
        }

        return str_replace('T', ' ', $valor) . (strlen($valor) === 16 ? ':00' : '');
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Request;

use Symfony\Component\HttpFoundation\Request;

/**
 * Datos del formulario de noticia, ya recortados y tipados. La validación
 * devuelve mensajes en español listos para mostrarse junto al campo
 * (CLAUDE.md §6, Panel: errores comprensibles para un funcionario).
 */
final readonly class NoticiaDatos
{
    public function __construct(
        public string $titulo,
        public string $resumen,
        public string $contenido,
        public ?int $categoriaId,
        public bool $destacada,
        public ?string $metaTitulo,
        public ?string $metaDescripcion,
        public ?string $publicarEn,
        public ?string $despublicarEn,
    ) {
    }

    public static function desdePeticion(Request $peticion): self
    {
        $r = $peticion->request;

        return new self(
            titulo: trim($r->getString('titulo')),
            resumen: trim($r->getString('resumen')),
            contenido: trim($r->getString('contenido')),
            categoriaId: $r->getString('categoria_id') !== '' ? $r->getInt('categoria_id') : null,
            destacada: $r->getBoolean('destacada'),
            metaTitulo: $r->getString('meta_titulo') !== '' ? trim($r->getString('meta_titulo')) : null,
            metaDescripcion: $r->getString('meta_descripcion') !== '' ? trim($r->getString('meta_descripcion')) : null,
            publicarEn: self::fecha($r->getString('publicar_en')),
            despublicarEn: self::fecha($r->getString('despublicar_en')),
        );
    }

    /**
     * @return array<string, string> campo => mensaje; vacío si todo está bien
     */
    public function validar(): array
    {
        $errores = [];

        if ($this->titulo === '') {
            $errores['titulo'] = 'Escribe el título de la noticia.';
        } elseif (mb_strlen($this->titulo) > 255) {
            $errores['titulo'] = 'El título no puede pasar de 255 caracteres.';
        }

        if ($this->resumen === '') {
            $errores['resumen'] = 'Escribe un resumen corto: es lo que se ve en los listados y en Google.';
        } elseif (mb_strlen($this->resumen) > 500) {
            $errores['resumen'] = 'El resumen no puede pasar de 500 caracteres.';
        }

        if ($this->contenido === '') {
            $errores['contenido'] = 'La noticia necesita contenido.';
        }

        if ($this->publicarEn !== null && $this->despublicarEn !== null
            && $this->despublicarEn <= $this->publicarEn
        ) {
            $errores['despublicar_en'] = 'La fecha de despublicación debe ser posterior a la de publicación.';
        }

        return $errores;
    }

    /**
     * datetime-local llega como «2026-08-21T14:30»; MySQL espera espacio.
     */
    private static function fecha(string $valor): ?string
    {
        $valor = trim($valor);

        if ($valor === '') {
            return null;
        }

        return str_replace('T', ' ', $valor) . (strlen($valor) === 16 ? ':00' : '');
    }
}

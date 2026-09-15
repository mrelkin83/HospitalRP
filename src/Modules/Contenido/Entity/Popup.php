<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Entity;

/**
 * Ventana emergente del sitio público.
 *
 * El mensaje es texto plano, no HTML: la plantilla lo escapa y respeta los
 * saltos de línea. Un popup se escribe desde el panel y va a parar encima de
 * cada visitante; admitir marcado libre ahí convertiría un permiso de edición
 * de contenido en un permiso para inyectar en todas las páginas del sitio.
 */
final readonly class Popup
{
    public function __construct(
        public int $id,
        public string $titulo,
        public string $mensaje,
        public ?int $imagenId,
        public ?string $url,
        public ?string $textoBoton,
        public string $ubicacion,
        public string $frecuencia,
        /**
         * Huella de la última edición. Viaja al navegador junto al id para
         * formar la clave del «ya lo cerré»: si Comunicaciones reescribe el
         * popup, la clave cambia y vuelve a verlo quien ya lo había
         * descartado. Sin esto, corregir una fecha equivocada no llegaría a
         * nadie que hubiera cerrado la versión anterior.
         */
        public string $version,
    ) {
    }

    /** @param array<string, mixed> $fila */
    public static function desdeFila(array $fila): self
    {
        return new self(
            id: (int) $fila['id'],
            titulo: (string) $fila['titulo'],
            mensaje: (string) $fila['mensaje'],
            imagenId: ($fila['imagen_id'] ?? null) === null ? null : (int) $fila['imagen_id'],
            url: ($fila['url'] ?? null) === null ? null : (string) $fila['url'],
            textoBoton: ($fila['texto_boton'] ?? null) === null ? null : (string) $fila['texto_boton'],
            ubicacion: (string) $fila['ubicacion'],
            frecuencia: (string) $fila['frecuencia'],
            version: substr(md5((string) ($fila['actualizado_en'] ?? '')), 0, 8),
        );
    }

    /** La clave con la que el navegador recuerda que este popup ya se cerró. */
    public function clave(): string
    {
        return sprintf('popup-%d-%s', $this->id, $this->version);
    }

    public function tieneBoton(): bool
    {
        return $this->url !== null && $this->url !== '';
    }

    public function etiquetaBoton(): string
    {
        return $this->textoBoton !== null && $this->textoBoton !== '' ? $this->textoBoton : 'Ver más';
    }
}

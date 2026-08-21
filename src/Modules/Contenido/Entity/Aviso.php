<?php

declare(strict_types=1);

namespace App\Modules\Contenido\Entity;

/**
 * Aviso institucional. Uno con nivel crítico activo pone el sitio en modo
 * emergencia (esquema §2): la home se transforma para que el teléfono y el
 * mensaje sean lo primero que se ve.
 */
final readonly class Aviso
{
    public function __construct(
        public int $id,
        public string $tipo,
        public string $nivel,
        public string $titulo,
        public string $mensaje,
        public ?string $url,
        public ?string $textoBoton,
    ) {
    }

    /**
     * @param array<string, mixed> $fila
     */
    public static function desdeFila(array $fila): self
    {
        return new self(
            id: (int) $fila['id'],
            tipo: (string) $fila['tipo'],
            nivel: (string) $fila['nivel'],
            titulo: (string) $fila['titulo'],
            mensaje: (string) $fila['mensaje'],
            url: isset($fila['url']) ? (string) $fila['url'] : null,
            textoBoton: isset($fila['texto_boton']) ? (string) $fila['texto_boton'] : null,
        );
    }

    public function esCritico(): bool
    {
        return $this->nivel === 'critico';
    }
}

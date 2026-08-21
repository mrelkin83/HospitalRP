<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Ports\NotificadorCorreo;
use RuntimeException;

/**
 * Doble del puerto de correo: registra los envíos y, si se le pide,
 * simula un transporte caído.
 */
final class NotificadorCorreoEspia implements NotificadorCorreo
{
    /** @var list<array{destinatario: string, asunto: string, texto: string}> */
    public array $enviados = [];

    public function __construct(private readonly bool $falla = false)
    {
    }

    public function enviar(string $destinatario, string $asunto, string $textoPlano): void
    {
        if ($this->falla) {
            throw new RuntimeException('Transporte de correo caído (simulado).');
        }

        $this->enviados[] = ['destinatario' => $destinatario, 'asunto' => $asunto, 'texto' => $textoPlano];
    }
}

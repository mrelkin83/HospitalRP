<?php

declare(strict_types=1);

namespace App\Ports;

/**
 * Puerto de envío de correo (CLAUDE.md §4: los servicios externos van
 * detrás de interfaces). El motor de trámites notifica por aquí; en local
 * el adaptador apunta a Mailpit y no sale ningún correo real.
 */
interface NotificadorCorreo
{
    /**
     * Envía un correo de texto plano. Lanza excepción si el transporte
     * falla; el llamador decide si eso rompe su operación (radicar NO se
     * rompe por un correo caído).
     */
    public function enviar(string $destinatario, string $asunto, string $textoPlano): void;
}

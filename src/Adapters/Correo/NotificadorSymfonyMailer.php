<?php

declare(strict_types=1);

namespace App\Adapters\Correo;

use App\Core\Config\Env;
use App\Ports\NotificadorCorreo;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Adaptador de correo sobre symfony/mailer. El DSN viene del entorno:
 * en local es Mailpit (smtp://127.0.0.1:1025) y nada sale de la máquina.
 */
final class NotificadorSymfonyMailer implements NotificadorCorreo
{
    private ?Mailer $mailer = null;

    public function __construct(
        private readonly string $dsn,
        private readonly string $remitente,
        private readonly string $remitenteNombre,
    ) {
    }

    public static function desde(Env $env): self
    {
        return new self(
            dsn: $env->texto('CORREO_DSN'),
            remitente: $env->texto('CORREO_REMITENTE'),
            remitenteNombre: $env->texto('CORREO_REMITENTE_NOMBRE', 'E.S.E. Hospital Rosario Pumarejo de López'),
        );
    }

    public function enviar(string $destinatario, string $asunto, string $textoPlano): void
    {
        $this->mailer ??= new Mailer(Transport::fromDsn($this->dsn));

        $this->mailer->send(
            (new Email())
                ->from(new Address($this->remitente, $this->remitenteNombre))
                ->to($destinatario)
                ->subject($asunto)
                ->text($textoPlano),
        );
    }
}

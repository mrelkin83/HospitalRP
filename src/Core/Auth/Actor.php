<?php

declare(strict_types=1);

namespace App\Core\Auth;

use Symfony\Component\HttpFoundation\Request;

/**
 * Quién está actuando y desde dónde: lo que la auditoría necesita saber de
 * cada acción del panel. Los controladores lo arman desde la sesión y la
 * petición; los servicios solo lo reciben.
 */
final readonly class Actor
{
    public function __construct(
        public ?int $usuarioId,
        public ?string $nombre,
        public ?string $ip,
        public ?string $userAgent,
    ) {
    }

    public static function desde(Sesion $sesion, Request $peticion): self
    {
        return new self(
            usuarioId: $sesion->usuarioId(),
            nombre: $sesion->usuarioNombre(),
            ip: $peticion->getClientIp(),
            userAgent: $peticion->headers->get('User-Agent'),
        );
    }
}

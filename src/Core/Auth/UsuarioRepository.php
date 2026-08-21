<?php

declare(strict_types=1);

namespace App\Core\Auth;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;

/**
 * Acceso a `usuarios` para el flujo de autenticación. Solo eso.
 */
final readonly class UsuarioRepository
{
    public function __construct(private Connection $conexion)
    {
    }

    public function porCorreo(string $correo): ?Usuario
    {
        $fila = $this->conexion->fetchAssociative(
            'SELECT * FROM usuarios WHERE correo = ? AND eliminado_en IS NULL',
            [$correo],
        );

        return $fila === false ? null : Usuario::desdeFila($fila);
    }

    public function registrarFallo(int $usuarioId, int $intentos, ?DateTimeImmutable $bloqueadoHasta): void
    {
        $this->conexion->executeStatement(
            'UPDATE usuarios SET intentos_fallidos = ?, bloqueado_hasta = ? WHERE id = ?',
            [$intentos, $bloqueadoHasta?->format('Y-m-d H:i:s'), $usuarioId],
        );
    }

    public function registrarAccesoCorrecto(int $usuarioId, ?string $ipBinaria): void
    {
        $this->conexion->executeStatement(
            'UPDATE usuarios
                SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_acceso = NOW(), ultima_ip = ?
              WHERE id = ?',
            [$ipBinaria, $usuarioId],
        );
    }
}

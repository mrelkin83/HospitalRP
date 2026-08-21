<?php

declare(strict_types=1);

namespace App\Core\Auth;

use Doctrine\DBAL\Connection;

/**
 * Permisos granulares (CLAUDE.md §6, Fase 0 paso 8).
 *
 * Un permiso es una cadena `modulo.accion` («contenido.publicar»,
 * «sistema.usuarios.crear»). El rol guarda una lista JSON de permisos y admite
 * comodines: `*` lo concede todo (rol administrador) y `contenido.*` concede
 * todo lo del módulo. La lista viene de la base: qué puede hacer cada rol se
 * administra desde el panel, no en código (regla de oro #2).
 */
final class AutorizacionService
{
    /** @var array<int, list<string>> Permisos por usuario, cacheados por petición */
    private array $cache = [];

    public function __construct(private readonly Connection $conexion)
    {
    }

    public function puede(int $usuarioId, string $permiso): bool
    {
        foreach ($this->permisosDe($usuarioId) as $concedido) {
            if ($concedido === '*' || $concedido === $permiso) {
                return true;
            }

            if (str_ends_with($concedido, '.*')
                && str_starts_with($permiso, substr($concedido, 0, -1))
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function permisosDe(int $usuarioId): array
    {
        if (isset($this->cache[$usuarioId])) {
            return $this->cache[$usuarioId];
        }

        // Un usuario desactivado o eliminado no conserva ningún permiso,
        // aunque su sesión siga viva: la revocación surte efecto de inmediato.
        $json = $this->conexion->fetchOne(
            'SELECT r.permisos
               FROM usuarios u
               JOIN roles r ON r.id = u.rol_id
              WHERE u.id = ? AND u.activo = 1 AND u.eliminado_en IS NULL',
            [$usuarioId],
        );

        if (!is_string($json)) {
            return $this->cache[$usuarioId] = [];
        }

        $decodificado = json_decode($json, true);

        $permisos = [];

        if (is_array($decodificado)) {
            foreach ($decodificado as $permiso) {
                if (is_string($permiso) && $permiso !== '') {
                    $permisos[] = $permiso;
                }
            }
        }

        return $this->cache[$usuarioId] = $permisos;
    }
}

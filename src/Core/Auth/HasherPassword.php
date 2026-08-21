<?php

declare(strict_types=1);

namespace App\Core\Auth;

/**
 * Contraseñas con Argon2id (CLAUDE.md §6, Seguridad).
 *
 * Los parámetros por defecto de PHP (64 MB de memoria, 4 iteraciones) son los
 * recomendados y se dejan tal cual: subirlos sin medir castiga el login en el
 * servidor real; bajarlos debilita el hash. `necesitaRehash` permite migrar
 * transparentemente si el día de mañana cambian los parámetros o el algoritmo.
 */
final readonly class HasherPassword
{
    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    public function verificar(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function necesitaRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID);
    }
}

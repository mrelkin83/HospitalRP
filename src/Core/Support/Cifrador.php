<?php

declare(strict_types=1);

namespace App\Core\Support;

/**
 * Cifrado simétrico (sodium secretbox) para secretos guardados en base de
 * datos: las claves de API que el funcionario configura desde el panel.
 *
 * Traído del módulo de proveedores de IA del proyecto SEPUD. La llave
 * maestra vive en el .env (APP_CLAVE_CIFRADO): un volcado de la base no
 * revela ninguna clave sin el .env del servidor, y el .env solo guarda
 * UNA llave de infraestructura en lugar de una clave por proveedor.
 */
final readonly class Cifrador
{
    private const string PREFIJO = 'cifrado:v1:';

    public function __construct(private string $claveBase64)
    {
    }

    public function disponible(): bool
    {
        return $this->clave() !== null;
    }

    public function cifrar(string $textoPlano): string
    {
        $clave = $this->clave();

        if ($clave === null) {
            throw new \RuntimeException('Defina APP_CLAVE_CIFRADO en el .env (32 bytes en base64) para poder guardar claves de API desde el panel.');
        }

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return self::PREFIJO . base64_encode($nonce . sodium_crypto_secretbox($textoPlano, $nonce, $clave));
    }

    /** Nulo cuando el valor está corrupto o la llave no es la correcta. */
    public function descifrar(string $almacenado): ?string
    {
        if (!str_starts_with($almacenado, self::PREFIJO)) {
            // Valor sin cifrar (compatibilidad con datos previos).
            return $almacenado;
        }

        $clave = $this->clave();

        if ($clave === null) {
            return null;
        }

        $crudo = base64_decode(substr($almacenado, strlen(self::PREFIJO)), true);

        if ($crudo === false || strlen($crudo) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        $abierto = sodium_crypto_secretbox_open(
            substr($crudo, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($crudo, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $clave,
        );

        return $abierto === false ? null : $abierto;
    }

    private function clave(): ?string
    {
        if ($this->claveBase64 === '') {
            return null;
        }

        $clave = base64_decode($this->claveBase64, true);

        return ($clave !== false && strlen($clave) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) ? $clave : null;
    }
}

<?php

declare(strict_types=1);

namespace App\Core\Auth;

/**
 * Reglas de una contraseña aceptable para el panel.
 *
 * Vive en un solo sitio porque se aplica en tres puntos distintos —alta de
 * usuario, restablecimiento por el administrador y cambio propio— y tres
 * copias de la misma regla acaban divergiendo: la que se olvida es siempre la
 * que deja entrar una contraseña de seis letras.
 *
 * El criterio sigue la guía del NIST SP 800-63B: longitud por encima de
 * composición caprichosa. No se exige símbolo raro ni caducidad periódica
 * (ambas cosas empujan al funcionario a anotar la clave en un papel); sí se
 * exige largo, algo de variedad y que no sea una de las claves de siempre.
 */
final readonly class PoliticaPassword
{
    public const int LARGO_MINIMO = 12;
    public const int LARGO_MAXIMO = 200;

    /**
     * Las que un atacante prueba primero. Lista corta y local: bloquear el
     * top mundial exigiría un diccionario de millones de entradas y eso es
     * trabajo del bloqueo por intentos fallidos, no de esta validación.
     *
     * @var list<string>
     */
    private const array PROHIBIDAS = [
        'password', 'contrasena', 'contraseña', 'hospital', 'administrador',
        '123456', '12345678', '123456789', 'qwerty', 'admin', 'bienvenido',
        'valledupar', 'rosariopumarejo', 'hrplopez', 'cambiarya',
    ];

    /**
     * Devuelve la lista de incumplimientos. Vacía significa aceptable.
     *
     * Se devuelven todos de una vez y no el primero: pedir la contraseña
     * cuatro veces seguidas, corrigiendo un defecto por intento, es la vía
     * rápida a que el usuario escriba «Hospital2026!» y la pegue en el monitor.
     *
     * @return list<string>
     */
    public function problemas(string $password, string $correo = '', string $nombre = ''): array
    {
        $problemas = [];
        $largo = mb_strlen($password);

        if ($largo < self::LARGO_MINIMO) {
            $problemas[] = sprintf('Debe tener al menos %d caracteres.', self::LARGO_MINIMO);
        }

        if ($largo > self::LARGO_MAXIMO) {
            $problemas[] = sprintf('No puede pasar de %d caracteres.', self::LARGO_MAXIMO);
        }

        if (preg_match('/\p{L}/u', $password) !== 1) {
            $problemas[] = 'Debe incluir al menos una letra.';
        }

        if (preg_match('/\d/', $password) !== 1) {
            $problemas[] = 'Debe incluir al menos un número.';
        }

        if (trim($password) !== $password) {
            $problemas[] = 'No puede empezar ni terminar con espacios.';
        }

        $normalizada = mb_strtolower($password);

        foreach (self::PROHIBIDAS as $prohibida) {
            if (str_contains($normalizada, $prohibida)) {
                $problemas[] = 'Es demasiado previsible: no uses palabras como «' . $prohibida . '».';
                break;
            }
        }

        // El usuario de ingreso y el nombre del funcionario son públicos: una
        // contraseña que los contenga no protege de quien ya tiene el listado
        // de servidores públicos del sitio.
        $usuarioDelCorreo = mb_strtolower((string) strstr($correo, '@', true));

        if ($usuarioDelCorreo !== '' && str_contains($normalizada, $usuarioDelCorreo)) {
            $problemas[] = 'No puede contener tu usuario de correo.';
        }

        foreach ($this->palabrasDe($nombre) as $palabra) {
            if (str_contains($normalizada, $palabra)) {
                $problemas[] = 'No puede contener tu nombre ni tus apellidos.';
                break;
            }
        }

        return array_values(array_unique($problemas));
    }

    public function aceptable(string $password, string $correo = '', string $nombre = ''): bool
    {
        return $this->problemas($password, $correo, $nombre) === [];
    }

    /**
     * Contraseña temporal para altas y restablecimientos.
     *
     * Sin caracteres ambiguos (0/O, 1/l/I) porque en la práctica se dicta por
     * teléfono, y con un dígito garantizado para que cumpla esta misma
     * política: una clave generada por el sistema que el sistema rechace
     * sería una broma pesada.
     */
    public function generar(int $largo = 16): string
    {
        $alfabeto = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ';
        $digitos = '23456789';

        $fuente = $alfabeto . $digitos;
        $caracteres = [$digitos[random_int(0, strlen($digitos) - 1)]];

        for ($i = 1; $i < max($largo, self::LARGO_MINIMO); $i++) {
            $caracteres[] = $fuente[random_int(0, strlen($fuente) - 1)];
        }

        // Barajado con random_int, no str_shuffle: esa usa el generador
        // rápido de PHP, no el criptográfico, y aquí el resultado es una
        // credencial.
        for ($i = count($caracteres) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$caracteres[$i], $caracteres[$j]] = [$caracteres[$j], $caracteres[$i]];
        }

        return implode('', $caracteres);
    }

    /**
     * @return list<string> Palabras del nombre con peso identificatorio
     */
    private function palabrasDe(string $nombre): array
    {
        $palabras = [];

        foreach (preg_split('/\s+/u', mb_strtolower(trim($nombre))) ?: [] as $palabra) {
            // «de», «la», «del» aparecen en medio mundo de nombres compuestos
            // y prohibirlas no aporta nada.
            if (mb_strlen($palabra) >= 4) {
                $palabras[] = $palabra;
            }
        }

        return $palabras;
    }
}

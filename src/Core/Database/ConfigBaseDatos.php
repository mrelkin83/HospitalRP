<?php

declare(strict_types=1);

namespace App\Core\Database;

use App\Core\Config\Env;

/**
 * Configuración de conexión a la base de datos.
 *
 * Igual que ConfigAplicacion: aquí solo vive lo que cambia entre servidores.
 * Es el único lugar del sistema que conoce las variables DB_*; el resto del
 * código recibe una Connection ya construida por el contenedor.
 */
final readonly class ConfigBaseDatos
{
    public function __construct(
        public string $host,
        public int $puerto,
        public string $nombre,
        public string $usuario,
        public string $password,
    ) {
    }

    public static function desde(Env $env): self
    {
        return new self(
            host: $env->texto('DB_HOST', '127.0.0.1'),
            puerto: $env->entero('DB_PUERTO', 3306),
            nombre: $env->texto('DB_NOMBRE'),
            usuario: $env->texto('DB_USUARIO'),
            // En local (Laragon) root no tiene contraseña; en producción la
            // variable es obligatoria y un valor vacío debe sonar raro al DBA.
            password: $env->texto('DB_PASSWORD', ''),
        );
    }

    /**
     * Parámetros en el formato que espera Doctrine DBAL.
     *
     * utf8mb4 en la conexión y no solo en las tablas: sin esto, un emoji en un
     * PQRSDF se corrompe al viajar aunque la columna lo soporte.
     *
     * @return array{
     *     driver: 'pdo_mysql',
     *     host: string,
     *     port: int,
     *     dbname: string,
     *     user: string,
     *     password: string,
     *     charset: 'utf8mb4',
     * }
     */
    public function comoParametrosDbal(): array
    {
        return [
            'driver' => 'pdo_mysql',
            'host' => $this->host,
            'port' => $this->puerto,
            'dbname' => $this->nombre,
            'user' => $this->usuario,
            'password' => $this->password,
            'charset' => 'utf8mb4',
        ];
    }
}

<?php

declare(strict_types=1);

/**
 * Configuración de Doctrine Migrations.
 *
 * `all_or_nothing`: una tanda de migraciones se aplica completa o no se aplica.
 * Un fallo a mitad de camino no deja el esquema en un estado intermedio que
 * nadie sabe revertir. (Las sentencias DDL de MySQL hacen commit implícito,
 * así que esto protege la tanda, no cada CREATE TABLE individual.)
 */

return [
    'table_storage' => [
        'table_name' => 'doctrine_migraciones',
    ],
    'migrations_paths' => [
        'Database\\Migrations' => 'database/migrations',
    ],
    'all_or_nothing' => true,
    'transactional' => true,
    'check_database_platform' => true,
];

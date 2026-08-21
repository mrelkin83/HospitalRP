<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Grupo IA del panel (Fase 8), copiado de database/esquema.sql §10.
 */
final class Version20260822000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Registro de uso y consumo de la IA del panel (ia_usos)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE ia_usos (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              funcion         ENUM('resumen_documento','sugerencia_etiquetas','texto_alternativo','metadatos_seo','borrador_noticia') NOT NULL,
              proveedor       VARCHAR(40)  NOT NULL,
              modelo          VARCHAR(80)  NOT NULL,
              tokens_entrada  INT UNSIGNED NOT NULL DEFAULT 0,
              tokens_salida   INT UNSIGNED NOT NULL DEFAULT 0,
              duracion_ms     INT UNSIGNED NOT NULL DEFAULT 0,
              exito           TINYINT(1)   NOT NULL DEFAULT 1,
              error           VARCHAR(255) NULL,
              usuario_id      BIGINT UNSIGNED NULL,
              creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_iausos_fecha (creado_en),
              KEY idx_iausos_funcion (funcion, creado_en),
              CONSTRAINT fk_iausos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
            ) ENGINE=InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ia_usos');
    }
}

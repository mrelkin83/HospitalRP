<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Convocatorias (Fase 9, bloque B4), copiado de database/esquema.sql §7.
 *
 * Las tablas estaban en el esquema desde la Fase 0 pero nunca se migraron, así
 * que el tipo `convocatoria` del índice de búsqueda quedó desactivado y las
 * ofertas de empleo del Joomla no tenían dónde aterrizar (docs/13 §2.3).
 */
final class Version20260914120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convocatorias de empleo, contratación y proveedores';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE convocatorias (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              titulo          VARCHAR(300) NOT NULL,
              slug            VARCHAR(320) NOT NULL,
              tipo            ENUM('empleo','contratacion','proveedores','otra') NOT NULL,
              descripcion     MEDIUMTEXT   NOT NULL,
              dependencia_id  BIGINT UNSIGNED NULL,
              estado          ENUM('proxima','abierta','en_evaluacion','cerrada','desierta','adjudicada') NOT NULL DEFAULT 'proxima',
              apertura        DATETIME     NULL,
              cierre          DATETIME     NULL,
              url_externa     VARCHAR(500) NULL,
              publicada       TINYINT(1)   NOT NULL DEFAULT 0,
              eliminado_en    DATETIME     NULL,
              creado_por      BIGINT UNSIGNED NULL,
              creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_convocatorias_slug (slug),
              KEY idx_convocatorias_estado (estado, publicada, cierre),
              CONSTRAINT fk_convocatorias_dependencia FOREIGN KEY (dependencia_id) REFERENCES dependencias (id) ON DELETE SET NULL
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE convocatoria_documentos (
              convocatoria_id BIGINT UNSIGNED NOT NULL,
              documento_id    BIGINT UNSIGNED NOT NULL,
              orden           SMALLINT     NOT NULL DEFAULT 0,
              PRIMARY KEY (convocatoria_id, documento_id),
              CONSTRAINT fk_cd_convocatoria FOREIGN KEY (convocatoria_id) REFERENCES convocatorias (id) ON DELETE CASCADE,
              CONSTRAINT fk_cd_documento FOREIGN KEY (documento_id) REFERENCES documentos (id) ON DELETE CASCADE
            ) ENGINE=InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE convocatoria_documentos');
        $this->addSql('DROP TABLE convocatorias');
    }
}

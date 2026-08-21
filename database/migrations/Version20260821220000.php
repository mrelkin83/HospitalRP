<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Grupo Transparencia y datos abiertos (Fase 4, paso 1), copiado de
 * database/esquema.sql. La estructura del Anexo 2 se siembra con el comando
 * `semillas:transparencia`, no aquí: la semilla es editable y la migración no.
 */
final class Version20260821220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grupo Transparencia: transparencia_secciones, transparencia_items, datasets, dataset_recursos';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE transparencia_secciones (
              id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              codigo              VARCHAR(20)  NOT NULL,
              nombre              VARCHAR(300) NOT NULL,
              descripcion         TEXT         NULL,
              padre_id            BIGINT UNSIGNED NULL,
              orden               SMALLINT     NOT NULL DEFAULT 0,
              obligatoria         TINYINT(1)   NOT NULL DEFAULT 1,
              aplica              TINYINT(1)   NOT NULL DEFAULT 1,
              periodicidad        ENUM('ninguna','mensual','trimestral','semestral','anual','permanente') NOT NULL DEFAULT 'ninguna',
              dependencia_id      BIGINT UNSIGNED NULL,
              ultima_actualizacion DATE        NULL,
              proxima_actualizacion DATE       NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_transp_codigo (codigo),
              KEY idx_transp_padre (padre_id),
              CONSTRAINT fk_transp_padre FOREIGN KEY (padre_id) REFERENCES transparencia_secciones (id) ON DELETE CASCADE,
              CONSTRAINT fk_transp_dependencia FOREIGN KEY (dependencia_id) REFERENCES dependencias (id) ON DELETE SET NULL
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE transparencia_items (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              seccion_id      BIGINT UNSIGNED NOT NULL,
              documento_id    BIGINT UNSIGNED NULL,
              url_externa     VARCHAR(500) NULL,
              titulo          VARCHAR(300) NOT NULL,
              descripcion     VARCHAR(500) NULL,
              orden           SMALLINT     NOT NULL DEFAULT 0,
              publicado       TINYINT(1)   NOT NULL DEFAULT 1,
              creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_transpitem_seccion (seccion_id, orden),
              CONSTRAINT fk_transpitem_seccion FOREIGN KEY (seccion_id) REFERENCES transparencia_secciones (id) ON DELETE CASCADE,
              -- CASCADE y no SET NULL: MySQL 8 (error 3823) prohíbe el CHECK
              -- sobre una columna con SET NULL, y un ítem sin destino es
              -- basura de todos modos. Los documentos usan borrado lógico,
              -- así que esta acción en la práctica no se dispara.
              CONSTRAINT fk_transpitem_documento FOREIGN KEY (documento_id) REFERENCES documentos (id) ON DELETE CASCADE,
              CONSTRAINT chk_transpitem_destino CHECK (documento_id IS NOT NULL OR url_externa IS NOT NULL)
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE datasets (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              titulo          VARCHAR(300) NOT NULL,
              slug            VARCHAR(320) NOT NULL,
              descripcion     TEXT         NOT NULL,
              dependencia_id  BIGINT UNSIGNED NULL,
              periodicidad    ENUM('ninguna','mensual','trimestral','semestral','anual') NOT NULL DEFAULT 'anual',
              licencia        VARCHAR(160) NOT NULL DEFAULT 'Datos abiertos - Ley 1712 de 2014',
              cobertura_desde DATE         NULL,
              cobertura_hasta DATE         NULL,
              publicado       TINYINT(1)   NOT NULL DEFAULT 0,
              creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_datasets_slug (slug),
              CONSTRAINT fk_datasets_dependencia FOREIGN KEY (dependencia_id) REFERENCES dependencias (id) ON DELETE SET NULL
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE dataset_recursos (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              dataset_id      BIGINT UNSIGNED NOT NULL,
              formato         ENUM('csv','json','xml','xlsx','geojson') NOT NULL,
              archivo_id      BIGINT UNSIGNED NULL,
              url_externa     VARCHAR(500) NULL,
              descargas       INT UNSIGNED NOT NULL DEFAULT 0,
              creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_dsrec_dataset (dataset_id),
              CONSTRAINT fk_dsrec_dataset FOREIGN KEY (dataset_id) REFERENCES datasets (id) ON DELETE CASCADE,
              CONSTRAINT fk_dsrec_archivo FOREIGN KEY (archivo_id) REFERENCES archivos (id) ON DELETE SET NULL
            ) ENGINE=InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE dataset_recursos');
        $this->addSql('DROP TABLE datasets');
        $this->addSql('DROP TABLE transparencia_items');
        $this->addSql('DROP TABLE transparencia_secciones');
    }
}

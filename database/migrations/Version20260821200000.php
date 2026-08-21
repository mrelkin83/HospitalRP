<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Grupo Documental (Fase 3, paso 1), copiado de database/esquema.sql.
 *
 * Un documento es un registro con metadatos, no un archivo suelto
 * (CLAUDE.md §6): serie y subserie de las TRD del AGN, vigencia,
 * `reemplaza_a_id` para normas derogadas y FULLTEXT para el buscador.
 */
final class Version20260821200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grupo Documental: etiquetas, documentos, documento_versiones, documento_etiqueta';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE etiquetas (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              nombre          VARCHAR(80)  NOT NULL,
              slug            VARCHAR(100) NOT NULL,
              usos            INT UNSIGNED NOT NULL DEFAULT 0,
              PRIMARY KEY (id),
              UNIQUE KEY uq_etiquetas_slug (slug)
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE documentos (
              id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              titulo              VARCHAR(300) NOT NULL,
              slug                VARCHAR(320) NOT NULL,
              resumen             VARCHAR(1000) NOT NULL,
              archivo_id          BIGINT UNSIGNED NOT NULL,
              categoria_id        BIGINT UNSIGNED NULL,
              dependencia_id      BIGINT UNSIGNED NULL,
              numero_acto         VARCHAR(80)  NULL,
              fecha_documento     DATE         NOT NULL,
              vigencia_desde      DATE         NULL,
              vigencia_hasta      DATE         NULL,
              version             INT UNSIGNED NOT NULL DEFAULT 1,
              estado              ENUM('borrador','vigente','derogado','historico') NOT NULL DEFAULT 'borrador',
              reemplaza_a_id      BIGINT UNSIGNED NULL,
              serie_documental    VARCHAR(160) NULL,
              subserie_documental VARCHAR(160) NULL,
              tiempo_retencion    SMALLINT     NULL,
              publicado           TINYINT(1)   NOT NULL DEFAULT 0,
              descargas           INT UNSIGNED NOT NULL DEFAULT 0,
              eliminado_en        DATETIME     NULL,
              creado_por          BIGINT UNSIGNED NULL,
              actualizado_por     BIGINT UNSIGNED NULL,
              creado_en           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_documentos_slug (slug),
              KEY idx_documentos_estado (estado, publicado, eliminado_en),
              KEY idx_documentos_dependencia (dependencia_id),
              KEY idx_documentos_categoria (categoria_id),
              KEY idx_documentos_vigencia (vigencia_hasta),
              KEY idx_documentos_fecha (fecha_documento),
              FULLTEXT KEY ft_documentos (titulo, resumen),
              CONSTRAINT fk_documentos_archivo FOREIGN KEY (archivo_id) REFERENCES archivos (id) ON DELETE RESTRICT,
              CONSTRAINT fk_documentos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias (id) ON DELETE SET NULL,
              CONSTRAINT fk_documentos_dependencia FOREIGN KEY (dependencia_id) REFERENCES dependencias (id) ON DELETE SET NULL,
              CONSTRAINT fk_documentos_reemplaza FOREIGN KEY (reemplaza_a_id) REFERENCES documentos (id) ON DELETE SET NULL
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE documento_versiones (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              documento_id    BIGINT UNSIGNED NOT NULL,
              version         INT UNSIGNED NOT NULL,
              archivo_id      BIGINT UNSIGNED NOT NULL,
              notas           VARCHAR(500) NULL,
              creado_por      BIGINT UNSIGNED NULL,
              creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_documento_version (documento_id, version),
              CONSTRAINT fk_docver_documento FOREIGN KEY (documento_id) REFERENCES documentos (id) ON DELETE CASCADE,
              CONSTRAINT fk_docver_archivo FOREIGN KEY (archivo_id) REFERENCES archivos (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE documento_etiqueta (
              documento_id    BIGINT UNSIGNED NOT NULL,
              etiqueta_id     BIGINT UNSIGNED NOT NULL,
              PRIMARY KEY (documento_id, etiqueta_id),
              CONSTRAINT fk_de_documento FOREIGN KEY (documento_id) REFERENCES documentos (id) ON DELETE CASCADE,
              CONSTRAINT fk_de_etiqueta FOREIGN KEY (etiqueta_id) REFERENCES etiquetas (id) ON DELETE CASCADE
            ) ENGINE=InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE documento_etiqueta');
        $this->addSql('DROP TABLE documento_versiones');
        $this->addSql('DROP TABLE documentos');
        $this->addSql('DROP TABLE etiquetas');
    }
}

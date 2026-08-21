<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Grupo Directorio (Fase 2, paso 1), copiado de database/esquema.sql.
 *
 * `profesionales` publica datos personales: la fila solo sale al sitio con
 * `autoriza_publicacion = 1` y jamás almacena teléfonos ni correos
 * personales (Ley 1581).
 */
final class Version20260821180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grupo Directorio: sedes, especialidades, servicios, profesionales y sus relaciones';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE sedes (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              nombre          VARCHAR(160) NOT NULL,
              slug            VARCHAR(180) NOT NULL,
              descripcion     TEXT         NULL,
              direccion       VARCHAR(255) NOT NULL,
              municipio       VARCHAR(120) NOT NULL,
              departamento    VARCHAR(120) NOT NULL DEFAULT 'Cesar',
              telefono        VARCHAR(60)  NULL,
              correo          VARCHAR(160) NULL,
              horario         JSON         NULL,
              latitud         DECIMAL(10,7) NULL,
              longitud        DECIMAL(10,7) NULL,
              tiene_urgencias TINYINT(1)   NOT NULL DEFAULT 0,
              imagen_id       BIGINT UNSIGNED NULL,
              orden           SMALLINT     NOT NULL DEFAULT 0,
              activa          TINYINT(1)   NOT NULL DEFAULT 1,
              creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_sedes_slug (slug),
              CONSTRAINT fk_sedes_imagen FOREIGN KEY (imagen_id) REFERENCES archivos (id) ON DELETE SET NULL
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE especialidades (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              nombre          VARCHAR(160) NOT NULL,
              slug            VARCHAR(180) NOT NULL,
              descripcion     TEXT         NULL,
              icono           VARCHAR(60)  NULL,
              imagen_id       BIGINT UNSIGNED NULL,
              orden           SMALLINT     NOT NULL DEFAULT 0,
              activa          TINYINT(1)   NOT NULL DEFAULT 1,
              creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_especialidades_slug (slug),
              CONSTRAINT fk_especialidades_imagen FOREIGN KEY (imagen_id) REFERENCES archivos (id) ON DELETE SET NULL
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE servicios (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              nombre          VARCHAR(160) NOT NULL,
              slug            VARCHAR(180) NOT NULL,
              descripcion     TEXT         NULL,
              requisitos      TEXT         NULL,
              horario         VARCHAR(500) NULL,
              telefono        VARCHAR(60)  NULL,
              icono           VARCHAR(60)  NULL,
              imagen_id       BIGINT UNSIGNED NULL,
              orden           SMALLINT     NOT NULL DEFAULT 0,
              activo          TINYINT(1)   NOT NULL DEFAULT 1,
              creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_servicios_slug (slug),
              CONSTRAINT fk_servicios_imagen FOREIGN KEY (imagen_id) REFERENCES archivos (id) ON DELETE SET NULL
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE profesionales (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              nombre          VARCHAR(200) NOT NULL,
              slug            VARCHAR(220) NOT NULL,
              registro_rethus VARCHAR(60)  NULL,
              perfil          TEXT         NULL,
              foto_id         BIGINT UNSIGNED NULL,
              autoriza_publicacion TINYINT(1) NOT NULL DEFAULT 0,
              orden           SMALLINT     NOT NULL DEFAULT 0,
              activo          TINYINT(1)   NOT NULL DEFAULT 1,
              creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_profesionales_slug (slug),
              CONSTRAINT fk_profesionales_foto FOREIGN KEY (foto_id) REFERENCES archivos (id) ON DELETE SET NULL
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE servicio_sede (
              servicio_id     BIGINT UNSIGNED NOT NULL,
              sede_id         BIGINT UNSIGNED NOT NULL,
              PRIMARY KEY (servicio_id, sede_id),
              CONSTRAINT fk_ss_servicio FOREIGN KEY (servicio_id) REFERENCES servicios (id) ON DELETE CASCADE,
              CONSTRAINT fk_ss_sede FOREIGN KEY (sede_id) REFERENCES sedes (id) ON DELETE CASCADE
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE especialidad_sede (
              especialidad_id BIGINT UNSIGNED NOT NULL,
              sede_id         BIGINT UNSIGNED NOT NULL,
              PRIMARY KEY (especialidad_id, sede_id),
              CONSTRAINT fk_es_especialidad FOREIGN KEY (especialidad_id) REFERENCES especialidades (id) ON DELETE CASCADE,
              CONSTRAINT fk_es_sede FOREIGN KEY (sede_id) REFERENCES sedes (id) ON DELETE CASCADE
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE profesional_especialidad (
              profesional_id  BIGINT UNSIGNED NOT NULL,
              especialidad_id BIGINT UNSIGNED NOT NULL,
              PRIMARY KEY (profesional_id, especialidad_id),
              CONSTRAINT fk_pe_profesional FOREIGN KEY (profesional_id) REFERENCES profesionales (id) ON DELETE CASCADE,
              CONSTRAINT fk_pe_especialidad FOREIGN KEY (especialidad_id) REFERENCES especialidades (id) ON DELETE CASCADE
            ) ENGINE=InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE profesional_especialidad');
        $this->addSql('DROP TABLE especialidad_sede');
        $this->addSql('DROP TABLE servicio_sede');
        $this->addSql('DROP TABLE profesionales');
        $this->addSql('DROP TABLE servicios');
        $this->addSql('DROP TABLE especialidades');
        $this->addSql('DROP TABLE sedes');
    }
}

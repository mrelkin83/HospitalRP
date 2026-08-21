<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Grupo Trámites (Fase 5, paso 1), copiado de database/esquema.sql, más
 * `festivos` (cálculo de días hábiles, Ley 1755) y —adelantadas del grupo
 * Cumplimiento— `politicas` y `autorizaciones_datos`: ningún formulario se
 * envía sin casilla de autorización y su evidencia (Ley 1581).
 */
final class Version20260821230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grupo Trámites completo + festivos + politicas + autorizaciones_datos';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE festivos (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              fecha           DATE         NOT NULL,
              nombre          VARCHAR(120) NOT NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_festivos_fecha (fecha)
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE politicas (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              tipo            ENUM('tratamiento_datos','aviso_privacidad','cookies','terminos','accesibilidad','seguridad') NOT NULL,
              version         VARCHAR(20)  NOT NULL,
              titulo          VARCHAR(255) NOT NULL,
              contenido       MEDIUMTEXT   NOT NULL,
              documento_id    BIGINT UNSIGNED NULL,
              vigente_desde   DATE         NOT NULL,
              vigente_hasta   DATE         NULL,
              activa          TINYINT(1)   NOT NULL DEFAULT 0,
              creado_por      BIGINT UNSIGNED NULL,
              creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_politicas_tipo_version (tipo, version),
              KEY idx_politicas_activa (tipo, activa),
              CONSTRAINT fk_politicas_documento FOREIGN KEY (documento_id) REFERENCES documentos (id) ON DELETE SET NULL
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE autorizaciones_datos (
              id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              origen            ENUM('tramite','contacto','suscripcion','otro') NOT NULL,
              origen_id         BIGINT UNSIGNED NULL,
              numero_documento  VARCHAR(40)  NULL,
              correo            VARCHAR(160) NULL,
              politica_id       BIGINT UNSIGNED NOT NULL,
              finalidad         VARCHAR(500) NOT NULL,
              aceptado          TINYINT(1)   NOT NULL,
              ip                VARBINARY(16) NOT NULL,
              user_agent        VARCHAR(500) NULL,
              revocado_en       DATETIME     NULL,
              creado_en         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_autoriz_origen (origen, origen_id),
              KEY idx_autoriz_documento (numero_documento),
              CONSTRAINT fk_autoriz_politica FOREIGN KEY (politica_id) REFERENCES politicas (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE tipos_tramite (
              id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              nombre                VARCHAR(200) NOT NULL,
              slug                  VARCHAR(220) NOT NULL,
              descripcion           TEXT         NULL,
              instrucciones         TEXT         NULL,
              prefijo_radicado      VARCHAR(20)  NOT NULL DEFAULT 'HRPL',
              dependencia_id        BIGINT UNSIGNED NULL,
              dias_termino          SMALLINT     NOT NULL DEFAULT 15,
              tipo_dias             ENUM('habiles','calendario') NOT NULL DEFAULT 'habiles',
              fundamento_legal      VARCHAR(255) NULL,
              permite_anonimo       TINYINT(1)   NOT NULL DEFAULT 0,
              requiere_adjunto      TINYINT(1)   NOT NULL DEFAULT 0,
              max_adjuntos          SMALLINT     NOT NULL DEFAULT 3,
              notificar_correos     VARCHAR(500) NULL,
              icono                 VARCHAR(60)  NULL,
              orden                 SMALLINT     NOT NULL DEFAULT 0,
              activo                TINYINT(1)   NOT NULL DEFAULT 1,
              creado_en             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_tipostramite_slug (slug),
              CONSTRAINT fk_tipostramite_dependencia FOREIGN KEY (dependencia_id) REFERENCES dependencias (id) ON DELETE SET NULL
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE tramite_estados (
              id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              tipo_tramite_id     BIGINT UNSIGNED NOT NULL,
              codigo              VARCHAR(60)  NOT NULL,
              nombre              VARCHAR(120) NOT NULL,
              descripcion_publica VARCHAR(255) NULL,
              color               CHAR(7)      NULL,
              orden               SMALLINT     NOT NULL DEFAULT 0,
              es_inicial          TINYINT(1)   NOT NULL DEFAULT 0,
              es_final            TINYINT(1)   NOT NULL DEFAULT 0,
              detiene_termino     TINYINT(1)   NOT NULL DEFAULT 0,
              notifica_ciudadano  TINYINT(1)   NOT NULL DEFAULT 1,
              PRIMARY KEY (id),
              UNIQUE KEY uq_tramestado (tipo_tramite_id, codigo),
              CONSTRAINT fk_tramestado_tipo FOREIGN KEY (tipo_tramite_id) REFERENCES tipos_tramite (id) ON DELETE CASCADE
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE tramite_campos (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              tipo_tramite_id BIGINT UNSIGNED NOT NULL,
              codigo          VARCHAR(60)  NOT NULL,
              etiqueta        VARCHAR(200) NOT NULL,
              ayuda           VARCHAR(255) NULL,
              tipo_campo      ENUM('texto','texto_largo','numero','fecha','correo','telefono','select','multiselect','radio','checkbox','archivo','documento_id') NOT NULL,
              opciones        JSON         NULL,
              requerido       TINYINT(1)   NOT NULL DEFAULT 0,
              validacion      VARCHAR(255) NULL,
              orden           SMALLINT     NOT NULL DEFAULT 0,
              grupo           VARCHAR(80)  NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_tramcampo (tipo_tramite_id, codigo),
              CONSTRAINT fk_tramcampo_tipo FOREIGN KEY (tipo_tramite_id) REFERENCES tipos_tramite (id) ON DELETE CASCADE
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE tramites (
              id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              radicado            VARCHAR(40)  NOT NULL,
              tipo_tramite_id     BIGINT UNSIGNED NOT NULL,
              canal               ENUM('web','presencial','telefonico','correo','buzon','whatsapp') NOT NULL DEFAULT 'web',
              anonimo             TINYINT(1)   NOT NULL DEFAULT 0,
              nombre              VARCHAR(200) NULL,
              tipo_documento      ENUM('CC','TI','CE','PA','RC','NIT','PEP','NUIP','OTRO') NULL,
              numero_documento    VARCHAR(40)  NULL,
              correo              VARCHAR(160) NULL,
              telefono            VARCHAR(60)  NULL,
              direccion           VARCHAR(255) NULL,
              municipio           VARCHAR(120) NULL,
              departamento        VARCHAR(120) NULL,
              poblacion_especial  VARCHAR(120) NULL,
              eps                 VARCHAR(160) NULL,
              regimen             ENUM('contributivo','subsidiado','especial','vinculado','particular') NULL,
              asunto              VARCHAR(300) NOT NULL,
              descripcion         TEXT         NOT NULL,
              dependencia_id      BIGINT UNSIGNED NULL,
              asignado_a          BIGINT UNSIGNED NULL,
              estado_id           BIGINT UNSIGNED NOT NULL,
              medio_respuesta     ENUM('correo','fisico','telefono','presencial') NOT NULL DEFAULT 'correo',
              recibido_en         DATETIME     NOT NULL,
              dias_termino        SMALLINT     NOT NULL,
              vence_en            DATE         NOT NULL,
              respondido_en       DATETIME     NULL,
              cerrado_en          DATETIME     NULL,
              dias_habiles_usados SMALLINT     NULL,
              ip                  VARBINARY(16) NULL,
              user_agent          VARCHAR(500) NULL,
              eliminado_en        DATETIME     NULL,
              creado_en           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_tramites_radicado (radicado),
              KEY idx_tramites_tipo (tipo_tramite_id),
              KEY idx_tramites_estado (estado_id),
              KEY idx_tramites_vence (vence_en, respondido_en),
              KEY idx_tramites_documento (numero_documento),
              KEY idx_tramites_dependencia (dependencia_id),
              CONSTRAINT fk_tramites_tipo FOREIGN KEY (tipo_tramite_id) REFERENCES tipos_tramite (id) ON DELETE RESTRICT,
              CONSTRAINT fk_tramites_estado FOREIGN KEY (estado_id) REFERENCES tramite_estados (id) ON DELETE RESTRICT,
              CONSTRAINT fk_tramites_dependencia FOREIGN KEY (dependencia_id) REFERENCES dependencias (id) ON DELETE SET NULL,
              CONSTRAINT fk_tramites_asignado FOREIGN KEY (asignado_a) REFERENCES usuarios (id) ON DELETE SET NULL
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE radicado_secuencias (
              anio            SMALLINT     NOT NULL,
              prefijo         VARCHAR(20)  NOT NULL,
              ultimo          INT UNSIGNED NOT NULL DEFAULT 0,
              PRIMARY KEY (anio, prefijo)
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE tramite_valores (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              tramite_id      BIGINT UNSIGNED NOT NULL,
              campo_id        BIGINT UNSIGNED NOT NULL,
              valor           TEXT         NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_tramvalor (tramite_id, campo_id),
              CONSTRAINT fk_tramvalor_tramite FOREIGN KEY (tramite_id) REFERENCES tramites (id) ON DELETE CASCADE,
              CONSTRAINT fk_tramvalor_campo FOREIGN KEY (campo_id) REFERENCES tramite_campos (id) ON DELETE CASCADE
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE tramite_seguimiento (
              id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              tramite_id          BIGINT UNSIGNED NOT NULL,
              estado_anterior_id  BIGINT UNSIGNED NULL,
              estado_nuevo_id     BIGINT UNSIGNED NULL,
              nota                TEXT         NULL,
              visible_ciudadano   TINYINT(1)   NOT NULL DEFAULT 0,
              usuario_id          BIGINT UNSIGNED NULL,
              creado_en           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_tramseg_tramite (tramite_id, creado_en),
              CONSTRAINT fk_tramseg_tramite FOREIGN KEY (tramite_id) REFERENCES tramites (id) ON DELETE CASCADE,
              CONSTRAINT fk_tramseg_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE tramite_adjuntos (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              tramite_id      BIGINT UNSIGNED NOT NULL,
              archivo_id      BIGINT UNSIGNED NOT NULL,
              origen          ENUM('ciudadano','hospital') NOT NULL,
              descripcion     VARCHAR(255) NULL,
              creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_tramadj_tramite (tramite_id),
              CONSTRAINT fk_tramadj_tramite FOREIGN KEY (tramite_id) REFERENCES tramites (id) ON DELETE CASCADE,
              CONSTRAINT fk_tramadj_archivo FOREIGN KEY (archivo_id) REFERENCES archivos (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tramite_adjuntos');
        $this->addSql('DROP TABLE tramite_seguimiento');
        $this->addSql('DROP TABLE tramite_valores');
        $this->addSql('DROP TABLE radicado_secuencias');
        $this->addSql('DROP TABLE tramites');
        $this->addSql('DROP TABLE tramite_campos');
        $this->addSql('DROP TABLE tramite_estados');
        $this->addSql('DROP TABLE tipos_tramite');
        $this->addSql('DROP TABLE autorizaciones_datos');
        $this->addSql('DROP TABLE politicas');
        $this->addSql('DROP TABLE festivos');
    }
}

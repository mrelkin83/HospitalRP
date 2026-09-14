<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Calendario de eventos y encuestas de satisfacción (Fase 9, bloques B6 y D2).
 *
 * El calendario lo exigen los ítems 1.12 y 6.5 del ITA. Las encuestas entraron
 * al alcance por decisión del hospital del 2026-09-14 (CLAUDE.md §7).
 *
 * Las encuestas son ANÓNIMAS por diseño: no hay columna de identificación del
 * ciudadano, ni cédula, ni correo, ni número de historia. `huella` es un hash
 * con sal para frenar el envío repetido desde el mismo navegador, y no
 * identifica a nadie. Regla §2.6 de CLAUDE.md: aquí no entra dato clínico.
 */
final class Version20260914130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Calendario de eventos y encuestas de satisfacción anónimas';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE eventos (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              titulo          VARCHAR(300) NOT NULL,
              slug            VARCHAR(320) NOT NULL,
              descripcion     MEDIUMTEXT   NULL,
              tipo            ENUM('institucional','participacion_ciudadana','rendicion_cuentas','capacitacion','jornada_salud','otro') NOT NULL DEFAULT 'institucional',
              inicio          DATETIME     NOT NULL,
              fin             DATETIME     NULL,
              todo_el_dia     TINYINT(1)   NOT NULL DEFAULT 0,
              lugar           VARCHAR(300) NULL,
              url_externa     VARCHAR(500) NULL,
              publicado       TINYINT(1)   NOT NULL DEFAULT 0,
              eliminado_en    DATETIME     NULL,
              creado_por      BIGINT UNSIGNED NULL,
              creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_eventos_slug (slug),
              KEY idx_eventos_agenda (publicado, inicio),
              CONSTRAINT fk_eventos_usuario FOREIGN KEY (creado_por) REFERENCES usuarios (id) ON DELETE SET NULL
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE encuestas (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              titulo          VARCHAR(300) NOT NULL,
              slug            VARCHAR(320) NOT NULL,
              descripcion     TEXT         NULL,
              estado          ENUM('borrador','abierta','cerrada') NOT NULL DEFAULT 'borrador',
              apertura        DATETIME     NULL,
              cierre          DATETIME     NULL,
              mensaje_cierre  VARCHAR(500) NULL,
              eliminado_en    DATETIME     NULL,
              creado_por      BIGINT UNSIGNED NULL,
              creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_encuestas_slug (slug),
              KEY idx_encuestas_estado (estado, cierre),
              CONSTRAINT fk_encuestas_usuario FOREIGN KEY (creado_por) REFERENCES usuarios (id) ON DELETE SET NULL
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE encuesta_preguntas (
              id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              encuesta_id  BIGINT UNSIGNED NOT NULL,
              enunciado    VARCHAR(500) NOT NULL,
              tipo         ENUM('escala','opcion_unica','opcion_multiple','texto') NOT NULL DEFAULT 'escala',
              opciones     JSON         NULL,
              obligatoria  TINYINT(1)   NOT NULL DEFAULT 1,
              orden        SMALLINT     NOT NULL DEFAULT 0,
              PRIMARY KEY (id),
              KEY idx_encpreg_encuesta (encuesta_id, orden),
              CONSTRAINT fk_encpreg_encuesta FOREIGN KEY (encuesta_id) REFERENCES encuestas (id) ON DELETE CASCADE
            ) ENGINE=InnoDB
            SQL);

        // Sin columna que identifique al ciudadano: ni nombre, ni documento,
        // ni correo. `huella` es SHA-256 con sal de la sesión; sirve para
        // frenar el reenvío, no para saber quién respondió.
        $this->addSql(<<<'SQL'
            CREATE TABLE encuesta_respuestas (
              id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              encuesta_id  BIGINT UNSIGNED NOT NULL,
              huella       CHAR(64)     NULL,
              enviado_en   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_encresp_huella (encuesta_id, huella),
              KEY idx_encresp_encuesta (encuesta_id, enviado_en),
              CONSTRAINT fk_encresp_encuesta FOREIGN KEY (encuesta_id) REFERENCES encuestas (id) ON DELETE CASCADE
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE encuesta_valores (
              id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              respuesta_id BIGINT UNSIGNED NOT NULL,
              pregunta_id  BIGINT UNSIGNED NOT NULL,
              valor        VARCHAR(2000) NOT NULL,
              PRIMARY KEY (id),
              KEY idx_encval_pregunta (pregunta_id),
              CONSTRAINT fk_encval_respuesta FOREIGN KEY (respuesta_id) REFERENCES encuesta_respuestas (id) ON DELETE CASCADE,
              CONSTRAINT fk_encval_pregunta FOREIGN KEY (pregunta_id) REFERENCES encuesta_preguntas (id) ON DELETE CASCADE
            ) ENGINE=InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE encuesta_valores');
        $this->addSql('DROP TABLE encuesta_respuestas');
        $this->addSql('DROP TABLE encuesta_preguntas');
        $this->addSql('DROP TABLE encuestas');
        $this->addSql('DROP TABLE eventos');
    }
}

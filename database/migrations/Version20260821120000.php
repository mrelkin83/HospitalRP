<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Grupo Sistema: las seis tablas sobre las que se apoya todo lo demás.
 *
 * Copiadas de database/esquema.sql sin reinterpretar: ese archivo es la
 * referencia y esta migración su implementación (Fase 0, paso 5).
 *
 * Decisiones del esquema que conviene no "corregir" después sin pensarlo:
 *  - `auditoria` no tiene FK a `usuarios`: es append-only y debe sobrevivir
 *    a la eliminación de cualquier usuario. Guarda el nombre desnormalizado.
 *  - `ip` es VARBINARY(16): cabe IPv4 e IPv6 en binario (INET6_ATON).
 *  - `usuarios.eliminado_en`: borrado lógico; el correo sigue único incluso
 *    para cuentas retiradas, y eso es deliberado.
 */
final class Version20260821120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grupo Sistema: roles, dependencias, usuarios, archivos, configuracion, auditoria';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE roles (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              nombre          VARCHAR(80)  NOT NULL,
              slug            VARCHAR(80)  NOT NULL,
              descripcion     VARCHAR(255) NULL,
              permisos        JSON         NOT NULL,
              protegido       TINYINT(1)   NOT NULL DEFAULT 0,
              creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_roles_slug (slug)
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE dependencias (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              nombre          VARCHAR(160) NOT NULL,
              sigla           VARCHAR(20)  NULL,
              responsable     VARCHAR(160) NULL,
              correo          VARCHAR(160) NULL,
              telefono        VARCHAR(40)  NULL,
              orden           SMALLINT     NOT NULL DEFAULT 0,
              activa          TINYINT(1)   NOT NULL DEFAULT 1,
              creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_dependencias_activa (activa)
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE usuarios (
              id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              nombre            VARCHAR(160) NOT NULL,
              correo            VARCHAR(160) NOT NULL,
              hash_password     VARCHAR(255) NOT NULL,
              rol_id            BIGINT UNSIGNED NOT NULL,
              dependencia_id    BIGINT UNSIGNED NULL,
              cargo             VARCHAR(120) NULL,
              activo            TINYINT(1)   NOT NULL DEFAULT 1,
              mfa_secreto       VARBINARY(255) NULL,
              mfa_habilitado    TINYINT(1)   NOT NULL DEFAULT 0,
              debe_cambiar_pass TINYINT(1)   NOT NULL DEFAULT 1,
              intentos_fallidos SMALLINT     NOT NULL DEFAULT 0,
              bloqueado_hasta   DATETIME     NULL,
              ultimo_acceso     DATETIME     NULL,
              ultima_ip         VARBINARY(16) NULL,
              eliminado_en      DATETIME     NULL,
              creado_en         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_usuarios_correo (correo),
              KEY idx_usuarios_rol (rol_id),
              KEY idx_usuarios_dependencia (dependencia_id),
              CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id) REFERENCES roles (id) ON DELETE RESTRICT,
              CONSTRAINT fk_usuarios_dependencia FOREIGN KEY (dependencia_id) REFERENCES dependencias (id) ON DELETE SET NULL
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE archivos (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              nombre_original VARCHAR(255) NOT NULL,
              ruta            VARCHAR(500) NOT NULL,
              mime            VARCHAR(120) NOT NULL,
              extension       VARCHAR(20)  NOT NULL,
              tamano          BIGINT UNSIGNED NOT NULL,
              hash_sha256     CHAR(64)     NOT NULL,
              ancho           INT UNSIGNED NULL,
              alto            INT UNSIGNED NULL,
              texto_alt       VARCHAR(255) NULL,
              publico         TINYINT(1)   NOT NULL DEFAULT 1,
              descargas       INT UNSIGNED NOT NULL DEFAULT 0,
              creado_por      BIGINT UNSIGNED NULL,
              creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_archivos_hash (hash_sha256),
              KEY idx_archivos_mime (mime),
              CONSTRAINT fk_archivos_creador FOREIGN KEY (creado_por) REFERENCES usuarios (id) ON DELETE SET NULL
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE configuracion (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              clave           VARCHAR(120) NOT NULL,
              valor           TEXT         NULL,
              tipo            ENUM('texto','texto_largo','numero','booleano','json','color','archivo','html') NOT NULL DEFAULT 'texto',
              grupo           VARCHAR(60)  NOT NULL DEFAULT 'general',
              etiqueta        VARCHAR(160) NOT NULL,
              ayuda           VARCHAR(255) NULL,
              orden           SMALLINT     NOT NULL DEFAULT 0,
              editable        TINYINT(1)   NOT NULL DEFAULT 1,
              actualizado_por BIGINT UNSIGNED NULL,
              actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_configuracion_clave (clave),
              KEY idx_configuracion_grupo (grupo)
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE auditoria (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              usuario_id      BIGINT UNSIGNED NULL,
              usuario_nombre  VARCHAR(160) NULL,
              accion          VARCHAR(60)  NOT NULL,
              entidad         VARCHAR(80)  NOT NULL,
              entidad_id      BIGINT UNSIGNED NULL,
              descripcion     VARCHAR(500) NULL,
              antes           JSON         NULL,
              despues         JSON         NULL,
              ip              VARBINARY(16) NULL,
              user_agent      VARCHAR(500) NULL,
              creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_auditoria_usuario (usuario_id),
              KEY idx_auditoria_entidad (entidad, entidad_id),
              KEY idx_auditoria_fecha (creado_en)
            ) ENGINE=InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        // Orden inverso al de creación: primero las tablas con FK salientes.
        $this->addSql('DROP TABLE auditoria');
        $this->addSql('DROP TABLE configuracion');
        $this->addSql('DROP TABLE archivos');
        $this->addSql('DROP TABLE usuarios');
        $this->addSql('DROP TABLE dependencias');
        $this->addSql('DROP TABLE roles');
    }
}

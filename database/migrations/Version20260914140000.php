<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Directorio de servidores públicos (Fase 9, bloque A5).
 *
 * Lo exigen los ítems 1.6 y 1.6.1 del ITA: directorio de servidores públicos,
 * empleados y contratistas, con su escala salarial. En el Joomla esto vivía
 * como una página de 151 KB con ~150 fichas escritas a mano (docs/14 §4).
 *
 * Es DISTINTO de `profesionales`, que es el directorio médico asistencial:
 * aquel responde «¿qué especialista me atiende?» y este «¿quién responde por
 * esta dependencia y cuánto gana?».
 *
 * Solo datos de contacto INSTITUCIONALES. Nada de dirección de residencia,
 * teléfono personal, documento de identidad ni fecha de nacimiento: la Ley
 * 1712 obliga a publicar el cargo y la remuneración, no la vida privada de
 * quien ocupa el cargo. La hoja de vida se enlaza al SIGEP, que es la fuente
 * oficial, en lugar de copiarla aquí.
 */
final class Version20260914140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Directorio de servidores públicos con escala salarial (ITA 1.6 y 1.6.1)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE servidores_publicos (
              id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              nombres                VARCHAR(120) NOT NULL,
              apellidos              VARCHAR(120) NOT NULL,
              slug                   VARCHAR(260) NOT NULL,
              cargo                  VARCHAR(200) NOT NULL,
              dependencia_id         BIGINT UNSIGNED NULL,
              oficina                VARCHAR(200) NULL,
              tipo_vinculacion       ENUM('planta','libre_nombramiento','provisional','contrato','otro') NOT NULL DEFAULT 'planta',
              formacion_academica    VARCHAR(300) NULL,
              correo_institucional   VARCHAR(160) NULL,
              telefono_institucional VARCHAR(60)  NULL,
              url_hoja_vida          VARCHAR(500) NULL,
              escala_salarial        VARCHAR(160) NULL,
              url_escala_salarial    VARCHAR(500) NULL,
              orden                  SMALLINT     NOT NULL DEFAULT 0,
              publicado              TINYINT(1)   NOT NULL DEFAULT 0,
              eliminado_en           DATETIME     NULL,
              creado_en              TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_servidores_slug (slug),
              KEY idx_servidores_publicado (publicado, orden),
              KEY idx_servidores_dependencia (dependencia_id),
              CONSTRAINT fk_servidores_dependencia FOREIGN KEY (dependencia_id) REFERENCES dependencias (id) ON DELETE SET NULL
            ) ENGINE=InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE servidores_publicos');
    }
}

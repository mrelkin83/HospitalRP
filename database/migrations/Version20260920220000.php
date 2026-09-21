<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adiós al OTP de seis dígitos: el ingreso pasa a contraseña propia.
 * Se elimina cc_otp y llega cc_ingreso_intentos, el registro de intentos
 * fallidos que sostiene el bloqueo por conexión (10 por hora).
 */
final class Version20260920220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retira la tabla de OTP y crea el registro de intentos fallidos de ingreso';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE cc_ingreso_intentos (
              id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              contratista_id BIGINT UNSIGNED NULL,
              ip             VARCHAR(45) NOT NULL,
              creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_cc_ingreso_intentos_ip (ip, creado_en),
              CONSTRAINT fk_cc_ingreso_intentos_contratista FOREIGN KEY (contratista_id)
                REFERENCES cc_contratistas (id) ON DELETE SET NULL
            ) ENGINE=InnoDB
            SQL);

        $this->addSql('DROP TABLE cc_otp');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE cc_otp (
              id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              contratista_id BIGINT UNSIGNED NOT NULL,
              canal          ENUM('WHATSAPP','SMS','CORREO') NOT NULL,
              codigo_hash    VARCHAR(255) NOT NULL,
              expira_en      DATETIME NOT NULL,
              usado_en       DATETIME NULL,
              intentos       TINYINT UNSIGNED NOT NULL DEFAULT 0,
              ip             VARCHAR(45) NOT NULL,
              creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_cc_otp_contratista (contratista_id, expira_en),
              CONSTRAINT fk_cc_otp_contratista FOREIGN KEY (contratista_id)
                REFERENCES cc_contratistas (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB
            SQL);

        $this->addSql('DROP TABLE cc_ingreso_intentos');
    }
}

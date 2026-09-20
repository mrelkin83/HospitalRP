<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cuentas de cobro, fase 2: tokens de descarga de PDF.
 *
 * Los PDF de una cuenta contienen datos personales y bancarios, así que
 * nunca se sirven por ruta directa (§9 del documento del módulo): cada
 * descarga externa (el enlace de WhatsApp, el correo) viaja con un token
 * aleatorio de UN SOLO USO y con vencimiento. El token se guarda hasheado:
 * quien lea la tabla no puede reconstruir el enlace.
 */
final class Version20260920150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cuentas de cobro: tokens de descarga de un solo uso';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE cc_descarga_tokens (
              id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              cuenta_id   BIGINT UNSIGNED NOT NULL,
              documento   ENUM('PAQUETE','CUENTA_COBRO','INFORME_ACTIVIDADES','INFORME_SUPERVISION') NOT NULL,
              token_hash  CHAR(64) NOT NULL,
              expira_en   DATETIME NOT NULL,
              usado_en    DATETIME NULL,
              creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_cc_descarga_token (token_hash),
              KEY idx_cc_descarga_cuenta (cuenta_id),
              CONSTRAINT fk_cc_descarga_cuenta FOREIGN KEY (cuenta_id) REFERENCES cc_cuentas (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cc_descarga_tokens');
    }
}

<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cuentas de cobro, fase 2: quién actúa puede ser un contratista.
 *
 * El historial y las firmas nacieron en la fase 1 con FK obligatoria a
 * `usuarios`, pero el contratista entra al portal con cédula + código, sin
 * usuario del panel. Crearle un usuario fantasma solo para cumplir la FK
 * sería mentirle a la tabla de usuarios; en su lugar, el actor es una de
 * dos columnas: `usuario_id` (panel) o `contratista_id` (portal).
 */
final class Version20260920152000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cuentas de cobro: historial y firmas aceptan contratistas como actores';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cc_cuenta_historial MODIFY usuario_id BIGINT UNSIGNED NULL');
        $this->addSql('ALTER TABLE cc_cuenta_historial ADD contratista_id BIGINT UNSIGNED NULL AFTER usuario_id');
        $this->addSql('ALTER TABLE cc_cuenta_historial ADD CONSTRAINT fk_cc_historial_contratista FOREIGN KEY (contratista_id) REFERENCES cc_contratistas (id) ON DELETE RESTRICT');

        $this->addSql('ALTER TABLE cc_cuenta_firmas MODIFY usuario_id BIGINT UNSIGNED NULL');
        $this->addSql('ALTER TABLE cc_cuenta_firmas ADD contratista_id BIGINT UNSIGNED NULL AFTER usuario_id');
        $this->addSql('ALTER TABLE cc_cuenta_firmas ADD CONSTRAINT fk_cc_firmas_contratista FOREIGN KEY (contratista_id) REFERENCES cc_contratistas (id) ON DELETE RESTRICT');

        // Los anexos también los sube el contratista (la planilla PILA es
        // suya); mismo par de columnas que arriba.
        $this->addSql('ALTER TABLE cc_cuenta_adjuntos DROP FOREIGN KEY fk_cc_adjuntos_usuario');
        $this->addSql('ALTER TABLE cc_cuenta_adjuntos MODIFY subido_por BIGINT UNSIGNED NULL');
        $this->addSql('ALTER TABLE cc_cuenta_adjuntos ADD CONSTRAINT fk_cc_adjuntos_usuario FOREIGN KEY (subido_por) REFERENCES usuarios (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE cc_cuenta_adjuntos ADD subido_por_contratista BIGINT UNSIGNED NULL AFTER subido_por');
        $this->addSql('ALTER TABLE cc_cuenta_adjuntos ADD CONSTRAINT fk_cc_adjuntos_contratista FOREIGN KEY (subido_por_contratista) REFERENCES cc_contratistas (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cc_cuenta_adjuntos DROP FOREIGN KEY fk_cc_adjuntos_contratista');
        $this->addSql('ALTER TABLE cc_cuenta_adjuntos DROP COLUMN subido_por_contratista');
        $this->addSql('ALTER TABLE cc_cuenta_adjuntos DROP FOREIGN KEY fk_cc_adjuntos_usuario');
        $this->addSql('ALTER TABLE cc_cuenta_adjuntos MODIFY subido_por BIGINT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE cc_cuenta_adjuntos ADD CONSTRAINT fk_cc_adjuntos_usuario FOREIGN KEY (subido_por) REFERENCES usuarios (id) ON DELETE RESTRICT');

        $this->addSql('ALTER TABLE cc_cuenta_firmas DROP FOREIGN KEY fk_cc_firmas_contratista');
        $this->addSql('ALTER TABLE cc_cuenta_firmas DROP COLUMN contratista_id');
        $this->addSql('ALTER TABLE cc_cuenta_firmas MODIFY usuario_id BIGINT UNSIGNED NOT NULL');

        $this->addSql('ALTER TABLE cc_cuenta_historial DROP FOREIGN KEY fk_cc_historial_contratista');
        $this->addSql('ALTER TABLE cc_cuenta_historial DROP COLUMN contratista_id');
        $this->addSql('ALTER TABLE cc_cuenta_historial MODIFY usuario_id BIGINT UNSIGNED NOT NULL');
    }
}

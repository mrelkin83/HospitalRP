<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cuentas de cobro: contraseña del contratista, revisores y actividades
 * manuales (ajustes pedidos tras la puesta en marcha).
 *
 * - El OTP de seis dígitos dificultaba el ingreso: cada contratista tendrá
 *   su contraseña (la tabla cc_otp se queda como histórico, sin uso).
 * - Entre el contratista y el supervisor entra un equipo de REVISORES que
 *   firma la revisión: tabla propia, estado REVISADA en el flujo y su rol
 *   en firmas e historial.
 * - Cuando el contrato no existe en físico o el escaneo es ilegible, el
 *   contratista reporta sus actividades A MANO: la actividad puede vivir
 *   sin obligación contractual, con su propio título.
 */
final class Version20260920210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cuentas de cobro: contraseña del contratista, revisores y actividades manuales';
    }

    public function up(Schema $schema): void
    {
        // 1. Contraseña del contratista (Argon2id, como todo el sitio).
        $this->addSql('ALTER TABLE cc_contratistas ADD password_hash VARCHAR(255) NULL AFTER pin_hash');

        // 2. Los revisores: como los supervisores, con usuario del panel y
        //    la firma con la que firman su revisión.
        $this->addSql(<<<'SQL'
            CREATE TABLE cc_revisores (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              usuario_id      BIGINT UNSIGNED NOT NULL,
              nombre_completo VARCHAR(240) NOT NULL,
              cargo           VARCHAR(160) NOT NULL,
              firma_archivo   VARCHAR(255) NULL,
              activo          TINYINT(1) NOT NULL DEFAULT 1,
              PRIMARY KEY (id),
              UNIQUE KEY uq_cc_revisores_usuario (usuario_id),
              CONSTRAINT fk_cc_revisores_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB
            SQL);

        // 3. El estado intermedio del nuevo flujo y su rastro en la cuenta.
        $this->addSql("ALTER TABLE cc_cuentas MODIFY estado ENUM('BORRADOR','ENVIADA','REVISADA','DEVUELTA','APROBADA','ENTREGADA_FINANCIERA','ANULADA') NOT NULL DEFAULT 'BORRADOR'");
        $this->addSql('ALTER TABLE cc_cuentas ADD revisada_en DATETIME NULL AFTER enviada_en');
        $this->addSql('ALTER TABLE cc_cuentas ADD revisada_por BIGINT UNSIGNED NULL AFTER revisada_en');
        $this->addSql('ALTER TABLE cc_cuentas ADD CONSTRAINT fk_cc_cuentas_revisor FOREIGN KEY (revisada_por) REFERENCES cc_revisores (id) ON DELETE RESTRICT');

        // 4. El revisor firma su revisión y aparece en el historial.
        $this->addSql("ALTER TABLE cc_cuenta_firmas MODIFY documento ENUM('CUENTA_COBRO','INFORME_ACTIVIDADES','INFORME_SUPERVISION','REVISION') NOT NULL");
        $this->addSql("ALTER TABLE cc_cuenta_firmas MODIFY firmante_rol ENUM('CONTRATISTA','SUPERVISOR','REVISOR') NOT NULL");
        $this->addSql('ALTER TABLE cc_cuenta_firmas ADD revisor_id BIGINT UNSIGNED NULL AFTER contratista_id');
        $this->addSql('ALTER TABLE cc_cuenta_firmas ADD CONSTRAINT fk_cc_firmas_revisor FOREIGN KEY (revisor_id) REFERENCES cc_revisores (id) ON DELETE RESTRICT');
        $this->addSql("ALTER TABLE cc_cuenta_historial MODIFY rol ENUM('CONTRATISTA','SUPERVISOR','REVISOR','ADMIN','SISTEMA') NOT NULL");

        // 5. Actividades sin obligación contractual (reporte manual).
        $this->addSql('ALTER TABLE cc_cuenta_actividades MODIFY obligacion_id BIGINT UNSIGNED NULL');
        $this->addSql('ALTER TABLE cc_cuenta_actividades ADD titulo_manual VARCHAR(300) NULL AFTER obligacion_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cc_cuenta_actividades DROP COLUMN titulo_manual');
        $this->addSql('DELETE FROM cc_cuenta_actividades WHERE obligacion_id IS NULL');
        $this->addSql('ALTER TABLE cc_cuenta_actividades MODIFY obligacion_id BIGINT UNSIGNED NOT NULL');

        $this->addSql("DELETE FROM cc_cuenta_historial WHERE rol = 'REVISOR'");
        $this->addSql("ALTER TABLE cc_cuenta_historial MODIFY rol ENUM('CONTRATISTA','SUPERVISOR','ADMIN','SISTEMA') NOT NULL");
        $this->addSql("DELETE FROM cc_cuenta_firmas WHERE firmante_rol = 'REVISOR'");
        $this->addSql('ALTER TABLE cc_cuenta_firmas DROP FOREIGN KEY fk_cc_firmas_revisor');
        $this->addSql('ALTER TABLE cc_cuenta_firmas DROP COLUMN revisor_id');
        $this->addSql("ALTER TABLE cc_cuenta_firmas MODIFY documento ENUM('CUENTA_COBRO','INFORME_ACTIVIDADES','INFORME_SUPERVISION') NOT NULL");
        $this->addSql("ALTER TABLE cc_cuenta_firmas MODIFY firmante_rol ENUM('CONTRATISTA','SUPERVISOR') NOT NULL");

        $this->addSql('ALTER TABLE cc_cuentas DROP FOREIGN KEY fk_cc_cuentas_revisor');
        $this->addSql('ALTER TABLE cc_cuentas DROP COLUMN revisada_por');
        $this->addSql('ALTER TABLE cc_cuentas DROP COLUMN revisada_en');
        $this->addSql("UPDATE cc_cuentas SET estado = 'ENVIADA' WHERE estado = 'REVISADA'");
        $this->addSql("ALTER TABLE cc_cuentas MODIFY estado ENUM('BORRADOR','ENVIADA','DEVUELTA','APROBADA','ENTREGADA_FINANCIERA','ANULADA') NOT NULL DEFAULT 'BORRADOR'");

        $this->addSql('DROP TABLE cc_revisores');
        $this->addSql('ALTER TABLE cc_contratistas DROP COLUMN password_hash');
    }
}

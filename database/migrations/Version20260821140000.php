<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Refuerza el append-only de `auditoria` a nivel de base de datos.
 *
 * La regla de oro #3 (CLAUDE.md §2) no puede depender de que el código se
 * porte bien: cualquier UPDATE o DELETE sobre la tabla —desde la aplicación,
 * un script suelto o un cliente MySQL— falla con SQLSTATE 45000. TRUNCATE y
 * DROP siguen siendo posibles para el DBA; lo que se bloquea es la
 * manipulación fila a fila, que es como se falsifica una auditoría.
 */
final class Version20260821140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Triggers que hacen cumplir el append-only de auditoria';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TRIGGER trg_auditoria_sin_update
            BEFORE UPDATE ON auditoria
            FOR EACH ROW
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'auditoria es append-only: UPDATE prohibido'
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TRIGGER trg_auditoria_sin_delete
            BEFORE DELETE ON auditoria
            FOR EACH ROW
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'auditoria es append-only: DELETE prohibido'
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER trg_auditoria_sin_update');
        $this->addSql('DROP TRIGGER trg_auditoria_sin_delete');
    }
}

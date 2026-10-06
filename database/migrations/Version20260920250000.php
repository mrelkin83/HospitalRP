<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La copia de la cédula pasa a ser DOS caras.
 *
 * Se pedía «foto de su cédula» con una sola casilla y la ayuda decía «por
 * ambos lados si es posible»: el resultado previsible es media cédula, y
 * Contratación devolviendo la cuenta por un documento incompleto.
 *
 * El reverso va en su propia columna y no en un adjunto suelto porque es
 * parte de la ficha del contratista, igual que el anverso.
 */
final class Version20260920250000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cuentas de cobro: reverso de la cédula del contratista';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE cc_contratistas
              ADD COLUMN cedula_reverso_archivo VARCHAR(255) NULL AFTER cedula_archivo
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cc_contratistas DROP COLUMN cedula_reverso_archivo');
    }
}

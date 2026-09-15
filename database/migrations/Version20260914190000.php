<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Marca de «destacado en portada» para los indicadores.
 *
 * El héroe de la home mostraba dos barras con cifras escritas a mano en la
 * plantilla —«98.4 % Satisfacción del Paciente», «95 % Disponibilidad
 * Médica»—, sin fuente ni periodo. En un sitio .gov.co eso no es un adorno:
 * es una cifra institucional que nadie respalda.
 *
 * Con esta columna la portada muestra indicadores REALES: el hospital marca
 * cuáles quiere destacar y el héroe toma el último periodo publicado de cada
 * uno, con su fecha. Si no hay ninguno marcado, las barras no se pintan.
 *
 * Es un flag y no una tabla de «destacados» porque el dato ya existe: lo que
 * faltaba era decir cuál sale en portada, no duplicarlo.
 */
final class Version20260914190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Marca de indicador destacado en la portada';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE indicadores ADD COLUMN destacado TINYINT(1) NOT NULL DEFAULT 0 AFTER publicado');
        $this->addSql('CREATE INDEX idx_indicadores_destacado ON indicadores (destacado, publicado, periodo)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_indicadores_destacado ON indicadores');
        $this->addSql('ALTER TABLE indicadores DROP COLUMN destacado');
    }
}

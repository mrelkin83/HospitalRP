<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cifras destacadas de cada servicio, del Portafolio de Servicios 2026.
 *
 * El portafolio institucional no describe los servicios solo en prosa: cada
 * lámina apoya el texto en cifras sueltas —21 consultorios, 49 especialistas,
 * 269 camas, 8 quirófanos, 5 ambulancias— que son lo primero que busca quien
 * llega a la página. Meterlas dentro de `descripcion` las convertiría en un
 * párrafo más y se perderían; dejarlas fuera obligaría a reconstruir el dato
 * leyendo el PDF.
 *
 * Va como JSON y no como tabla aparte porque es una lista corta de pares
 * cifra/texto que solo tiene sentido dentro de su servicio, nunca se consulta
 * por separado y no se relaciona con nada (CLAUDE.md §2.7).
 *
 * Forma: `[{"cifra": "269", "texto": "camas habilitadas"}, …]`.
 */
final class Version20261006160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cifras destacadas por servicio (Portafolio de Servicios 2026)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE servicios ADD COLUMN destacados JSON NULL AFTER requisitos');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE servicios DROP COLUMN destacados');
    }
}

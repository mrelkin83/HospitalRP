<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cuentas de cobro, fase 5: la extracción de minutas entra al registro de
 * consumo de IA.
 *
 * `ia_usos.funcion` es un ENUM cerrado a propósito (las funciones de IA
 * permitidas son una lista corta y auditada, CLAUDE.md §6); la extracción
 * de minutas es la sexta y vive solo en el panel, como las demás.
 */
final class Version20260920180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cuentas de cobro: la extracción de minutas como función de IA registrada';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE ia_usos MODIFY funcion
            ENUM('resumen_documento','sugerencia_etiquetas','texto_alternativo','metadatos_seo','borrador_noticia','extraccion_minuta')
            NOT NULL
            SQL);
    }

    public function down(Schema $schema): void
    {
        // Las filas de la función nueva no caben en el ENUM viejo: se
        // retiran antes de encoger la lista (es bitácora de consumo, no
        // contenido institucional).
        $this->addSql("DELETE FROM ia_usos WHERE funcion = 'extraccion_minuta'");
        $this->addSql(<<<'SQL'
            ALTER TABLE ia_usos MODIFY funcion
            ENUM('resumen_documento','sugerencia_etiquetas','texto_alternativo','metadatos_seo','borrador_noticia')
            NOT NULL
            SQL);
    }
}

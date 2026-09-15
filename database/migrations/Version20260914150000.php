<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Indicadores estadísticos publicados por la entidad.
 *
 * Tres grupos, pedidos por el hospital: cómo va la contratación, ocupación
 * hospitalaria y estadísticas vitales.
 *
 * Cada fila es la lectura de UN indicador en UN periodo, no el indicador
 * suelto. Así la serie histórica sale sola —«camas ocupadas» en enero,
 * febrero, marzo— sin necesitar una segunda tabla, y publicar el dato del mes
 * es insertar una fila desde el panel.
 *
 * El valor lo carga un funcionario; no sale del sistema hospitalario. El
 * `HisConnector` existe como puerto para conectarlo más adelante, y entonces
 * solo cambia quién escribe estas filas, no la pantalla que las lee.
 *
 * `fuente` no es decorativa: una cifra de ocupación sin decir de dónde salió
 * y a qué fecha corresponde no es información pública, es un número suelto.
 */
final class Version20260914150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Indicadores de contratación, ocupación hospitalaria y estadísticas vitales';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE indicadores (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              grupo           ENUM('contratacion','ocupacion','vitales','otro') NOT NULL DEFAULT 'otro',
              nombre          VARCHAR(200)  NOT NULL,
              slug            VARCHAR(220)  NOT NULL,
              periodo         DATE          NOT NULL,
              valor           DECIMAL(14,2) NOT NULL,
              unidad          VARCHAR(40)   NULL,
              meta            DECIMAL(14,2) NULL,
              fuente          VARCHAR(200)  NULL,
              nota            VARCHAR(500)  NULL,
              orden           SMALLINT      NOT NULL DEFAULT 0,
              publicado       TINYINT(1)    NOT NULL DEFAULT 0,
              eliminado_en    DATETIME      NULL,
              creado_por      BIGINT UNSIGNED NULL,
              creado_en       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_indicadores_slug_periodo (slug, periodo),
              KEY idx_indicadores_grupo (grupo, publicado, periodo),
              CONSTRAINT fk_indicadores_usuario FOREIGN KEY (creado_por) REFERENCES usuarios (id) ON DELETE SET NULL
            ) ENGINE=InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE indicadores');
    }
}

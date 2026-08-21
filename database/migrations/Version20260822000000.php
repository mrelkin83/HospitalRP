<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Grupo Búsqueda y redirecciones (Fase 7), copiado de database/esquema.sql.
 * `redirecciones` venía del grupo Sistema y se migra aquí, que es donde se
 * implementa (301 desde las URLs del Joomla).
 */
final class Version20260822000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Búsqueda unificada (indice_busqueda, busquedas_log) y redirecciones 301';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE indice_busqueda (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              tipo            ENUM('pagina','noticia','documento','especialidad','servicio','profesional','sede','faq','convocatoria','tramite_tipo','dataset') NOT NULL,
              entidad_id      BIGINT UNSIGNED NOT NULL,
              titulo          VARCHAR(500) NOT NULL,
              resumen         VARCHAR(1000) NULL,
              contenido       MEDIUMTEXT   NULL,
              url             VARCHAR(500) NOT NULL,
              peso            SMALLINT     NOT NULL DEFAULT 0,
              actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_indice (tipo, entidad_id),
              FULLTEXT KEY ft_indice (titulo, resumen, contenido)
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE busquedas_log (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              termino         VARCHAR(255) NOT NULL,
              resultados      INT UNSIGNED NOT NULL DEFAULT 0,
              creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_busquedas_termino (termino),
              KEY idx_busquedas_sin_resultado (resultados, creado_en)
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE redirecciones (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              origen          VARCHAR(500) NOT NULL,
              destino         VARCHAR(500) NOT NULL,
              codigo          SMALLINT     NOT NULL DEFAULT 301,
              activa          TINYINT(1)   NOT NULL DEFAULT 1,
              hits            INT UNSIGNED NOT NULL DEFAULT 0,
              ultimo_hit      DATETIME     NULL,
              creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_redirecciones_origen (origen(255))
            ) ENGINE=InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE redirecciones');
        $this->addSql('DROP TABLE busquedas_log');
        $this->addSql('DROP TABLE indice_busqueda');
    }
}

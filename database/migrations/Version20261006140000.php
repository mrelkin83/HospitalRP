<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tres huecos del ITA en el motor de trámites.
 *
 * 1. **Costos asociados (ITA 7.1).** Es el único «No» de 2025 que se puede
 *    cerrar con código. La sección Trámites sacó 75/100 porque al terminar el
 *    contrato con el proveedor que publicaba los costos nadie los volvió a
 *    publicar. Con el costo como campo del tipo de trámite deja de depender de
 *    un tercero.
 *
 * 2. **Dirección de correspondencia completa (ITA 13.3.g).** El criterio
 *    enumera «Dirección, Barrio/Vereda/Corregimiento, Municipio/Distrito,
 *    País». La tabla tenía dirección, municipio y departamento; faltaban el
 *    barrio y el país.
 *
 * 3. **Mecanismo anti-SPAM (ITA 13.3.c).** No existía ninguno en el formulario
 *    público: el bloqueo por intentos solo cubría el login del panel.
 *    `tramite_intentos` sostiene un límite por IP. Se eligió eso y un campo
 *    trampa antes que un captcha de terceros: un captcha externo rompería la
 *    CSP del sitio, mandaría la IP del ciudadano a otra empresa y pondría una
 *    barrera de accesibilidad justo en el canal de peticiones.
 */
final class Version20261006140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Costos de trámites, dirección completa y anti-SPAM en PQRSD (ITA 7.1, 13.3.c y 13.3.g)';
    }

    public function up(Schema $schema): void
    {
        // 1. Costos asociados al trámite.
        $this->addSql(<<<'SQL'
            ALTER TABLE tipos_tramite
              ADD COLUMN gratuito      TINYINT(1)   NOT NULL DEFAULT 1 AFTER fundamento_legal,
              ADD COLUMN costo_detalle VARCHAR(500) NULL AFTER gratuito
            SQL);

        // 2. Dirección de correspondencia completa.
        $this->addSql(<<<'SQL'
            ALTER TABLE tramites
              ADD COLUMN barrio VARCHAR(160) NULL AFTER direccion,
              ADD COLUMN pais   VARCHAR(120) NULL AFTER departamento
            SQL);

        // 3. Límite por IP para el formulario público. Sin llave foránea a
        //    `tramites`: se registra también el intento que NO llegó a crear
        //    un trámite, que es justo el que hay que contar.
        $this->addSql(<<<'SQL'
            CREATE TABLE tramite_intentos (
              id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              ip        VARCHAR(45) NOT NULL,
              creado_en DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_tramite_intentos_ip (ip, creado_en)
            ) ENGINE=InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tramite_intentos');
        $this->addSql('ALTER TABLE tramites DROP COLUMN pais, DROP COLUMN barrio');
        $this->addSql('ALTER TABLE tipos_tramite DROP COLUMN costo_detalle, DROP COLUMN gratuito');
    }
}

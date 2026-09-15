<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ventanas emergentes administrables desde el panel.
 *
 * No se reutiliza `avisos`: un aviso es una banda dentro de la página, que el
 * visitante lee cuando llega a ella; un popup se le planta encima y le
 * interrumpe. Comparten campos, pero no reglas: el popup necesita saber CADA
 * CUÁNTO vuelve a aparecer y DÓNDE, y un aviso no. Mezclarlos obligaría a que
 * cada aviso arrastrara columnas que no le sirven, y a que encender una banda
 * pudiera convertirse en un modal por descuido.
 *
 * `frecuencia` es la columna que evita que el sitio se vuelva insoportable:
 * `sesion` (por omisión) lo enseña una vez por visita, `diaria` una vez al día
 * y `siempre` en cada página. El recuerdo vive en el navegador del visitante,
 * no en el servidor: no hace falta cookie ni identificarlo para saber que ya
 * lo cerró.
 *
 * `activo` nace en 0 a propósito. Un popup se escribe, se revisa y luego se
 * enciende; nacer encendido sería publicar a medio escribir.
 */
final class Version20260915200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ventanas emergentes (popups) administrables desde el panel';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE popups (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              titulo          VARCHAR(200) NOT NULL,
              mensaje         VARCHAR(1000) NOT NULL,
              imagen_id       BIGINT UNSIGNED NULL,
              url             VARCHAR(500) NULL,
              texto_boton     VARCHAR(80)  NULL,
              ubicacion       ENUM('todas','portada') NOT NULL DEFAULT 'portada',
              frecuencia      ENUM('siempre','sesion','diaria') NOT NULL DEFAULT 'sesion',
              desde           DATETIME     NULL,
              hasta           DATETIME     NULL,
              activo          TINYINT(1)   NOT NULL DEFAULT 0,
              prioridad       SMALLINT     NOT NULL DEFAULT 0,
              creado_por      BIGINT UNSIGNED NULL,
              creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_popups_vigencia (activo, desde, hasta),
              CONSTRAINT fk_popups_imagen FOREIGN KEY (imagen_id) REFERENCES archivos (id) ON DELETE SET NULL,
              CONSTRAINT fk_popups_creador FOREIGN KEY (creado_por) REFERENCES usuarios (id) ON DELETE SET NULL
            ) ENGINE=InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE popups');
    }
}

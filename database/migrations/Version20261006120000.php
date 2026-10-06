<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Instrumentos de gestión de la información (ITA 9.1) y enlace a datos.gov.co
 * (ITA 9.1.g, 9.1.m y 9.2).
 *
 * Sección 9 «Datos Abiertos» del formulario MinTIC Res. 1519: 14 % del peso del
 * índice y la mayor pérdida de 2026. La clave está en el enunciado de cada
 * criterio: el ITA NO pide un PDF adjunto, pide una TABLA con columnas
 * nombradas. Por eso esto no cabe en `transparencia_items` —que solo enlaza un
 * documento o una URL— y necesita esquema propio.
 *
 * Tres instrumentos, una tabla. Comparten diez de sus columnas (nombre, idioma,
 * medio de conservación, responsables…) y se distinguen por `tipo`:
 *
 * - `registro_activos`    → Registro de Activos de Información, 7 columnas
 * - `indice_clasificada`  → Índice de Información Clasificada y Reservada, 13
 * - `esquema_publicacion` → Esquema de Publicación de Información, 9
 *
 * Partirlas en tres tablas casi idénticas sería la sobre-ingeniería que prohíbe
 * CLAUDE.md §2.7. Las columnas que no aplican a un tipo quedan NULL, y la vista
 * pública solo pinta las de su instrumento.
 *
 * La Ley 1712 art. 6 lit. d obliga a publicarlos en formato abierto, así que
 * cada instrumento se descarga en CSV además de verse en HTML.
 */
final class Version20261006120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Instrumentos de gestión de la información y enlace a datos.gov.co (ITA 9.1 y 9.2)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE instrumentos_informacion (
              id                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              tipo                     ENUM('registro_activos','indice_clasificada','esquema_publicacion') NOT NULL,

              -- Comunes. `categoria` agrupa (RAI a, IICR a); `nombre` titula la
              -- información concreta (IICR b, EPI a).
              categoria                VARCHAR(300) NULL,
              nombre                   VARCHAR(300) NOT NULL,
              descripcion              TEXT         NULL,
              idioma                   VARCHAR(60)  NOT NULL DEFAULT 'Español',
              medio_conservacion       VARCHAR(160) NOT NULL,
              formato                  VARCHAR(120) NULL,
              fecha_generacion         DATE         NULL,
              responsable_produccion   VARCHAR(200) NULL,
              responsable_informacion  VARCHAR(200) NULL,
              dependencia_id           BIGINT UNSIGNED NULL,

              -- Solo Registro de Activos de Información.
              disponibilidad           VARCHAR(160) NULL,

              -- Solo Esquema de Publicación.
              frecuencia_actualizacion VARCHAR(120) NULL,
              lugar_consulta           VARCHAR(300) NULL,

              -- Solo Índice de Información Clasificada y Reservada.
              objetivo_excepcion       TEXT         NULL,
              fundamento_legal         VARCHAR(300) NULL,
              fundamento_juridico      TEXT         NULL,
              alcance_excepcion        ENUM('total','parcial') NULL,
              plazo_reserva            VARCHAR(160) NULL,

              -- ITA 9.1.g y 9.1.m: enlace al conjunto en el portal nacional.
              url_datos_abiertos       VARCHAR(500) NULL,

              orden                    SMALLINT     NOT NULL DEFAULT 0,
              publicado                TINYINT(1)   NOT NULL DEFAULT 0,
              creado_en                TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_instrumentos_tipo (tipo, publicado, orden),
              KEY idx_instrumentos_dependencia (dependencia_id),
              CONSTRAINT fk_instrumentos_dependencia FOREIGN KEY (dependencia_id) REFERENCES dependencias (id) ON DELETE SET NULL
            ) ENGINE=InnoDB
            SQL);

        // ITA 9.2: cada conjunto enlaza su vista en el portal nacional. El
        // enlace institucional al perfil completo va en configuración
        // (`datos_abiertos.portal_url`), administrable sin tocar código.
        $this->addSql(
            'ALTER TABLE datasets ADD COLUMN url_datos_gov VARCHAR(500) NULL AFTER licencia',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE datasets DROP COLUMN url_datos_gov');
        $this->addSql('DROP TABLE instrumentos_informacion');
    }
}

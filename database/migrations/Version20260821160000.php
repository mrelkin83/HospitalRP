<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Grupo Contenido (Fase 1, paso 1), copiado de database/esquema.sql sin
 * reinterpretar. Incluye menus/menu_items porque los menús editables son
 * parte de esta fase.
 *
 * Decisiones del esquema a respetar:
 *  - Todo contenido publicable lleva `estado_aprobacion` + ventana
 *    `publicar_en`/`despublicar_en` y borrado lógico (`eliminado_en`).
 *  - `contenido_versiones` es genérico: (entidad, entidad_id, version) único.
 *  - Un aviso `nivel='critico'` activo convierte la home en modo emergencia.
 */
final class Version20260821160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grupo Contenido: categorias, paginas, noticias, campanas, avisos, faq, contenido_versiones, menus, menu_items';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE categorias (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              tipo            ENUM('noticia','documento','campana','faq') NOT NULL,
              nombre          VARCHAR(120) NOT NULL,
              slug            VARCHAR(140) NOT NULL,
              descripcion     VARCHAR(255) NULL,
              color           CHAR(7)      NULL,
              orden           SMALLINT     NOT NULL DEFAULT 0,
              activa          TINYINT(1)   NOT NULL DEFAULT 1,
              creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_categorias_tipo_slug (tipo, slug)
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE paginas (
              id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              slug              VARCHAR(200) NOT NULL,
              ruta              VARCHAR(500) NOT NULL,
              titulo            VARCHAR(255) NOT NULL,
              resumen           VARCHAR(500) NULL,
              contenido         MEDIUMTEXT   NULL,
              padre_id          BIGINT UNSIGNED NULL,
              plantilla         VARCHAR(60)  NOT NULL DEFAULT 'default',
              imagen_id         BIGINT UNSIGNED NULL,
              orden             SMALLINT     NOT NULL DEFAULT 0,
              meta_titulo       VARCHAR(200) NULL,
              meta_descripcion  VARCHAR(320) NULL,
              noindex           TINYINT(1)   NOT NULL DEFAULT 0,
              estado_aprobacion ENUM('borrador','en_revision','aprobado','publicado') NOT NULL DEFAULT 'borrador',
              aprobado_por      BIGINT UNSIGNED NULL,
              aprobado_en       DATETIME     NULL,
              publicar_en       DATETIME     NULL,
              despublicar_en    DATETIME     NULL,
              protegida         TINYINT(1)   NOT NULL DEFAULT 0,
              vistas            INT UNSIGNED NOT NULL DEFAULT 0,
              eliminado_en      DATETIME     NULL,
              creado_por        BIGINT UNSIGNED NULL,
              actualizado_por   BIGINT UNSIGNED NULL,
              creado_en         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_paginas_ruta (ruta(255)),
              KEY idx_paginas_padre (padre_id),
              KEY idx_paginas_estado (estado_aprobacion, eliminado_en),
              CONSTRAINT fk_paginas_padre FOREIGN KEY (padre_id) REFERENCES paginas (id) ON DELETE SET NULL,
              CONSTRAINT fk_paginas_imagen FOREIGN KEY (imagen_id) REFERENCES archivos (id) ON DELETE SET NULL
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE noticias (
              id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              slug              VARCHAR(200) NOT NULL,
              titulo            VARCHAR(255) NOT NULL,
              resumen           VARCHAR(500) NOT NULL,
              contenido         MEDIUMTEXT   NOT NULL,
              imagen_id         BIGINT UNSIGNED NULL,
              categoria_id      BIGINT UNSIGNED NULL,
              destacada         TINYINT(1)   NOT NULL DEFAULT 0,
              meta_titulo       VARCHAR(200) NULL,
              meta_descripcion  VARCHAR(320) NULL,
              estado_aprobacion ENUM('borrador','en_revision','aprobado','publicado') NOT NULL DEFAULT 'borrador',
              aprobado_por      BIGINT UNSIGNED NULL,
              aprobado_en       DATETIME     NULL,
              publicar_en       DATETIME     NULL,
              despublicar_en    DATETIME     NULL,
              vistas            INT UNSIGNED NOT NULL DEFAULT 0,
              eliminado_en      DATETIME     NULL,
              creado_por        BIGINT UNSIGNED NULL,
              actualizado_por   BIGINT UNSIGNED NULL,
              creado_en         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_noticias_slug (slug),
              KEY idx_noticias_publicacion (estado_aprobacion, publicar_en, eliminado_en),
              KEY idx_noticias_categoria (categoria_id),
              CONSTRAINT fk_noticias_imagen FOREIGN KEY (imagen_id) REFERENCES archivos (id) ON DELETE SET NULL,
              CONSTRAINT fk_noticias_categoria FOREIGN KEY (categoria_id) REFERENCES categorias (id) ON DELETE SET NULL
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE campanas (
              id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              slug              VARCHAR(200) NOT NULL,
              titulo            VARCHAR(255) NOT NULL,
              resumen           VARCHAR(500) NULL,
              contenido         MEDIUMTEXT   NULL,
              imagen_id         BIGINT UNSIGNED NULL,
              poblacion_objetivo VARCHAR(255) NULL,
              inicio            DATE         NULL,
              fin               DATE         NULL,
              estado_aprobacion ENUM('borrador','en_revision','aprobado','publicado') NOT NULL DEFAULT 'borrador',
              eliminado_en      DATETIME     NULL,
              creado_por        BIGINT UNSIGNED NULL,
              actualizado_por   BIGINT UNSIGNED NULL,
              creado_en         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_campanas_slug (slug),
              CONSTRAINT fk_campanas_imagen FOREIGN KEY (imagen_id) REFERENCES archivos (id) ON DELETE SET NULL
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE avisos (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              tipo            ENUM('informativo','urgencia','campana','emergencia') NOT NULL DEFAULT 'informativo',
              nivel           ENUM('info','alerta','critico') NOT NULL DEFAULT 'info',
              titulo          VARCHAR(200) NOT NULL,
              mensaje         VARCHAR(1000) NOT NULL,
              url             VARCHAR(500) NULL,
              texto_boton     VARCHAR(80)  NULL,
              desde           DATETIME     NULL,
              hasta           DATETIME     NULL,
              activo          TINYINT(1)   NOT NULL DEFAULT 0,
              prioridad       SMALLINT     NOT NULL DEFAULT 0,
              creado_por      BIGINT UNSIGNED NULL,
              creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_avisos_activo (activo, desde, hasta)
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE faq (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              pregunta        VARCHAR(500) NOT NULL,
              respuesta       TEXT         NOT NULL,
              categoria_id    BIGINT UNSIGNED NULL,
              orden           SMALLINT     NOT NULL DEFAULT 0,
              publicada       TINYINT(1)   NOT NULL DEFAULT 1,
              vistas          INT UNSIGNED NOT NULL DEFAULT 0,
              creado_por      BIGINT UNSIGNED NULL,
              creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_faq_categoria (categoria_id),
              CONSTRAINT fk_faq_categoria FOREIGN KEY (categoria_id) REFERENCES categorias (id) ON DELETE SET NULL
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE contenido_versiones (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              entidad         VARCHAR(60)  NOT NULL,
              entidad_id      BIGINT UNSIGNED NOT NULL,
              version         INT UNSIGNED NOT NULL,
              snapshot        JSON         NOT NULL,
              nota            VARCHAR(255) NULL,
              creado_por      BIGINT UNSIGNED NULL,
              creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_versiones (entidad, entidad_id, version),
              KEY idx_versiones_entidad (entidad, entidad_id)
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE menus (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              nombre          VARCHAR(80)  NOT NULL,
              slug            VARCHAR(80)  NOT NULL,
              ubicacion       ENUM('principal','superior','pie_1','pie_2','pie_3','lateral') NOT NULL,
              protegido       TINYINT(1)   NOT NULL DEFAULT 0,
              creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_menus_slug (slug)
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE menu_items (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              menu_id         BIGINT UNSIGNED NOT NULL,
              padre_id        BIGINT UNSIGNED NULL,
              etiqueta        VARCHAR(120) NOT NULL,
              url             VARCHAR(500) NOT NULL,
              icono           VARCHAR(60)  NULL,
              destino         ENUM('mismo','nueva') NOT NULL DEFAULT 'mismo',
              orden           SMALLINT     NOT NULL DEFAULT 0,
              visible         TINYINT(1)   NOT NULL DEFAULT 1,
              protegido       TINYINT(1)   NOT NULL DEFAULT 0,
              PRIMARY KEY (id),
              KEY idx_menu_items_menu (menu_id, orden),
              KEY idx_menu_items_padre (padre_id),
              CONSTRAINT fk_menu_items_menu FOREIGN KEY (menu_id) REFERENCES menus (id) ON DELETE CASCADE,
              CONSTRAINT fk_menu_items_padre FOREIGN KEY (padre_id) REFERENCES menu_items (id) ON DELETE CASCADE
            ) ENGINE=InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE menu_items');
        $this->addSql('DROP TABLE menus');
        $this->addSql('DROP TABLE contenido_versiones');
        $this->addSql('DROP TABLE faq');
        $this->addSql('DROP TABLE avisos');
        $this->addSql('DROP TABLE campanas');
        $this->addSql('DROP TABLE noticias');
        $this->addSql('DROP TABLE paginas');
        $this->addSql('DROP TABLE categorias');
    }
}

<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * El héroe de la portada deja de ser uno solo y deja de ser solo texto.
 *
 * Hasta ahora el héroe eran 19 claves sueltas en Ajustes: una píldora, un
 * título partido en dos, un párrafo y dos botones. Editarlo era rellenar
 * campos desperdigados en un formulario largo, y por más que se editaran
 * seguía siendo UN bloque fijo, sin imagen. Eso no es un héroe.
 *
 * Esta tabla lo convierte en lo que el hospital espera: varias diapositivas,
 * cada una con su imagen a sangre, su texto y sus botones, con orden,
 * interruptor y vigencia. Se administra en una sola pantalla.
 *
 * Tres decisiones que conviene entender antes de tocar nada:
 *
 * - `mostrar_tarjeta` es la tarjeta ilustrada «Rosario Digital». Es una sola
 *   y vive en la PRIMERA diapositiva: en las demás la ocupa la fotografía.
 *   Se guarda por fila, no como «la primera», porque «la primera» cambia en
 *   cuanto alguien reordena, y entonces la tarjeta saltaría de sitio sola.
 * - `imagen_id` apunta a `archivos`, la misma mediateca que ya usan campañas
 *   y noticias. `ON DELETE SET NULL`: borrar el archivo deja la diapositiva
 *   en su fondo degradado, no un hueco roto ni una fila huérfana.
 * - `inicio`/`fin` son la vigencia. Un héroe de jornada de vacunación se
 *   apaga solo el día que toca, sin que nadie tenga que acordarse.
 *
 * Los ocho textos que hoy están en Ajustes se MUEVEN aquí, a la primera
 * diapositiva: se copian antes de borrarlos, para que lo que el hospital haya
 * escrito no se pierda en el despliegue. Las claves que NO son de diapositiva
 * —la tarjeta, los avisos flotantes, el texto guía del buscador— se quedan en
 * Ajustes, porque son únicas y no se repiten por héroe.
 */
final class Version20260915210000 extends AbstractMigration
{
    /** Las claves de Ajustes que pasan a ser columnas de una diapositiva. */
    private const array CLAVES_MOVIDAS = [
        'hero.pildora',
        'hero.titulo1',
        'hero.titulo_degradado',
        'hero.eslogan',
        'hero.boton1',
        'hero.enlace1',
        'hero.boton2',
        'hero.enlace2',
    ];

    public function getDescription(): string
    {
        return 'Héroes de la portada: varias diapositivas con imagen, administrables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE heroes (
              id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              pildora           VARCHAR(120) NULL,
              titulo            VARCHAR(160) NOT NULL,
              titulo_degradado  VARCHAR(160) NULL,
              eslogan           VARCHAR(500) NULL,
              boton1_texto      VARCHAR(60)  NULL,
              boton1_url        VARCHAR(255) NULL,
              boton2_texto      VARCHAR(60)  NULL,
              boton2_url        VARCHAR(255) NULL,
              imagen_id         BIGINT UNSIGNED NULL,
              mostrar_tarjeta   TINYINT(1)   NOT NULL DEFAULT 0,
              orden             SMALLINT     NOT NULL DEFAULT 0,
              visible           TINYINT(1)   NOT NULL DEFAULT 1,
              inicio            DATE         NULL,
              fin               DATE         NULL,
              creado_por        BIGINT UNSIGNED NULL,
              actualizado_por   BIGINT UNSIGNED NULL,
              creado_en         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_heroes_portada (visible, orden),
              CONSTRAINT fk_heroes_imagen FOREIGN KEY (imagen_id) REFERENCES archivos (id) ON DELETE SET NULL,
              CONSTRAINT fk_heroes_creador FOREIGN KEY (creado_por) REFERENCES usuarios (id) ON DELETE SET NULL
            ) ENGINE=InnoDB
            SQL);

        // La primera diapositiva es lo que el sitio está mostrando ahora
        // mismo. Se arma leyendo Ajustes, no con literales: si alguien del
        // hospital ya cambió el título, el que manda es el suyo.
        //
        // `NULLIF(valor, '')` porque un campo vaciado en el panel se guarda
        // como cadena vacía, y ahí lo que corresponde es el valor de fábrica,
        // igual que hacía la plantilla.
        $this->addSql(<<<'SQL'
            INSERT INTO heroes
                (pildora, titulo, titulo_degradado, eslogan,
                 boton1_texto, boton1_url, boton2_texto, boton2_url,
                 mostrar_tarjeta, orden, visible)
            SELECT
                (SELECT NULLIF(valor, '') FROM configuracion WHERE clave = 'hero.pildora'),
                COALESCE((SELECT NULLIF(valor, '') FROM configuracion WHERE clave = 'hero.titulo1'), 'Tu salud,'),
                COALESCE((SELECT NULLIF(valor, '') FROM configuracion WHERE clave = 'hero.titulo_degradado'), 'nuestra prioridad'),
                COALESCE((SELECT NULLIF(valor, '') FROM configuracion WHERE clave = 'hero.eslogan'),
                         'En la E.S.E. Hospital Rosario Pumarejo de López brindamos atención médica de calidad, humana y segura, con la mejor tecnología y un equipo comprometido contigo.'),
                COALESCE((SELECT NULLIF(valor, '') FROM configuracion WHERE clave = 'hero.boton1'), 'Solicitar cita'),
                COALESCE((SELECT NULLIF(valor, '') FROM configuracion WHERE clave = 'hero.enlace1'), '/citas'),
                COALESCE((SELECT NULLIF(valor, '') FROM configuracion WHERE clave = 'hero.boton2'), 'Conoce nuestros servicios'),
                COALESCE((SELECT NULLIF(valor, '') FROM configuracion WHERE clave = 'hero.enlace2'), '/servicios'),
                1, 0, 1
            SQL);

        $claves = "'" . implode("','", self::CLAVES_MOVIDAS) . "'";
        $this->addSql('DELETE FROM configuracion WHERE clave IN (' . $claves . ')');
    }

    /**
     * Al revertir, los textos vuelven a Ajustes desde la primera diapositiva
     * —otra vez, para no perder lo escrito— y la tabla desaparece.
     */
    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO configuracion (clave, valor, tipo, grupo, etiqueta, ayuda, orden, editable)
            SELECT 'hero.pildora', COALESCE(pildora, ''), 'texto', 'hero', 'Píldora sobre el título', NULL, 40, 1
              FROM heroes ORDER BY orden, id LIMIT 1
            SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO configuracion (clave, valor, tipo, grupo, etiqueta, ayuda, orden, editable)
            SELECT 'hero.titulo1', titulo, 'texto', 'hero', 'Título (primera parte)', NULL, 41, 1
              FROM heroes ORDER BY orden, id LIMIT 1
            SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO configuracion (clave, valor, tipo, grupo, etiqueta, ayuda, orden, editable)
            SELECT 'hero.titulo_degradado', COALESCE(titulo_degradado, ''), 'texto', 'hero', 'Título (parte en degradado)', NULL, 42, 1
              FROM heroes ORDER BY orden, id LIMIT 1
            SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO configuracion (clave, valor, tipo, grupo, etiqueta, ayuda, orden, editable)
            SELECT 'hero.eslogan', COALESCE(eslogan, ''), 'texto', 'hero', 'Párrafo de presentación', NULL, 43, 1
              FROM heroes ORDER BY orden, id LIMIT 1
            SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO configuracion (clave, valor, tipo, grupo, etiqueta, ayuda, orden, editable)
            SELECT 'hero.boton1', COALESCE(boton1_texto, ''), 'texto', 'hero', 'Botón principal (texto)', NULL, 44, 1
              FROM heroes ORDER BY orden, id LIMIT 1
            SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO configuracion (clave, valor, tipo, grupo, etiqueta, ayuda, orden, editable)
            SELECT 'hero.enlace1', COALESCE(boton1_url, ''), 'texto', 'hero', 'Botón principal (dirección)', NULL, 45, 1
              FROM heroes ORDER BY orden, id LIMIT 1
            SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO configuracion (clave, valor, tipo, grupo, etiqueta, ayuda, orden, editable)
            SELECT 'hero.boton2', COALESCE(boton2_texto, ''), 'texto', 'hero', 'Botón secundario (texto)', NULL, 46, 1
              FROM heroes ORDER BY orden, id LIMIT 1
            SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO configuracion (clave, valor, tipo, grupo, etiqueta, ayuda, orden, editable)
            SELECT 'hero.enlace2', COALESCE(boton2_url, ''), 'texto', 'hero', 'Botón secundario (dirección)', NULL, 47, 1
              FROM heroes ORDER BY orden, id LIMIT 1
            SQL);

        $this->addSql('DROP TABLE heroes');
    }
}

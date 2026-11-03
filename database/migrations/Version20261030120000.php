<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Subsanación de la Auditoría Visual V9 (26/10/2026) sobre datos vivos.
 *
 * Las semillas no pisan lo que el hospital editó en el panel, así que los
 * ajustes que la auditoría exige sobre VALORES ya sembrados van aquí:
 *
 *  - Teléfonos con el prefijo +57 (Anexo 2, 2.2.1(4), Res. 1519): el PBX y
 *    Citas se mostraban sin él; la forma «(+57)» se normaliza a «+57».
 *  - Marca País CO–Colombia en el pie (Anexo 2, 2.2.1(1)): claves nuevas
 *    con el recurso oficial autoalojado.
 *  - Denominación completa «Transparencia y Acceso a la Información
 *    Pública» y unificación de «Atención y Servicios a la Ciudadanía»
 *    (Anexo 2, 2.4; Anexo 1, CC7).
 *  - Centro de Relevo en la barra superior (estándar gráfico 2022).
 *  - Logotipo con transparencia en lugar del JPEG con placa blanca y
 *    leyenda corta del buscador (observaciones de diseño, secciones 12 y 15).
 *
 * Cada UPDATE condiciona por el valor anterior: lo que el hospital haya
 * personalizado con otro texto no se toca.
 */
final class Version20261030120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Auditoría Visual V9: +57, Marca País, denominaciones de menú, relevo, logo y buscador';
    }

    public function up(Schema $schema): void
    {
        // «(+57) xxx» → «+57 xxx»: el prefijo ya estaba, solo cambia la forma.
        $this->addSql(
            "UPDATE configuracion
                SET valor = CONCAT('+57 ', LTRIM(SUBSTRING(valor, 6)))
              WHERE clave IN ('contacto.pbx', 'contacto.emergencias', 'contacto.call_center', 'contacto.linea_etica_telefono')
                AND valor LIKE '(+57)%'",
        );

        // Sin prefijo: se limpian los paréntesis de indicativo y se antepone
        // +57. «(605) 5712339» → «+57 605 5712339»; «317 5153642» → «+57 317 5153642».
        $this->addSql(
            "UPDATE configuracion
                SET valor = CONCAT('+57 ', TRIM(REPLACE(REPLACE(valor, '(', ''), ')', '')))
              WHERE clave IN ('contacto.pbx', 'contacto.emergencias', 'contacto.call_center', 'contacto.linea_etica_telefono')
                AND valor NOT LIKE '+57%'
                AND valor NOT LIKE '(+57%'
                AND valor <> ''",
        );

        // Marca País CO–Colombia (recurso oficial, autoalojado en /img).
        $this->addSql(
            "INSERT IGNORE INTO configuracion (clave, valor, tipo, grupo, etiqueta, ayuda, orden, editable) VALUES
             ('marca_pais.logo', '/img/marca_co.svg', 'texto', 'pie', 'Logotipo Marca País CO', 'Marca oficial del Estado: no cambiar salvo indicación de MinTIC', 90, 1),
             ('marca_pais.url', 'https://www.colombia.co/', 'texto', 'pie', 'Enlace de la Marca País', 'Sitio oficial de la Marca País Colombia', 91, 1)",
        );

        // Logotipo: solo si sigue siendo el JPEG sembrado, cuya placa blanca
        // señaló la auditoría; un logo subido por el hospital no se pisa.
        $this->addSql(
            "UPDATE configuracion SET valor = '/img/logo_icono.png'
              WHERE clave = 'marca.logo' AND valor = '/img/logo_pequeno.jpeg'",
        );

        // Leyenda del buscador: la anterior salía truncada en móvil.
        $this->addSql(
            "UPDATE configuracion SET valor = 'Buscar en el portal'
              WHERE clave = 'hero.buscador_leyenda' AND valor = 'Buscar trámites, médicos, noticias...'",
        );

        // Denominaciones de los menús institucionales (todas las ubicaciones:
        // barra superior, menú principal y tarjeta de acceso de la portada).
        $this->addSql(
            "UPDATE menu_items SET etiqueta = 'Transparencia y Acceso a la Información Pública'
              WHERE url = '/transparencia' AND etiqueta = 'Transparencia'",
        );
        $this->addSql(
            "UPDATE menu_items SET etiqueta = 'Atención y Servicios a la Ciudadanía'
              WHERE url = '/atencion-ciudadania' AND etiqueta = 'Atención a la Ciudadanía'",
        );

        // Centro de Relevo en la barra superior, si no existe ya.
        $this->addSql(
            "INSERT INTO menu_items (menu_id, padre_id, etiqueta, url, orden, protegido, destino)
             SELECT m.id, NULL, 'Centro de Relevo', 'https://www.centroderelevo.gov.co/', 4, 0, 'nueva'
               FROM menus m
              WHERE m.slug = 'superior'
                AND NOT EXISTS (
                    SELECT 1 FROM menu_items mi
                     WHERE mi.menu_id = m.id AND mi.url = 'https://www.centroderelevo.gov.co/'
                )",
        );
    }

    public function down(Schema $schema): void
    {
        // Los teléfonos no se devuelven a su forma sin prefijo: el +57 es
        // correcto en cualquier caso y el valor anterior exacto ya no se
        // conoce. Solo se revierte lo reversible sin pérdida.
        $this->addSql(
            "UPDATE menu_items SET etiqueta = 'Transparencia'
              WHERE url = '/transparencia' AND etiqueta = 'Transparencia y Acceso a la Información Pública'",
        );
        $this->addSql(
            "UPDATE menu_items SET etiqueta = 'Atención a la Ciudadanía'
              WHERE url = '/atencion-ciudadania' AND etiqueta = 'Atención y Servicios a la Ciudadanía'",
        );
        $this->addSql("DELETE FROM menu_items WHERE url = 'https://www.centroderelevo.gov.co/'");
        $this->addSql("DELETE FROM configuracion WHERE clave IN ('marca_pais.logo', 'marca_pais.url')");
        $this->addSql(
            "UPDATE configuracion SET valor = '/img/logo_pequeno.jpeg'
              WHERE clave = 'marca.logo' AND valor = '/img/logo_icono.png'",
        );
        $this->addSql(
            "UPDATE configuracion SET valor = 'Buscar trámites, médicos, noticias...'
              WHERE clave = 'hero.buscador_leyenda' AND valor = 'Buscar en el portal'",
        );
    }
}

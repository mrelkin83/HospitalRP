<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Los accesos rápidos de la portada pasan a ser un menú administrable.
 *
 * Las ocho tarjetas —Citas, Urgencias, Especialidades, Directorio médico,
 * Trámites, Consulta tu trámite, PQRSDF y Transparencia— estaban escritas a
 * mano en `resources/views/publico/inicio.php`: cambiar una palabra o añadir
 * una novena exigía un despliegue. Son la navegación real del sitio, la que
 * usa el ciudadano que entra con prisa.
 *
 * Se reutiliza la maquinaria de menús en vez de inventar una tabla: etiqueta,
 * dirección, orden y visibilidad ya existen, con su pantalla y su auditoría.
 * Solo faltaban dos cosas para que una tarjeta quepa en un `menu_item`:
 *
 * - `descripcion`: la frase bajo el título. Un enlace de menú no la tiene.
 * - la ubicación `accesos`, para que el menú viva junto a los demás.
 *
 * `icono` ya existía sin usarse: ahora guarda una clave del catálogo
 * (`CatalogoIconos`), no un SVG. Pegar SVG desde el panel sería dejar entrar
 * marcado ajeno en la página.
 */
final class Version20260915180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Accesos rápidos de la portada como menú administrable';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE menus
              MODIFY ubicacion ENUM('principal','superior','pie_1','pie_2','pie_3','lateral','accesos') NOT NULL
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE menu_items
              ADD COLUMN descripcion VARCHAR(255) NULL AFTER etiqueta
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM menu_items WHERE menu_id IN (SELECT id FROM menus WHERE ubicacion = 'accesos')");
        $this->addSql("DELETE FROM menus WHERE ubicacion = 'accesos'");
        $this->addSql('ALTER TABLE menu_items DROP COLUMN descripcion');
        $this->addSql(<<<'SQL'
            ALTER TABLE menus
              MODIFY ubicacion ENUM('principal','superior','pie_1','pie_2','pie_3','lateral') NOT NULL
            SQL);
    }
}

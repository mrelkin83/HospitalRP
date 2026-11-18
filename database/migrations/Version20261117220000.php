<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Se deja de decir lo mismo dos veces en el encabezado.
 *
 * La gerencia señaló (2026-11-17) que la barra superior repetía el menú y que
 * el encabezado se veía amontonado. Al medirlo, la repetición era literal:
 *
 * - La barra superior llevaba «Transparencia y Acceso a la Información
 *   Pública», «Atención y Servicios a la Ciudadanía» y «Participa», que son
 *   TRES de las nueve raíces del menú principal, con la misma URL y el mismo
 *   rótulo. Eran dos filas de barra para repetir lo que estaba justo debajo.
 * - El menú principal llevaba «Contacto» → /contacto, cuando «Atención y
 *   Servicios a la Ciudadanía» ya tiene «Contáctenos» entre sus hijos.
 *
 * Esta migración los OCULTA, no los borra: siguen marcados `protegido`, así
 * que la fila queda en la base y el panel puede volver a mostrarlos con un
 * clic si una auditoría lo pide.
 *
 * Sobre el ITA: el criterio pide que Transparencia, Atención a la Ciudadanía
 * y Participa sean alcanzables DESDE EL ENCABEZADO, no que aparezcan dos
 * veces en él. Siguen estando, con su rótulo literal, como raíces del menú
 * principal —que es encabezado—. No se pierde cumplimiento; se deja de
 * duplicar.
 *
 * Lo que NO se toca, y conviene saber por qué:
 *
 * - Los rótulos largos. «Atención y Servicios a la Ciudadanía» y
 *   «Transparencia y Acceso a la Información Pública» miden 352 y 457 px y
 *   son los que más amontonan, pero la Res. 1519 los exige literales y la
 *   Auditoría Visual V9 ya obligó a escribirlos así. Acortarlos sería
 *   cambiar aspecto por incumplimiento.
 * - «Inicio». Es corto y es el ancla que un visitante poco habituado busca,
 *   aunque el logotipo lleve al mismo sitio.
 * - «Buscar», «Resultados de imagenología» y «Centro de Relevo» se quedan en
 *   la barra superior: no están en el menú principal y los dos últimos son
 *   servicios externos —el Centro de Relevo es el canal de accesibilidad
 *   para personas sordas que exige la norma—.
 */
final class Version20261117220000 extends AbstractMigration
{
    /** URLs que la barra superior repetía del menú principal. */
    private const array DUPLICADOS_BARRA = [
        '/transparencia',
        '/atencion-ciudadania',
        '/participa',
    ];

    public function getDescription(): string
    {
        return 'Encabezado: quitar del menú lo que ya se decía dos veces';
    }

    public function up(Schema $schema): void
    {
        $enLista = "'" . implode("', '", self::DUPLICADOS_BARRA) . "'";

        $this->addSql(
            "UPDATE menu_items i
                JOIN menus m ON m.id = i.menu_id
                SET i.visible = 0
              WHERE m.ubicacion = 'superior'
                AND i.url IN ({$enLista})"
        );

        // «Contacto» del menú principal: su destino ya vive como «Contáctenos»
        // dentro de Atención y Servicios a la Ciudadanía.
        $this->addSql(
            "UPDATE menu_items i
                JOIN menus m ON m.id = i.menu_id
                SET i.visible = 0
              WHERE m.ubicacion = 'principal'
                AND i.padre_id IS NULL
                AND i.url = '/contacto'"
        );
    }

    public function down(Schema $schema): void
    {
        $enLista = "'" . implode("', '", self::DUPLICADOS_BARRA) . "'";

        $this->addSql(
            "UPDATE menu_items i
                JOIN menus m ON m.id = i.menu_id
                SET i.visible = 1
              WHERE m.ubicacion = 'superior'
                AND i.url IN ({$enLista})"
        );

        $this->addSql(
            "UPDATE menu_items i
                JOIN menus m ON m.id = i.menu_id
                SET i.visible = 1
              WHERE m.ubicacion = 'principal'
                AND i.padre_id IS NULL
                AND i.url = '/contacto'"
        );
    }
}

<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La fotografía del héroe pasa a ser enlazable.
 *
 * La gerencia pidió (2026-11-17) que al hacer clic sobre la imagen del héroe
 * se llegue a la página que corresponde. Hasta ahora solo eran enlazables los
 * dos botones: `boton1_url` y `boton2_url`. La fotografía ocupa casi toda la
 * pantalla de entrada y no llevaba a ninguna parte, de modo que el gesto más
 * evidente —pulsar la imagen— no hacía nada.
 *
 * `enlace_imagen` es opcional a propósito. Donde esté vacío, la diapositiva se
 * pinta como hasta hoy: una imagen sin enlace. Solo cuando el hospital escriba
 * una dirección la imagen se envuelve en un <a>. Así ninguna diapositiva ya
 * publicada cambia de comportamiento con el despliegue.
 *
 * Importante para quien pinte la plantilla: cuando hay enlace, los botones
 * siguen estando POR ENCIMA de la imagen. Un <a> envolviendo toda la
 * diapositiva se tragaría el clic de los botones, y entonces «Solicitar cita»
 * llevaría al destino de la foto. El enlace de la imagen va detrás del texto,
 * nunca alrededor.
 */
final class Version20261117190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Héroes: la fotografía puede llevar a un enlace propio';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE heroes
                ADD COLUMN enlace_imagen VARCHAR(255) NULL
                COMMENT "Destino al pulsar la fotografía; vacío = imagen sin enlace"
                AFTER imagen_id'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE heroes DROP COLUMN enlace_imagen');
    }
}

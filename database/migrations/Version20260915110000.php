<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A quién le habla cada campaña: a la comunidad o al personal de la entidad.
 *
 * La portada tiene dos pestañas —«Salud y Prevención» y «Portal de
 * Empleados»— y hasta ahora el contenido de las dos estaba escrito a mano en
 * la plantilla. Para que Comunicaciones las administre desde el panel hace
 * falta que la campaña diga a qué público va dirigida.
 *
 * No se reutiliza `poblacion_objetivo` a propósito: ese campo es texto libre
 * («Niños menores de 5 años») y sirve como etiqueta visible; filtrar una
 * pestaña comparando cadenas escritas por una persona es un filtro que se
 * rompe el día que alguien escriba «Personal asistencial» en vez de
 * «Personal».
 *
 * `comunidad` es el valor por omisión porque es lo que era toda campaña antes
 * de esta columna: las filas existentes no cambian de significado.
 */
final class Version20260915110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Audiencia de las campañas: comunidad o personal de la entidad';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE campanas
              ADD COLUMN audiencia ENUM('comunidad','personal') NOT NULL DEFAULT 'comunidad' AFTER poblacion_objetivo,
              ADD KEY idx_campanas_audiencia (audiencia, estado_aprobacion, inicio)
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE campanas DROP KEY idx_campanas_audiencia, DROP COLUMN audiencia');
    }
}

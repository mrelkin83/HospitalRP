<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cuentas de cobro, fase 6 (bloque A): la extracción de minutas pasa de
 * procesarse dentro de la petición web a vivir en una cola.
 *
 * Tres columnas nuevas en `cc_extracciones`:
 *
 * - `intentos`: tope de reintentos. Sin él, un PDF corrupto se reprocesa
 *   para siempre y se lleva por delante la cuota del proveedor de IA.
 * - `reclamado_en`: cuándo un trabajador tomó la fila. Si un proceso muere
 *   a mitad de camino, la fila queda en PROCESANDO y nadie la vuelve a
 *   mirar; con esta marca se libera por antigüedad.
 * - `asignado_a`: qué usuario del panel tiene tomada la revisión de ESTA
 *   extracción. Con cuatro personas sobre la misma bandeja, sin esto dos
 *   abren la misma minuta y una de las dos trabaja gratis. Se llama
 *   `asignado_a` y no `revisor_id` a propósito: un «revisor» en este
 *   módulo es quien firma la revisión de una cuenta de cobro
 *   (`cc_revisores`), que es otra cosa.
 *
 * El índice (estado, id) es el del reclamo de la cola: se consulta cada
 * dos minutos por trabajador sobre una tabla que llega a 1.500 filas en
 * la carga inicial.
 */
final class Version20260920200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cuentas de cobro: cola de extracción de minutas (intentos, reclamo y asignación)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE cc_extracciones
              ADD COLUMN intentos     TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER estado,
              ADD COLUMN reclamado_en DATETIME NULL AFTER intentos,
              ADD COLUMN asignado_a   BIGINT UNSIGNED NULL AFTER creado_por,
              ADD CONSTRAINT fk_cc_extracciones_asignado
                  FOREIGN KEY (asignado_a) REFERENCES usuarios (id) ON DELETE SET NULL,
              ADD INDEX idx_cc_extracciones_cola (estado, id)
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cc_extracciones DROP FOREIGN KEY fk_cc_extracciones_asignado');
        $this->addSql(<<<'SQL'
            ALTER TABLE cc_extracciones
              DROP INDEX idx_cc_extracciones_cola,
              DROP COLUMN asignado_a,
              DROP COLUMN reclamado_en,
              DROP COLUMN intentos
            SQL);
    }
}

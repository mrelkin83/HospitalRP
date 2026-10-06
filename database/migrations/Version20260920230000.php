<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cuentas de cobro, fase 6 (bloque B): cada actividad del informe se
 * respalda con un PRODUCTO y su evidencia.
 *
 * Hasta ahora el supervisor certificaba el cumplimiento de cada obligación
 * contra un texto libre: el contratista contaba qué hizo y no había nada
 * que mirar. Los formatos del SGC piden un entregable por actividad —acta,
 * informe, pantallazo, oficio…— y aquí se le da un sitio.
 *
 * El catálogo va en TABLA y no en ENUM porque la lista la mantiene el
 * hospital: agregar «ACTA DE ENTREGA» debe ser un registro, no una
 * migración. `NO APLICA` existe para la obligación que no genera
 * entregable y es la ÚNICA que exime de evidencia.
 */
final class Version20260920230000 extends AbstractMigration
{
    /** El listado institucional, en el orden en que lo entregó el hospital. */
    private const array PRODUCTOS = [
        ['acta-auditoria', 'ACTA DE AUDITORÍA'],
        ['acta-reunion-presencial', 'ACTA DE REUNIÓN PRESENCIAL'],
        ['acta-reunion-virtual', 'ACTA DE REUNIÓN VIRTUAL'],
        ['archivo-digital', 'ARCHIVO DIGITAL'],
        ['base-de-datos', 'BASE DE DATOS'],
        ['copia-correo', 'COPIA DE CORREO ELECTRÓNICO'],
        ['elaboracion-documento', 'ELABORACIÓN DE DOCUMENTO'],
        ['formato-digital', 'FORMATO DIGITAL'],
        ['fotografias', 'FOTOGRAFÍA(S)'],
        ['informe', 'INFORME'],
        ['link-acceso', 'LINK DE ACCESO'],
        ['listado-asistencia', 'LISTADO DE ASISTENCIA'],
        ['modelo-oficio', 'MODELO DE OFICIO'],
        ['oficio-proyectado', 'OFICIO PROYECTADO'],
        ['oficio-revisado', 'OFICIO REVISADO'],
        ['pantallazo', 'PANTALLAZO'],
        ['visita-campo', 'VISITA DE CAMPO'],
        ['no-aplica', 'NO APLICA'],
    ];

    public function getDescription(): string
    {
        return 'Cuentas de cobro: producto asociado y evidencia por actividad';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE cc_productos_actividad (
              id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              clave  VARCHAR(40) NOT NULL,
              nombre VARCHAR(120) NOT NULL,
              orden  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
              activo TINYINT(1) NOT NULL DEFAULT 1,
              PRIMARY KEY (id),
              UNIQUE KEY uq_cc_productos_clave (clave)
            ) ENGINE=InnoDB
            SQL);

        $orden = 0;

        foreach (self::PRODUCTOS as [$clave, $nombre]) {
            $this->addSql(
                'INSERT INTO cc_productos_actividad (clave, nombre, orden) VALUES (?, ?, ?)',
                [$clave, $nombre, ++$orden * 10],
            );
        }

        // La actividad apunta a su producto. NULL porque las actividades ya
        // radicadas no lo tienen: no se puede exigir hacia atrás un dato
        // que no se pidió. La obligatoriedad la impone la radicación, no
        // el esquema.
        $this->addSql(<<<'SQL'
            ALTER TABLE cc_cuenta_actividades
              ADD COLUMN producto_id BIGINT UNSIGNED NULL AFTER descripcion_ejecucion,
              ADD CONSTRAINT fk_cc_actividades_producto
                  FOREIGN KEY (producto_id) REFERENCES cc_productos_actividad (id) ON DELETE RESTRICT
            SQL);

        // La evidencia cuelga de la ACTIVIDAD, no de la cuenta: «el
        // pantallazo que prueba la obligación 7», no «un anexo más del
        // paquete». Un enlace es evidencia válida para LINK DE ACCESO, y
        // por eso `archivo` y `enlace` son ambos opcionales con un CHECK
        // que obliga a traer exactamente uno.
        $this->addSql(<<<'SQL'
            CREATE TABLE cc_actividad_evidencias (
              id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              actividad_id   BIGINT UNSIGNED NOT NULL,
              tipo           ENUM('ARCHIVO','ENLACE') NOT NULL DEFAULT 'ARCHIVO',
              archivo        VARCHAR(255) NULL,
              enlace         VARCHAR(500) NULL,
              nombre_original VARCHAR(255) NULL,
              mime           VARCHAR(80) NULL,
              tamano         INT UNSIGNED NULL,
              subido_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_cc_evidencias_actividad (actividad_id),
              CONSTRAINT fk_cc_evidencias_actividad
                  FOREIGN KEY (actividad_id) REFERENCES cc_cuenta_actividades (id) ON DELETE CASCADE,
              CONSTRAINT chk_cc_evidencias_origen CHECK (
                (tipo = 'ARCHIVO' AND archivo IS NOT NULL AND enlace IS NULL)
                OR (tipo = 'ENLACE' AND enlace IS NOT NULL AND archivo IS NULL)
              )
            ) ENGINE=InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cc_actividad_evidencias');
        $this->addSql('ALTER TABLE cc_cuenta_actividades DROP FOREIGN KEY fk_cc_actividades_producto');
        $this->addSql('ALTER TABLE cc_cuenta_actividades DROP COLUMN producto_id');
        $this->addSql('DROP TABLE cc_productos_actividad');
    }
}

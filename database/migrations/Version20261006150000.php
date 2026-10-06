<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Los tres huecos de esquema que quedaban del ITA 2026.
 *
 * **1. Directorio de servidores públicos (ITA 3.5).** Faltaban el lugar de
 * nacimiento (3.5.b), la experiencia laboral (3.5.d) y los datos del contrato
 * de prestación de servicios (3.5.j).
 *
 * `Version20260914140000` había excluido a propósito el lugar de nacimiento,
 * razonando que la Ley 1712 obliga a publicar el cargo y la remuneración, no la
 * vida privada. El criterio del ITA sí lo exige y el hospital lo respondió «Sí»
 * en 2025, así que por decisión del 2026-10-06 se adopta el criterio tal cual.
 * Se conserva lo demás de aquella decisión: NO entran fecha de nacimiento,
 * documento de identidad, dirección de residencia ni teléfono personal. El ITA
 * no los pide, y juntos reconstruirían la identidad de la persona.
 *
 * **2. Entes y autoridades que vigilan (ITA 3.13).** Siete literales —nombre,
 * dirección, teléfono, correo, enlace, tipo de control y mecanismos internos de
 * supervisión— que no caben en un PDF adjunto a un ítem de Transparencia.
 *
 * **3. Informe de acceso a la información (ITA 6.10).** El módulo de trámites
 * ya tenía las solicitudes y sus tiempos, pero no había forma de marcar una
 * solicitud con acceso negado (6.10.d) ni trasladada a otra entidad (6.10.b),
 * que son dos de las cifras que el informe debe reportar.
 */
final class Version20261006150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Directorio de servidores públicos completo, entes de vigilancia e insumos del informe de acceso (ITA 3.5, 3.13 y 6.10)';
    }

    public function up(Schema $schema): void
    {
        // 1. Directorio de servidores públicos y contratistas.
        $this->addSql(<<<'SQL'
            ALTER TABLE servidores_publicos
              ADD COLUMN pais_nacimiento         VARCHAR(80)   NULL AFTER cargo,
              ADD COLUMN departamento_nacimiento VARCHAR(120)  NULL AFTER pais_nacimiento,
              ADD COLUMN ciudad_nacimiento       VARCHAR(120)  NULL AFTER departamento_nacimiento,
              ADD COLUMN experiencia_laboral     TEXT          NULL AFTER formacion_academica,
              ADD COLUMN contrato_objeto         TEXT          NULL AFTER url_escala_salarial,
              ADD COLUMN contrato_valor          DECIMAL(14,2) NULL AFTER contrato_objeto,
              ADD COLUMN contrato_desde          DATE          NULL AFTER contrato_valor,
              ADD COLUMN contrato_hasta          DATE          NULL AFTER contrato_desde
            SQL);

        // 2. Entes y autoridades que vigilan a la entidad.
        $this->addSql(<<<'SQL'
            CREATE TABLE entes_vigilancia (
              id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              nombre         VARCHAR(250) NOT NULL,
              tipo_control   ENUM('fiscal','social','politico','regulatorio','disciplinario','administrativo','otro') NOT NULL,
              direccion      VARCHAR(300) NULL,
              telefono       VARCHAR(120) NULL,
              correo         VARCHAR(160) NULL,
              url            VARCHAR(500) NULL,
              -- ITA 3.13.g: mecanismos internos de supervisión, notificación y
              -- vigilancia. Va por ente porque cada uno tiene el suyo.
              mecanismos     TEXT         NULL,
              orden          SMALLINT     NOT NULL DEFAULT 0,
              publicado      TINYINT(1)   NOT NULL DEFAULT 1,
              creado_en      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_entes_publicado (publicado, orden)
            ) ENGINE=InnoDB
            SQL);

        // 3. Insumos del informe trimestral de acceso a la información.
        $this->addSql(<<<'SQL'
            ALTER TABLE tramites
              ADD COLUMN acceso_negado    TINYINT(1)   NOT NULL DEFAULT 0 AFTER medio_respuesta,
              ADD COLUMN causal_negacion  VARCHAR(300) NULL AFTER acceso_negado,
              ADD COLUMN trasladado_a     VARCHAR(200) NULL AFTER causal_negacion
            SQL);

        // El informe agrupa por trimestre sobre miles de filas; sin este
        // índice la consulta recorre la tabla entera cada vez que alguien
        // abre la página.
        $this->addSql('CREATE INDEX idx_tramites_informe ON tramites (creado_en, acceso_negado)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_tramites_informe ON tramites');
        $this->addSql('ALTER TABLE tramites DROP COLUMN trasladado_a, DROP COLUMN causal_negacion, DROP COLUMN acceso_negado');
        $this->addSql('DROP TABLE entes_vigilancia');
        $this->addSql(<<<'SQL'
            ALTER TABLE servidores_publicos
              DROP COLUMN contrato_hasta,
              DROP COLUMN contrato_desde,
              DROP COLUMN contrato_valor,
              DROP COLUMN contrato_objeto,
              DROP COLUMN experiencia_laboral,
              DROP COLUMN ciudad_nacimiento,
              DROP COLUMN departamento_nacimiento,
              DROP COLUMN pais_nacimiento
            SQL);
    }
}

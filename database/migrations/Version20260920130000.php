<?php

declare(strict_types=1);

namespace Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Módulo de cuentas de cobro: todo su modelo de datos (fase 1).
 *
 * Cada mes, cada contratista de prestación de servicios legaliza su cuenta
 * transcribiendo a mano los mismos datos del contrato en tres documentos.
 * Estas tablas guardan el contrato UNA vez para que el sistema genere los
 * tres PDF sin volver a copiar nada.
 *
 * Adaptaciones sobre el modelo de la sección 6 del documento del módulo
 * (docs/CLAUDE-modulo-cuentas-de-cobro.md), como manda su sección 0:
 *
 * - Los id y las FK son BIGINT UNSIGNED, no INT: `usuarios.id` y todo lo
 *   demás de la plataforma es BIGINT, y una FK no puede cambiar de tipo.
 * - Sin COLLATE explícito: las tablas heredan el de la base (0900_ai_ci en
 *   esta plataforma). Fijar unicode_ci aquí crearía mezclas de colación en
 *   cada JOIN con usuarios.
 * - Los archivos del módulo (firmas, minutas, planillas) son rutas VARCHAR
 *   fuera del document root, NO filas de `archivos`: la mediateca de la
 *   plataforma es pública y esto son datos personales servidos con permiso.
 *
 * Todas las FK con ON DELETE RESTRICT: aquí nada se borra; contratos y
 * cuentas se anulan, contratistas y supervisores se desactivan.
 */
final class Version20260920130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cuentas de cobro: contratistas, supervisores, contratos, cuentas y parámetros';
    }

    public function up(Schema $schema): void
    {
        // Personas naturales con contrato de prestación de servicios. Una
        // por cédula; sus contratos van aparte y llegan con los años.
        $this->addSql(<<<'SQL'
            CREATE TABLE cc_contratistas (
              id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              usuario_id            BIGINT UNSIGNED NULL,
              tipo_documento        ENUM('CC','CE','PPT','NIT') NOT NULL DEFAULT 'CC',
              numero_documento      VARCHAR(20) NOT NULL,
              lugar_expedicion      VARCHAR(80) NULL,
              nombres               VARCHAR(120) NOT NULL,
              apellidos             VARCHAR(120) NOT NULL,
              nombre_completo       VARCHAR(241) GENERATED ALWAYS AS (CONCAT(nombres, ' ', apellidos)) STORED,
              direccion             VARCHAR(200) NULL,
              telefono              VARCHAR(30) NULL,
              celular               VARCHAR(30) NULL,
              correo                VARCHAR(150) NULL,
              banco                 VARCHAR(100) NULL,
              tipo_cuenta           ENUM('AHORROS','CORRIENTE') NULL,
              numero_cuenta         VARCHAR(40) NULL,
              clase_riesgo_arl      TINYINT UNSIGNED NULL,
              firma_archivo         VARCHAR(255) NULL,
              firma_cargada_en      DATETIME NULL,
              rut_archivo           VARCHAR(255) NULL,
              cedula_archivo        VARCHAR(255) NULL,
              certificacion_bancaria_archivo VARCHAR(255) NULL,
              datos_completos       TINYINT(1) NOT NULL DEFAULT 0,
              acepto_tratamiento_datos_en DATETIME NULL,
              acepto_tratamiento_datos_ip VARCHAR(45) NULL,
              pin_hash              VARCHAR(255) NULL,
              activo                TINYINT(1) NOT NULL DEFAULT 1,
              creado_en             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_cc_contratistas_documento (numero_documento),
              KEY idx_cc_contratistas_usuario (usuario_id),
              CONSTRAINT fk_cc_contratistas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB
            SQL);

        // Empleados del hospital que supervisan contratos, con su usuario
        // del panel. El nombre y el cargo van aquí TAL CUAL deben salir en
        // el informe de supervisión.
        $this->addSql(<<<'SQL'
            CREATE TABLE cc_supervisores (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              usuario_id      BIGINT UNSIGNED NOT NULL,
              nombre_completo VARCHAR(240) NOT NULL,
              cargo           VARCHAR(160) NOT NULL,
              dependencia     VARCHAR(160) NULL,
              firma_archivo   VARCHAR(255) NULL,
              activo          TINYINT(1) NOT NULL DEFAULT 1,
              PRIMARY KEY (id),
              UNIQUE KEY uq_cc_supervisores_usuario (usuario_id),
              CONSTRAINT fk_cc_supervisores_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB
            SQL);

        // El contrato, cargado UNA vez desde la minuta. `valor_total_letras`
        // lo genera el sistema pero queda editable: la minuta manda aunque
        // venga mal escrita, y esa diferencia es una alerta, no un bloqueo.
        $this->addSql(<<<'SQL'
            CREATE TABLE cc_contratos (
              id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              numero                VARCHAR(20) NOT NULL,
              contratista_id        BIGINT UNSIGNED NOT NULL,
              supervisor_id         BIGINT UNSIGNED NULL,
              cargo_supervisor_minuta VARCHAR(160) NULL,
              tipo_contrato         VARCHAR(120) NOT NULL DEFAULT 'PRESTACIÓN DE SERVICIOS',
              ciudad_suscripcion    VARCHAR(80) NOT NULL DEFAULT 'Valledupar',
              fecha_suscripcion     DATE NOT NULL,
              objeto                TEXT NOT NULL,
              valor_total           DECIMAL(14,2) NOT NULL,
              valor_total_letras    VARCHAR(400) NOT NULL,
              plazo_texto           VARCHAR(120) NOT NULL,
              plazo_meses           TINYINT UNSIGNED NOT NULL DEFAULT 0,
              plazo_dias            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
              modalidad_periodos    ENUM('CALENDARIO','ANIVERSARIO') NOT NULL DEFAULT 'CALENDARIO',
              fecha_acta_inicio     DATE NULL,
              fecha_terminacion     DATE NULL,
              garantias             TINYINT(1) NOT NULL DEFAULT 0,
              seccion_ubicacion     VARCHAR(160) NULL,
              forma_pago_texto      TEXT NULL,
              requisitos_pago_texto TEXT NULL,
              minuta_archivo        VARCHAR(255) NULL,
              estado                ENUM('EN_REVISION','ACTIVO','SUSPENDIDO','TERMINADO','LIQUIDADO','ANULADO') NOT NULL DEFAULT 'EN_REVISION',
              activado_en           DATETIME NULL,
              activado_por          BIGINT UNSIGNED NULL,
              creado_en             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_cc_contratos_numero (numero),
              KEY idx_cc_contratos_contratista (contratista_id),
              KEY idx_cc_contratos_supervisor (supervisor_id),
              KEY idx_cc_contratos_estado (estado),
              CONSTRAINT fk_cc_contratos_contratista FOREIGN KEY (contratista_id) REFERENCES cc_contratistas (id) ON DELETE RESTRICT,
              CONSTRAINT fk_cc_contratos_supervisor FOREIGN KEY (supervisor_id) REFERENCES cc_supervisores (id) ON DELETE RESTRICT,
              CONSTRAINT fk_cc_contratos_activador FOREIGN KEY (activado_por) REFERENCES usuarios (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB
            SQL);

        // Novedades primero que imputaciones: una adición trae su CDP/RP
        // propio y la imputación la referencia.
        $this->addSql(<<<'SQL'
            CREATE TABLE cc_contrato_novedades (
              id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              contrato_id   BIGINT UNSIGNED NOT NULL,
              tipo          ENUM('ADICION','PRORROGA','SUSPENSION','REINICIO','TERMINACION_ANTICIPADA','OTRA') NOT NULL,
              fecha         DATE NOT NULL,
              fecha_fin     DATE NULL,
              valor         DECIMAL(14,2) NULL,
              plazo_meses   TINYINT UNSIGNED NULL,
              plazo_dias    SMALLINT UNSIGNED NULL,
              descripcion   VARCHAR(400) NULL,
              documento_archivo VARCHAR(255) NULL,
              creado_por    BIGINT UNSIGNED NOT NULL,
              creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_cc_novedades_contrato (contrato_id),
              CONSTRAINT fk_cc_novedades_contrato FOREIGN KEY (contrato_id) REFERENCES cc_contratos (id) ON DELETE RESTRICT,
              CONSTRAINT fk_cc_novedades_creador FOREIGN KEY (creado_por) REFERENCES usuarios (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB
            SQL);

        // CDP y RP del contrato; los de una adición apuntan a su novedad.
        $this->addSql(<<<'SQL'
            CREATE TABLE cc_contrato_imputaciones (
              id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              contrato_id   BIGINT UNSIGNED NOT NULL,
              tipo          ENUM('CDP','RP') NOT NULL,
              numero        VARCHAR(30) NOT NULL,
              fecha         DATE NOT NULL,
              codigo_rubro  VARCHAR(40) NOT NULL,
              nombre_rubro  VARCHAR(200) NOT NULL,
              recurso       VARCHAR(120) NULL,
              valor         DECIMAL(14,2) NOT NULL,
              novedad_id    BIGINT UNSIGNED NULL,
              PRIMARY KEY (id),
              KEY idx_cc_imputaciones_contrato (contrato_id),
              CONSTRAINT fk_cc_imputaciones_contrato FOREIGN KEY (contrato_id) REFERENCES cc_contratos (id) ON DELETE RESTRICT,
              CONSTRAINT fk_cc_imputaciones_novedad FOREIGN KEY (novedad_id) REFERENCES cc_contrato_novedades (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB
            SQL);

        // Obligaciones específicas y del SGIC, en el orden de la minuta.
        $this->addSql(<<<'SQL'
            CREATE TABLE cc_contrato_obligaciones (
              id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              contrato_id   BIGINT UNSIGNED NOT NULL,
              orden         TINYINT UNSIGNED NOT NULL,
              grupo         ENUM('ESPECIFICA','SGIC') NOT NULL DEFAULT 'ESPECIFICA',
              texto         TEXT NOT NULL,
              activa        TINYINT(1) NOT NULL DEFAULT 1,
              PRIMARY KEY (id),
              UNIQUE KEY uq_cc_obligaciones (contrato_id, grupo, orden),
              CONSTRAINT fk_cc_obligaciones_contrato FOREIGN KEY (contrato_id) REFERENCES cc_contratos (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB
            SQL);

        // El cronograma de la cláusula «Forma de pago». Los periodos se
        // llenan al registrar el acta de inicio.
        $this->addSql(<<<'SQL'
            CREATE TABLE cc_contrato_pagos (
              id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              contrato_id   BIGINT UNSIGNED NOT NULL,
              numero_cuota  TINYINT UNSIGNED NOT NULL,
              descripcion   VARCHAR(120) NOT NULL,
              tipo          ENUM('COMPLETA','PARCIAL') NOT NULL DEFAULT 'COMPLETA',
              mes           CHAR(7) NULL,
              periodo_desde DATE NULL,
              periodo_hasta DATE NULL,
              dias          SMALLINT UNSIGNED NULL,
              valor         DECIMAL(14,2) NOT NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_cc_pagos_cuota (contrato_id, numero_cuota),
              CONSTRAINT fk_cc_pagos_contrato FOREIGN KEY (contrato_id) REFERENCES cc_contratos (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB
            SQL);

        // Una cuenta por cuota. Los acumulados se congelan al aprobar para
        // que una corrección posterior no reescriba documentos ya firmados.
        $this->addSql(<<<'SQL'
            CREATE TABLE cc_cuentas (
              id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              contrato_id           BIGINT UNSIGNED NOT NULL,
              pago_id               BIGINT UNSIGNED NOT NULL,
              consecutivo           TINYINT UNSIGNED NOT NULL,
              radicado              VARCHAR(30) NULL,
              periodo_desde         DATE NOT NULL,
              periodo_hasta         DATE NOT NULL,
              dias_periodo          SMALLINT UNSIGNED NOT NULL,
              fecha_cobro           DATE NOT NULL,
              valor                 DECIMAL(14,2) NOT NULL,
              valor_letras          VARCHAR(400) NOT NULL,
              pagos_anteriores      DECIMAL(14,2) NOT NULL DEFAULT 0,
              saldo_por_ejecutar    DECIMAL(14,2) NOT NULL DEFAULT 0,
              estado                ENUM('BORRADOR','ENVIADA','DEVUELTA','APROBADA','ENTREGADA_FINANCIERA','ANULADA') NOT NULL DEFAULT 'BORRADOR',
              enviada_en            DATETIME NULL,
              aprobada_en           DATETIME NULL,
              aprobada_por          BIGINT UNSIGNED NULL,
              fecha_informe_supervision DATE NULL,
              observacion_supervisor TEXT NULL,
              pdf_cuenta_cobro      VARCHAR(255) NULL,
              pdf_informe_actividades VARCHAR(255) NULL,
              pdf_informe_supervision VARCHAR(255) NULL,
              pdf_paquete           VARCHAR(255) NULL,
              creado_en             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizado_en        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_cc_cuentas_radicado (radicado),
              UNIQUE KEY uq_cc_cuentas_consecutivo (contrato_id, consecutivo),
              KEY idx_cc_cuentas_estado (estado),
              KEY idx_cc_cuentas_contrato_estado (contrato_id, estado),
              CONSTRAINT fk_cc_cuentas_contrato FOREIGN KEY (contrato_id) REFERENCES cc_contratos (id) ON DELETE RESTRICT,
              CONSTRAINT fk_cc_cuentas_pago FOREIGN KEY (pago_id) REFERENCES cc_contrato_pagos (id) ON DELETE RESTRICT,
              CONSTRAINT fk_cc_cuentas_aprobador FOREIGN KEY (aprobada_por) REFERENCES cc_supervisores (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB
            SQL);

        // Qué hizo el contratista frente a cada obligación y el Sí/No/
        // Parcial que marca el supervisor.
        $this->addSql(<<<'SQL'
            CREATE TABLE cc_cuenta_actividades (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              cuenta_id       BIGINT UNSIGNED NOT NULL,
              obligacion_id   BIGINT UNSIGNED NOT NULL,
              descripcion_ejecucion TEXT NULL,
              cumplimiento    ENUM('SI','NO','PARCIAL') NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_cc_actividades (cuenta_id, obligacion_id),
              CONSTRAINT fk_cc_actividades_cuenta FOREIGN KEY (cuenta_id) REFERENCES cc_cuentas (id) ON DELETE RESTRICT,
              CONSTRAINT fk_cc_actividades_obligacion FOREIGN KEY (obligacion_id) REFERENCES cc_contrato_obligaciones (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB
            SQL);

        // La planilla PILA reportada, junto a lo que el sistema sugirió
        // (JSON) para resaltar diferencias al supervisor.
        $this->addSql(<<<'SQL'
            CREATE TABLE cc_cuenta_seguridad_social (
              cuenta_id         BIGINT UNSIGNED NOT NULL,
              numero_planilla   VARCHAR(30) NOT NULL,
              mes_salud         CHAR(7) NOT NULL,
              mes_pension       CHAR(7) NOT NULL,
              ibc               DECIMAL(14,2) NOT NULL,
              aporte_salud      DECIMAL(14,2) NOT NULL,
              aporte_pension    DECIMAL(14,2) NOT NULL,
              aporte_fsp        DECIMAL(14,2) NOT NULL DEFAULT 0,
              aporte_arl        DECIMAL(14,2) NOT NULL,
              tarifa_arl        DECIMAL(6,5) NOT NULL,
              aporte_sena       DECIMAL(14,2) NOT NULL DEFAULT 0,
              aporte_icbf       DECIMAL(14,2) NOT NULL DEFAULT 0,
              aporte_ccf        DECIMAL(14,2) NOT NULL DEFAULT 0,
              total_aportes     DECIMAL(14,2) NOT NULL,
              valores_sugeridos_json JSON NULL,
              PRIMARY KEY (cuenta_id),
              CONSTRAINT fk_cc_ss_cuenta FOREIGN KEY (cuenta_id) REFERENCES cc_cuentas (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB
            SQL);

        // Anexos subidos a la cuenta (planilla, paz y salvo, impuestos).
        $this->addSql(<<<'SQL'
            CREATE TABLE cc_cuenta_adjuntos (
              id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              cuenta_id   BIGINT UNSIGNED NOT NULL,
              tipo        ENUM('PLANILLA_PILA','PAZ_Y_SALVO','IMPUESTOS_DEPARTAMENTALES','OTRO') NOT NULL,
              nombre_original VARCHAR(255) NOT NULL,
              archivo     VARCHAR(255) NOT NULL,
              mime        VARCHAR(80) NOT NULL,
              tamano      INT UNSIGNED NOT NULL,
              subido_por  BIGINT UNSIGNED NOT NULL,
              subido_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_cc_adjuntos_cuenta (cuenta_id),
              CONSTRAINT fk_cc_adjuntos_cuenta FOREIGN KEY (cuenta_id) REFERENCES cc_cuentas (id) ON DELETE RESTRICT,
              CONSTRAINT fk_cc_adjuntos_usuario FOREIGN KEY (subido_por) REFERENCES usuarios (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB
            SQL);

        // Historial de transiciones del flujo, además de la auditoría
        // general de la plataforma.
        $this->addSql(<<<'SQL'
            CREATE TABLE cc_cuenta_historial (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              cuenta_id       BIGINT UNSIGNED NOT NULL,
              estado_anterior VARCHAR(30) NULL,
              estado_nuevo    VARCHAR(30) NOT NULL,
              usuario_id      BIGINT UNSIGNED NOT NULL,
              rol             ENUM('CONTRATISTA','SUPERVISOR','ADMIN','SISTEMA') NOT NULL,
              observacion     TEXT NULL,
              ip              VARCHAR(45) NULL,
              creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_cc_historial_cuenta (cuenta_id),
              CONSTRAINT fk_cc_historial_cuenta FOREIGN KEY (cuenta_id) REFERENCES cc_cuentas (id) ON DELETE RESTRICT,
              CONSTRAINT fk_cc_historial_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB
            SQL);

        // Evidencia de firma electrónica simple: quién, cuándo, desde
        // dónde y sobre qué hash de PDF (Ley 527 de 1999).
        $this->addSql(<<<'SQL'
            CREATE TABLE cc_cuenta_firmas (
              id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              cuenta_id     BIGINT UNSIGNED NOT NULL,
              documento     ENUM('CUENTA_COBRO','INFORME_ACTIVIDADES','INFORME_SUPERVISION') NOT NULL,
              firmante_rol  ENUM('CONTRATISTA','SUPERVISOR') NOT NULL,
              usuario_id    BIGINT UNSIGNED NOT NULL,
              firma_archivo VARCHAR(255) NOT NULL,
              hash_pdf      CHAR(64) NOT NULL,
              ip            VARCHAR(45) NOT NULL,
              user_agent    VARCHAR(300) NULL,
              firmado_en    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_cc_firmas_cuenta (cuenta_id),
              CONSTRAINT fk_cc_firmas_cuenta FOREIGN KEY (cuenta_id) REFERENCES cc_cuentas (id) ON DELETE RESTRICT,
              CONSTRAINT fk_cc_firmas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB
            SQL);

        // Parámetros de ley por año y tarifas ARL, sembrados de una vez:
        // sin ellos el módulo no calcula nada.
        $this->addSql(<<<'SQL'
            CREATE TABLE cc_parametros_anuales (
              anio                  SMALLINT UNSIGNED NOT NULL,
              smlv                  DECIMAL(14,2) NOT NULL,
              porcentaje_ibc        DECIMAL(5,4) NOT NULL DEFAULT 0.4000,
              tarifa_salud          DECIMAL(5,4) NOT NULL DEFAULT 0.1250,
              tarifa_pension        DECIMAL(5,4) NOT NULL DEFAULT 0.1600,
              redondeo_aportes      INT UNSIGNED NOT NULL DEFAULT 100,
              PRIMARY KEY (anio)
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE cc_arl_clases (
              clase   TINYINT UNSIGNED NOT NULL,
              tarifa  DECIMAL(6,5) NOT NULL,
              descripcion VARCHAR(80) NOT NULL,
              PRIMARY KEY (clase)
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO cc_arl_clases (clase, tarifa, descripcion) VALUES
              (1, 0.00522, 'Riesgo mínimo'),
              (2, 0.01044, 'Riesgo bajo'),
              (3, 0.02436, 'Riesgo medio'),
              (4, 0.04350, 'Riesgo alto'),
              (5, 0.06960, 'Riesgo máximo')
            SQL);

        $this->addSql("INSERT INTO cc_parametros_anuales (anio, smlv) VALUES (2026, 1750905.00)");

        // Códigos de un solo uso para el ingreso del contratista (fase 2).
        $this->addSql(<<<'SQL'
            CREATE TABLE cc_otp (
              id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              contratista_id BIGINT UNSIGNED NOT NULL,
              canal          ENUM('WHATSAPP','SMS','CORREO') NOT NULL,
              codigo_hash    VARCHAR(255) NOT NULL,
              expira_en      DATETIME NOT NULL,
              usado_en       DATETIME NULL,
              intentos       TINYINT UNSIGNED NOT NULL DEFAULT 0,
              ip             VARCHAR(45) NOT NULL,
              creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_cc_otp_contratista (contratista_id, expira_en),
              CONSTRAINT fk_cc_otp_contratista FOREIGN KEY (contratista_id) REFERENCES cc_contratistas (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB
            SQL);

        // Los tres formatos institucionales, con su identidad SGC. El
        // código del informe de actividades lo definirá el Interventor:
        // queda NULL y configurable (decisión 14 del documento).
        $this->addSql(<<<'SQL'
            CREATE TABLE cc_plantillas (
              clave        VARCHAR(40) NOT NULL,
              nombre       VARCHAR(120) NOT NULL,
              codigo_sgc   VARCHAR(30) NULL,
              version_sgc  VARCHAR(10) NULL,
              fecha_sgc    DATE NULL,
              proceso      VARCHAR(80) NULL,
              archivo_html VARCHAR(255) NOT NULL,
              activa       TINYINT(1) NOT NULL DEFAULT 1,
              PRIMARY KEY (clave)
            ) ENGINE=InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO cc_plantillas (clave, nombre, codigo_sgc, version_sgc, proceso, archivo_html) VALUES
              ('CUENTA_COBRO', 'Cuenta de cobro', 'GF-FR-002', '001', 'Gestión Financiera (GF)', 'templates/pdf/cuenta-cobro.html'),
              ('INFORME_ACTIVIDADES', 'Informe de actividades', NULL, NULL, NULL, 'templates/pdf/informe-actividades.html'),
              ('INFORME_SUPERVISION', 'Informe de supervisión contractual', 'GJ-FR-004', '002', 'Gestión Jurídica', 'templates/pdf/informe-supervision.html')
            SQL);

        // Trabajos de extracción asistida de minutas (fase 5).
        $this->addSql(<<<'SQL'
            CREATE TABLE cc_extracciones (
              id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              minuta_archivo VARCHAR(255) NOT NULL,
              contrato_id    BIGINT UNSIGNED NULL,
              estado         ENUM('PENDIENTE','PROCESANDO','LISTA','ERROR','APLICADA') NOT NULL DEFAULT 'PENDIENTE',
              proveedor      VARCHAR(40) NULL,
              modelo         VARCHAR(80) NULL,
              resultado_json JSON NULL,
              alertas_json   JSON NULL,
              error          TEXT NULL,
              creado_por     BIGINT UNSIGNED NOT NULL,
              creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              terminado_en   DATETIME NULL,
              PRIMARY KEY (id),
              CONSTRAINT fk_cc_extracciones_contrato FOREIGN KEY (contrato_id) REFERENCES cc_contratos (id) ON DELETE RESTRICT,
              CONSTRAINT fk_cc_extracciones_creador FOREIGN KEY (creado_por) REFERENCES usuarios (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB
            SQL);
    }

    /** Las tablas caen en orden inverso a sus FK. Sin datos que rescatar: la fase 1 arranca vacía. */
    public function down(Schema $schema): void
    {
        foreach ([
            'cc_extracciones',
            'cc_plantillas',
            'cc_otp',
            'cc_arl_clases',
            'cc_parametros_anuales',
            'cc_cuenta_firmas',
            'cc_cuenta_historial',
            'cc_cuenta_adjuntos',
            'cc_cuenta_seguridad_social',
            'cc_cuenta_actividades',
            'cc_cuentas',
            'cc_contrato_pagos',
            'cc_contrato_obligaciones',
            'cc_contrato_imputaciones',
            'cc_contrato_novedades',
            'cc_contratos',
            'cc_supervisores',
            'cc_contratistas',
        ] as $tabla) {
            $this->addSql('DROP TABLE ' . $tabla);
        }
    }
}

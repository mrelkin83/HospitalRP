-- =====================================================================
-- Sitio web E.S.E. Hospital Rosario Pumarejo de López
-- Esquema de base de datos — v1.0
-- MySQL 8+ / InnoDB / utf8mb4_0900_ai_ci
--
-- Convenciones:
--   - Toda tabla de negocio: creado_en, actualizado_en, creado_por, actualizado_por
--   - Borrado lógico (eliminado_en) en contenido, documentos y trámites
--   - Contenido publicable: estado_aprobacion, publicar_en, despublicar_en
--   - Este archivo es la referencia. Implementar como migraciones versionadas,
--     no ejecutar de una sola vez en producción.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================================
-- 1. SISTEMA
-- =====================================================================

CREATE TABLE roles (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre          VARCHAR(80)  NOT NULL,
  slug            VARCHAR(80)  NOT NULL,
  descripcion     VARCHAR(255) NULL,
  permisos        JSON         NOT NULL,
  protegido       TINYINT(1)   NOT NULL DEFAULT 0,
  creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB;

CREATE TABLE dependencias (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre          VARCHAR(160) NOT NULL,
  sigla           VARCHAR(20)  NULL,
  responsable     VARCHAR(160) NULL,
  correo          VARCHAR(160) NULL,
  telefono        VARCHAR(40)  NULL,
  orden           SMALLINT     NOT NULL DEFAULT 0,
  activa          TINYINT(1)   NOT NULL DEFAULT 1,
  creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_dependencias_activa (activa)
) ENGINE=InnoDB;

CREATE TABLE usuarios (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre            VARCHAR(160) NOT NULL,
  correo            VARCHAR(160) NOT NULL,
  hash_password     VARCHAR(255) NOT NULL,
  rol_id            BIGINT UNSIGNED NOT NULL,
  dependencia_id    BIGINT UNSIGNED NULL,
  cargo             VARCHAR(120) NULL,
  activo            TINYINT(1)   NOT NULL DEFAULT 1,
  mfa_secreto       VARBINARY(255) NULL,
  mfa_habilitado    TINYINT(1)   NOT NULL DEFAULT 0,
  debe_cambiar_pass TINYINT(1)   NOT NULL DEFAULT 1,
  intentos_fallidos SMALLINT     NOT NULL DEFAULT 0,
  bloqueado_hasta   DATETIME     NULL,
  ultimo_acceso     DATETIME     NULL,
  ultima_ip         VARBINARY(16) NULL,
  eliminado_en      DATETIME     NULL,
  creado_en         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_correo (correo),
  KEY idx_usuarios_rol (rol_id),
  KEY idx_usuarios_dependencia (dependencia_id),
  CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id) REFERENCES roles (id) ON DELETE RESTRICT,
  CONSTRAINT fk_usuarios_dependencia FOREIGN KEY (dependencia_id) REFERENCES dependencias (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE archivos (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre_original VARCHAR(255) NOT NULL,
  ruta            VARCHAR(500) NOT NULL,
  mime            VARCHAR(120) NOT NULL,
  extension       VARCHAR(20)  NOT NULL,
  tamano          BIGINT UNSIGNED NOT NULL,
  hash_sha256     CHAR(64)     NOT NULL,
  ancho           INT UNSIGNED NULL,
  alto            INT UNSIGNED NULL,
  texto_alt       VARCHAR(255) NULL,
  publico         TINYINT(1)   NOT NULL DEFAULT 1,
  descargas       INT UNSIGNED NOT NULL DEFAULT 0,
  creado_por      BIGINT UNSIGNED NULL,
  creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_archivos_hash (hash_sha256),
  KEY idx_archivos_mime (mime),
  CONSTRAINT fk_archivos_creador FOREIGN KEY (creado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE configuracion (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clave           VARCHAR(120) NOT NULL,
  valor           TEXT         NULL,
  tipo            ENUM('texto','texto_largo','numero','booleano','json','color','archivo','html') NOT NULL DEFAULT 'texto',
  grupo           VARCHAR(60)  NOT NULL DEFAULT 'general',
  etiqueta        VARCHAR(160) NOT NULL,
  ayuda           VARCHAR(255) NULL,
  orden           SMALLINT     NOT NULL DEFAULT 0,
  editable        TINYINT(1)   NOT NULL DEFAULT 1,
  actualizado_por BIGINT UNSIGNED NULL,
  actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_configuracion_clave (clave),
  KEY idx_configuracion_grupo (grupo)
) ENGINE=InnoDB;

-- Append-only. Nunca UPDATE ni DELETE.
CREATE TABLE auditoria (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id      BIGINT UNSIGNED NULL,
  usuario_nombre  VARCHAR(160) NULL,
  accion          VARCHAR(60)  NOT NULL,
  entidad         VARCHAR(80)  NOT NULL,
  entidad_id      BIGINT UNSIGNED NULL,
  descripcion     VARCHAR(500) NULL,
  antes           JSON         NULL,
  despues         JSON         NULL,
  ip              VARBINARY(16) NULL,
  user_agent      VARCHAR(500) NULL,
  creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_auditoria_usuario (usuario_id),
  KEY idx_auditoria_entidad (entidad, entidad_id),
  KEY idx_auditoria_fecha (creado_en)
) ENGINE=InnoDB;

-- El append-only se hace cumplir en la base, no por cortesía del código:
-- cualquier UPDATE o DELETE falla, venga de donde venga.
CREATE TRIGGER trg_auditoria_sin_update
BEFORE UPDATE ON auditoria FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'auditoria es append-only: UPDATE prohibido';

CREATE TRIGGER trg_auditoria_sin_delete
BEFORE DELETE ON auditoria FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'auditoria es append-only: DELETE prohibido';

CREATE TABLE menus (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre          VARCHAR(80)  NOT NULL,
  slug            VARCHAR(80)  NOT NULL,
  ubicacion       ENUM('principal','superior','pie_1','pie_2','pie_3','lateral') NOT NULL,
  protegido       TINYINT(1)   NOT NULL DEFAULT 0,
  creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_menus_slug (slug)
) ENGINE=InnoDB;

CREATE TABLE menu_items (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  menu_id         BIGINT UNSIGNED NOT NULL,
  padre_id        BIGINT UNSIGNED NULL,
  etiqueta        VARCHAR(120) NOT NULL,
  url             VARCHAR(500) NOT NULL,
  icono           VARCHAR(60)  NULL,
  destino         ENUM('mismo','nueva') NOT NULL DEFAULT 'mismo',
  orden           SMALLINT     NOT NULL DEFAULT 0,
  visible         TINYINT(1)   NOT NULL DEFAULT 1,
  protegido       TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_menu_items_menu (menu_id, orden),
  KEY idx_menu_items_padre (padre_id),
  CONSTRAINT fk_menu_items_menu FOREIGN KEY (menu_id) REFERENCES menus (id) ON DELETE CASCADE,
  CONSTRAINT fk_menu_items_padre FOREIGN KEY (padre_id) REFERENCES menu_items (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Migración desde Joomla. Sin esto se rompen enlaces citados en actos administrativos.
CREATE TABLE redirecciones (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  origen          VARCHAR(500) NOT NULL,
  destino         VARCHAR(500) NOT NULL,
  codigo          SMALLINT     NOT NULL DEFAULT 301,
  activa          TINYINT(1)   NOT NULL DEFAULT 1,
  hits            INT UNSIGNED NOT NULL DEFAULT 0,
  ultimo_hit      DATETIME     NULL,
  creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_redirecciones_origen (origen(255))
) ENGINE=InnoDB;

-- Necesaria para calcular términos legales en días hábiles (Ley 1755).
CREATE TABLE festivos (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  fecha           DATE         NOT NULL,
  nombre          VARCHAR(120) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_festivos_fecha (fecha)
) ENGINE=InnoDB;

CREATE TABLE respaldos (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre_archivo  VARCHAR(255) NOT NULL,
  ruta            VARCHAR(500) NOT NULL,
  tamano          BIGINT UNSIGNED NOT NULL,
  tipo            ENUM('completo','base_datos','archivos') NOT NULL,
  origen          ENUM('manual','programado') NOT NULL,
  estado          ENUM('en_proceso','completado','fallido') NOT NULL,
  mensaje         VARCHAR(500) NULL,
  creado_por      BIGINT UNSIGNED NULL,
  creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_respaldos_creador FOREIGN KEY (creado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- 2. CONTENIDO
-- =====================================================================

CREATE TABLE categorias (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo            ENUM('noticia','documento','campana','faq') NOT NULL,
  nombre          VARCHAR(120) NOT NULL,
  slug            VARCHAR(140) NOT NULL,
  descripcion     VARCHAR(255) NULL,
  color           CHAR(7)      NULL,
  orden           SMALLINT     NOT NULL DEFAULT 0,
  activa          TINYINT(1)   NOT NULL DEFAULT 1,
  creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categorias_tipo_slug (tipo, slug)
) ENGINE=InnoDB;

CREATE TABLE paginas (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug              VARCHAR(200) NOT NULL,
  ruta              VARCHAR(500) NOT NULL,
  titulo            VARCHAR(255) NOT NULL,
  resumen           VARCHAR(500) NULL,
  contenido         MEDIUMTEXT   NULL,
  padre_id          BIGINT UNSIGNED NULL,
  plantilla         VARCHAR(60)  NOT NULL DEFAULT 'default',
  imagen_id         BIGINT UNSIGNED NULL,
  orden             SMALLINT     NOT NULL DEFAULT 0,
  meta_titulo       VARCHAR(200) NULL,
  meta_descripcion  VARCHAR(320) NULL,
  noindex           TINYINT(1)   NOT NULL DEFAULT 0,
  estado_aprobacion ENUM('borrador','en_revision','aprobado','publicado') NOT NULL DEFAULT 'borrador',
  aprobado_por      BIGINT UNSIGNED NULL,
  aprobado_en       DATETIME     NULL,
  publicar_en       DATETIME     NULL,
  despublicar_en    DATETIME     NULL,
  protegida         TINYINT(1)   NOT NULL DEFAULT 0,
  vistas            INT UNSIGNED NOT NULL DEFAULT 0,
  eliminado_en      DATETIME     NULL,
  creado_por        BIGINT UNSIGNED NULL,
  actualizado_por   BIGINT UNSIGNED NULL,
  creado_en         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_paginas_ruta (ruta(255)),
  KEY idx_paginas_padre (padre_id),
  KEY idx_paginas_estado (estado_aprobacion, eliminado_en),
  CONSTRAINT fk_paginas_padre FOREIGN KEY (padre_id) REFERENCES paginas (id) ON DELETE SET NULL,
  CONSTRAINT fk_paginas_imagen FOREIGN KEY (imagen_id) REFERENCES archivos (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE noticias (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug              VARCHAR(200) NOT NULL,
  titulo            VARCHAR(255) NOT NULL,
  resumen           VARCHAR(500) NOT NULL,
  contenido         MEDIUMTEXT   NOT NULL,
  imagen_id         BIGINT UNSIGNED NULL,
  categoria_id      BIGINT UNSIGNED NULL,
  destacada         TINYINT(1)   NOT NULL DEFAULT 0,
  meta_titulo       VARCHAR(200) NULL,
  meta_descripcion  VARCHAR(320) NULL,
  estado_aprobacion ENUM('borrador','en_revision','aprobado','publicado') NOT NULL DEFAULT 'borrador',
  aprobado_por      BIGINT UNSIGNED NULL,
  aprobado_en       DATETIME     NULL,
  publicar_en       DATETIME     NULL,
  despublicar_en    DATETIME     NULL,
  vistas            INT UNSIGNED NOT NULL DEFAULT 0,
  eliminado_en      DATETIME     NULL,
  creado_por        BIGINT UNSIGNED NULL,
  actualizado_por   BIGINT UNSIGNED NULL,
  creado_en         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_noticias_slug (slug),
  KEY idx_noticias_publicacion (estado_aprobacion, publicar_en, eliminado_en),
  KEY idx_noticias_categoria (categoria_id),
  CONSTRAINT fk_noticias_imagen FOREIGN KEY (imagen_id) REFERENCES archivos (id) ON DELETE SET NULL,
  CONSTRAINT fk_noticias_categoria FOREIGN KEY (categoria_id) REFERENCES categorias (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE campanas (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug              VARCHAR(200) NOT NULL,
  titulo            VARCHAR(255) NOT NULL,
  resumen           VARCHAR(500) NULL,
  contenido         MEDIUMTEXT   NULL,
  imagen_id         BIGINT UNSIGNED NULL,
  poblacion_objetivo VARCHAR(255) NULL,
  inicio            DATE         NULL,
  fin               DATE         NULL,
  estado_aprobacion ENUM('borrador','en_revision','aprobado','publicado') NOT NULL DEFAULT 'borrador',
  eliminado_en      DATETIME     NULL,
  creado_por        BIGINT UNSIGNED NULL,
  actualizado_por   BIGINT UNSIGNED NULL,
  creado_en         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_campanas_slug (slug),
  CONSTRAINT fk_campanas_imagen FOREIGN KEY (imagen_id) REFERENCES archivos (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Un aviso con nivel='critico' activo convierte la home en modo emergencia.
CREATE TABLE avisos (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo            ENUM('informativo','urgencia','campana','emergencia') NOT NULL DEFAULT 'informativo',
  nivel           ENUM('info','alerta','critico') NOT NULL DEFAULT 'info',
  titulo          VARCHAR(200) NOT NULL,
  mensaje         VARCHAR(1000) NOT NULL,
  url             VARCHAR(500) NULL,
  texto_boton     VARCHAR(80)  NULL,
  desde           DATETIME     NULL,
  hasta           DATETIME     NULL,
  activo          TINYINT(1)   NOT NULL DEFAULT 0,
  prioridad       SMALLINT     NOT NULL DEFAULT 0,
  creado_por      BIGINT UNSIGNED NULL,
  creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_avisos_activo (activo, desde, hasta)
) ENGINE=InnoDB;

-- Ventanas emergentes. Separadas de `avisos` porque un aviso es una banda
-- dentro de la página y un popup se planta encima: solo el popup necesita
-- saber cada cuánto reaparece (`frecuencia`) y en qué páginas (`ubicacion`).
CREATE TABLE popups (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo          VARCHAR(200) NOT NULL,
  mensaje         VARCHAR(1000) NOT NULL,
  imagen_id       BIGINT UNSIGNED NULL,
  url             VARCHAR(500) NULL,
  texto_boton     VARCHAR(80)  NULL,
  ubicacion       ENUM('todas','portada') NOT NULL DEFAULT 'portada',
  frecuencia      ENUM('siempre','sesion','diaria') NOT NULL DEFAULT 'sesion',
  desde           DATETIME     NULL,
  hasta           DATETIME     NULL,
  activo          TINYINT(1)   NOT NULL DEFAULT 0,
  prioridad       SMALLINT     NOT NULL DEFAULT 0,
  creado_por      BIGINT UNSIGNED NULL,
  creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_popups_vigencia (activo, desde, hasta),
  CONSTRAINT fk_popups_imagen FOREIGN KEY (imagen_id) REFERENCES archivos (id) ON DELETE SET NULL,
  CONSTRAINT fk_popups_creador FOREIGN KEY (creado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE faq (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pregunta        VARCHAR(500) NOT NULL,
  respuesta       TEXT         NOT NULL,
  categoria_id    BIGINT UNSIGNED NULL,
  orden           SMALLINT     NOT NULL DEFAULT 0,
  publicada       TINYINT(1)   NOT NULL DEFAULT 1,
  vistas          INT UNSIGNED NOT NULL DEFAULT 0,
  creado_por      BIGINT UNSIGNED NULL,
  creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_faq_categoria (categoria_id),
  CONSTRAINT fk_faq_categoria FOREIGN KEY (categoria_id) REFERENCES categorias (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Versionado genérico. Snapshot JSON de la entidad completa.
CREATE TABLE contenido_versiones (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  entidad         VARCHAR(60)  NOT NULL,
  entidad_id      BIGINT UNSIGNED NOT NULL,
  version         INT UNSIGNED NOT NULL,
  snapshot        JSON         NOT NULL,
  nota            VARCHAR(255) NULL,
  creado_por      BIGINT UNSIGNED NULL,
  creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_versiones (entidad, entidad_id, version),
  KEY idx_versiones_entidad (entidad, entidad_id)
) ENGINE=InnoDB;

-- =====================================================================
-- 3. DIRECTORIO Y SERVICIOS
-- =====================================================================

CREATE TABLE sedes (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre          VARCHAR(160) NOT NULL,
  slug            VARCHAR(180) NOT NULL,
  descripcion     TEXT         NULL,
  direccion       VARCHAR(255) NOT NULL,
  municipio       VARCHAR(120) NOT NULL,
  departamento    VARCHAR(120) NOT NULL DEFAULT 'Cesar',
  telefono        VARCHAR(60)  NULL,
  correo          VARCHAR(160) NULL,
  horario         JSON         NULL,
  latitud         DECIMAL(10,7) NULL,
  longitud        DECIMAL(10,7) NULL,
  tiene_urgencias TINYINT(1)   NOT NULL DEFAULT 0,
  imagen_id       BIGINT UNSIGNED NULL,
  orden           SMALLINT     NOT NULL DEFAULT 0,
  activa          TINYINT(1)   NOT NULL DEFAULT 1,
  creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sedes_slug (slug),
  CONSTRAINT fk_sedes_imagen FOREIGN KEY (imagen_id) REFERENCES archivos (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE especialidades (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre          VARCHAR(160) NOT NULL,
  slug            VARCHAR(180) NOT NULL,
  descripcion     TEXT         NULL,
  icono           VARCHAR(60)  NULL,
  imagen_id       BIGINT UNSIGNED NULL,
  orden           SMALLINT     NOT NULL DEFAULT 0,
  activa          TINYINT(1)   NOT NULL DEFAULT 1,
  creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_especialidades_slug (slug),
  CONSTRAINT fk_especialidades_imagen FOREIGN KEY (imagen_id) REFERENCES archivos (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE servicios (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre          VARCHAR(160) NOT NULL,
  slug            VARCHAR(180) NOT NULL,
  descripcion     TEXT         NULL,
  requisitos      TEXT         NULL,
  horario         VARCHAR(500) NULL,
  telefono        VARCHAR(60)  NULL,
  icono           VARCHAR(60)  NULL,
  imagen_id       BIGINT UNSIGNED NULL,
  orden           SMALLINT     NOT NULL DEFAULT 0,
  activo          TINYINT(1)   NOT NULL DEFAULT 1,
  creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_servicios_slug (slug),
  CONSTRAINT fk_servicios_imagen FOREIGN KEY (imagen_id) REFERENCES archivos (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Datos personales publicados: requiere autorización de cada profesional.
-- No almacenar teléfonos ni correos personales.
CREATE TABLE profesionales (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre          VARCHAR(200) NOT NULL,
  slug            VARCHAR(220) NOT NULL,
  registro_rethus VARCHAR(60)  NULL,
  perfil          TEXT         NULL,
  foto_id         BIGINT UNSIGNED NULL,
  autoriza_publicacion TINYINT(1) NOT NULL DEFAULT 0,
  orden           SMALLINT     NOT NULL DEFAULT 0,
  activo          TINYINT(1)   NOT NULL DEFAULT 1,
  creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_profesionales_slug (slug),
  CONSTRAINT fk_profesionales_foto FOREIGN KEY (foto_id) REFERENCES archivos (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE servicio_sede (
  servicio_id     BIGINT UNSIGNED NOT NULL,
  sede_id         BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (servicio_id, sede_id),
  CONSTRAINT fk_ss_servicio FOREIGN KEY (servicio_id) REFERENCES servicios (id) ON DELETE CASCADE,
  CONSTRAINT fk_ss_sede FOREIGN KEY (sede_id) REFERENCES sedes (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE especialidad_sede (
  especialidad_id BIGINT UNSIGNED NOT NULL,
  sede_id         BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (especialidad_id, sede_id),
  CONSTRAINT fk_es_especialidad FOREIGN KEY (especialidad_id) REFERENCES especialidades (id) ON DELETE CASCADE,
  CONSTRAINT fk_es_sede FOREIGN KEY (sede_id) REFERENCES sedes (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE profesional_especialidad (
  profesional_id  BIGINT UNSIGNED NOT NULL,
  especialidad_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (profesional_id, especialidad_id),
  CONSTRAINT fk_pe_profesional FOREIGN KEY (profesional_id) REFERENCES profesionales (id) ON DELETE CASCADE,
  CONSTRAINT fk_pe_especialidad FOREIGN KEY (especialidad_id) REFERENCES especialidades (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- 4. GESTIÓN DOCUMENTAL
-- =====================================================================

CREATE TABLE etiquetas (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre          VARCHAR(80)  NOT NULL,
  slug            VARCHAR(100) NOT NULL,
  usos            INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_etiquetas_slug (slug)
) ENGINE=InnoDB;

CREATE TABLE documentos (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo              VARCHAR(300) NOT NULL,
  slug                VARCHAR(320) NOT NULL,
  resumen             VARCHAR(1000) NOT NULL,
  archivo_id          BIGINT UNSIGNED NOT NULL,
  categoria_id        BIGINT UNSIGNED NULL,
  dependencia_id      BIGINT UNSIGNED NULL,
  numero_acto         VARCHAR(80)  NULL,
  fecha_documento     DATE         NOT NULL,
  vigencia_desde      DATE         NULL,
  vigencia_hasta      DATE         NULL,
  version             INT UNSIGNED NOT NULL DEFAULT 1,
  estado              ENUM('borrador','vigente','derogado','historico') NOT NULL DEFAULT 'borrador',
  reemplaza_a_id      BIGINT UNSIGNED NULL,
  -- Tablas de Retención Documental (Archivo General de la Nación)
  serie_documental    VARCHAR(160) NULL,
  subserie_documental VARCHAR(160) NULL,
  tiempo_retencion    SMALLINT     NULL,
  publicado           TINYINT(1)   NOT NULL DEFAULT 0,
  descargas           INT UNSIGNED NOT NULL DEFAULT 0,
  eliminado_en        DATETIME     NULL,
  creado_por          BIGINT UNSIGNED NULL,
  actualizado_por     BIGINT UNSIGNED NULL,
  creado_en           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_documentos_slug (slug),
  KEY idx_documentos_estado (estado, publicado, eliminado_en),
  KEY idx_documentos_dependencia (dependencia_id),
  KEY idx_documentos_categoria (categoria_id),
  KEY idx_documentos_vigencia (vigencia_hasta),
  KEY idx_documentos_fecha (fecha_documento),
  FULLTEXT KEY ft_documentos (titulo, resumen),
  CONSTRAINT fk_documentos_archivo FOREIGN KEY (archivo_id) REFERENCES archivos (id) ON DELETE RESTRICT,
  CONSTRAINT fk_documentos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias (id) ON DELETE SET NULL,
  CONSTRAINT fk_documentos_dependencia FOREIGN KEY (dependencia_id) REFERENCES dependencias (id) ON DELETE SET NULL,
  CONSTRAINT fk_documentos_reemplaza FOREIGN KEY (reemplaza_a_id) REFERENCES documentos (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE documento_versiones (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  documento_id    BIGINT UNSIGNED NOT NULL,
  version         INT UNSIGNED NOT NULL,
  archivo_id      BIGINT UNSIGNED NOT NULL,
  notas           VARCHAR(500) NULL,
  creado_por      BIGINT UNSIGNED NULL,
  creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_documento_version (documento_id, version),
  CONSTRAINT fk_docver_documento FOREIGN KEY (documento_id) REFERENCES documentos (id) ON DELETE CASCADE,
  CONSTRAINT fk_docver_archivo FOREIGN KEY (archivo_id) REFERENCES archivos (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE documento_etiqueta (
  documento_id    BIGINT UNSIGNED NOT NULL,
  etiqueta_id     BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (documento_id, etiqueta_id),
  CONSTRAINT fk_de_documento FOREIGN KEY (documento_id) REFERENCES documentos (id) ON DELETE CASCADE,
  CONSTRAINT fk_de_etiqueta FOREIGN KEY (etiqueta_id) REFERENCES etiquetas (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- 5. TRANSPARENCIA Y DATOS ABIERTOS
-- =====================================================================

-- Estructura fija sembrada por migración según Res. 1519 de 2020, Anexo 2.
-- Verificar la versión vigente del anexo antes de sembrar.
CREATE TABLE transparencia_secciones (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo              VARCHAR(20)  NOT NULL,
  nombre              VARCHAR(300) NOT NULL,
  descripcion         TEXT         NULL,
  padre_id            BIGINT UNSIGNED NULL,
  orden               SMALLINT     NOT NULL DEFAULT 0,
  obligatoria         TINYINT(1)   NOT NULL DEFAULT 1,
  aplica              TINYINT(1)   NOT NULL DEFAULT 1,
  periodicidad        ENUM('ninguna','mensual','trimestral','semestral','anual','permanente') NOT NULL DEFAULT 'ninguna',
  dependencia_id      BIGINT UNSIGNED NULL,
  ultima_actualizacion DATE        NULL,
  proxima_actualizacion DATE       NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_transp_codigo (codigo),
  KEY idx_transp_padre (padre_id),
  CONSTRAINT fk_transp_padre FOREIGN KEY (padre_id) REFERENCES transparencia_secciones (id) ON DELETE CASCADE,
  CONSTRAINT fk_transp_dependencia FOREIGN KEY (dependencia_id) REFERENCES dependencias (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE transparencia_items (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  seccion_id      BIGINT UNSIGNED NOT NULL,
  documento_id    BIGINT UNSIGNED NULL,
  url_externa     VARCHAR(500) NULL,
  titulo          VARCHAR(300) NOT NULL,
  descripcion     VARCHAR(500) NULL,
  orden           SMALLINT     NOT NULL DEFAULT 0,
  publicado       TINYINT(1)   NOT NULL DEFAULT 1,
  creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_transpitem_seccion (seccion_id, orden),
  CONSTRAINT fk_transpitem_seccion FOREIGN KEY (seccion_id) REFERENCES transparencia_secciones (id) ON DELETE CASCADE,
  -- CASCADE y no SET NULL: MySQL 8 (error 3823) no admite el CHECK sobre una
  -- columna con SET NULL. Corregido en la Fase 4.
  CONSTRAINT fk_transpitem_documento FOREIGN KEY (documento_id) REFERENCES documentos (id) ON DELETE CASCADE,
  CONSTRAINT chk_transpitem_destino CHECK (documento_id IS NOT NULL OR url_externa IS NOT NULL)
) ENGINE=InnoDB;

CREATE TABLE datasets (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo          VARCHAR(300) NOT NULL,
  slug            VARCHAR(320) NOT NULL,
  descripcion     TEXT         NOT NULL,
  dependencia_id  BIGINT UNSIGNED NULL,
  periodicidad    ENUM('ninguna','mensual','trimestral','semestral','anual') NOT NULL DEFAULT 'anual',
  licencia        VARCHAR(160) NOT NULL DEFAULT 'Datos abiertos - Ley 1712 de 2014',
  cobertura_desde DATE         NULL,
  cobertura_hasta DATE         NULL,
  publicado       TINYINT(1)   NOT NULL DEFAULT 0,
  creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_datasets_slug (slug),
  CONSTRAINT fk_datasets_dependencia FOREIGN KEY (dependencia_id) REFERENCES dependencias (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE dataset_recursos (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  dataset_id      BIGINT UNSIGNED NOT NULL,
  formato         ENUM('csv','json','xml','xlsx','geojson') NOT NULL,
  archivo_id      BIGINT UNSIGNED NULL,
  url_externa     VARCHAR(500) NULL,
  descargas       INT UNSIGNED NOT NULL DEFAULT 0,
  creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_dsrec_dataset (dataset_id),
  CONSTRAINT fk_dsrec_dataset FOREIGN KEY (dataset_id) REFERENCES datasets (id) ON DELETE CASCADE,
  CONSTRAINT fk_dsrec_archivo FOREIGN KEY (archivo_id) REFERENCES archivos (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- 6. MOTOR DE TRÁMITES
-- Motor configurable, NO un BPM. PQRSDF es un tipo de trámite.
-- =====================================================================

CREATE TABLE tipos_tramite (
  id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre                VARCHAR(200) NOT NULL,
  slug                  VARCHAR(220) NOT NULL,
  descripcion           TEXT         NULL,
  instrucciones         TEXT         NULL,
  prefijo_radicado      VARCHAR(20)  NOT NULL DEFAULT 'HRPL',
  dependencia_id        BIGINT UNSIGNED NULL,
  dias_termino          SMALLINT     NOT NULL DEFAULT 15,
  tipo_dias             ENUM('habiles','calendario') NOT NULL DEFAULT 'habiles',
  fundamento_legal      VARCHAR(255) NULL,
  permite_anonimo       TINYINT(1)   NOT NULL DEFAULT 0,
  requiere_adjunto      TINYINT(1)   NOT NULL DEFAULT 0,
  max_adjuntos          SMALLINT     NOT NULL DEFAULT 3,
  notificar_correos     VARCHAR(500) NULL,
  icono                 VARCHAR(60)  NULL,
  orden                 SMALLINT     NOT NULL DEFAULT 0,
  activo                TINYINT(1)   NOT NULL DEFAULT 1,
  creado_en             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tipostramite_slug (slug),
  CONSTRAINT fk_tipostramite_dependencia FOREIGN KEY (dependencia_id) REFERENCES dependencias (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE tramite_estados (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo_tramite_id     BIGINT UNSIGNED NOT NULL,
  codigo              VARCHAR(60)  NOT NULL,
  nombre              VARCHAR(120) NOT NULL,
  descripcion_publica VARCHAR(255) NULL,
  color               CHAR(7)      NULL,
  orden               SMALLINT     NOT NULL DEFAULT 0,
  es_inicial          TINYINT(1)   NOT NULL DEFAULT 0,
  es_final            TINYINT(1)   NOT NULL DEFAULT 0,
  detiene_termino     TINYINT(1)   NOT NULL DEFAULT 0,
  notifica_ciudadano  TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tramestado (tipo_tramite_id, codigo),
  CONSTRAINT fk_tramestado_tipo FOREIGN KEY (tipo_tramite_id) REFERENCES tipos_tramite (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tramite_campos (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo_tramite_id BIGINT UNSIGNED NOT NULL,
  codigo          VARCHAR(60)  NOT NULL,
  etiqueta        VARCHAR(200) NOT NULL,
  ayuda           VARCHAR(255) NULL,
  tipo_campo      ENUM('texto','texto_largo','numero','fecha','correo','telefono','select','multiselect','radio','checkbox','archivo','documento_id') NOT NULL,
  opciones        JSON         NULL,
  requerido       TINYINT(1)   NOT NULL DEFAULT 0,
  validacion      VARCHAR(255) NULL,
  orden           SMALLINT     NOT NULL DEFAULT 0,
  grupo           VARCHAR(80)  NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tramcampo (tipo_tramite_id, codigo),
  CONSTRAINT fk_tramcampo_tipo FOREIGN KEY (tipo_tramite_id) REFERENCES tipos_tramite (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tramites (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  radicado            VARCHAR(40)  NOT NULL,
  tipo_tramite_id     BIGINT UNSIGNED NOT NULL,
  canal               ENUM('web','presencial','telefonico','correo','buzon','whatsapp') NOT NULL DEFAULT 'web',
  anonimo             TINYINT(1)   NOT NULL DEFAULT 0,
  -- Datos del solicitante (campos mínimos según Anexo 2 Res. 1519)
  nombre              VARCHAR(200) NULL,
  tipo_documento      ENUM('CC','TI','CE','PA','RC','NIT','PEP','NUIP','OTRO') NULL,
  numero_documento    VARCHAR(40)  NULL,
  correo              VARCHAR(160) NULL,
  telefono            VARCHAR(60)  NULL,
  direccion           VARCHAR(255) NULL,
  municipio           VARCHAR(120) NULL,
  departamento        VARCHAR(120) NULL,
  poblacion_especial  VARCHAR(120) NULL,
  eps                 VARCHAR(160) NULL,
  regimen             ENUM('contributivo','subsidiado','especial','vinculado','particular') NULL,
  -- Contenido
  asunto              VARCHAR(300) NOT NULL,
  descripcion         TEXT         NOT NULL,
  -- Gestión
  dependencia_id      BIGINT UNSIGNED NULL,
  asignado_a          BIGINT UNSIGNED NULL,
  estado_id           BIGINT UNSIGNED NOT NULL,
  medio_respuesta     ENUM('correo','fisico','telefono','presencial') NOT NULL DEFAULT 'correo',
  -- Términos legales
  recibido_en         DATETIME     NOT NULL,
  dias_termino        SMALLINT     NOT NULL,
  vence_en            DATE         NOT NULL,
  respondido_en       DATETIME     NULL,
  cerrado_en          DATETIME     NULL,
  dias_habiles_usados SMALLINT     NULL,
  -- Trazabilidad
  ip                  VARBINARY(16) NULL,
  user_agent          VARCHAR(500) NULL,
  eliminado_en        DATETIME     NULL,
  creado_en           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tramites_radicado (radicado),
  KEY idx_tramites_tipo (tipo_tramite_id),
  KEY idx_tramites_estado (estado_id),
  KEY idx_tramites_vence (vence_en, respondido_en),
  KEY idx_tramites_documento (numero_documento),
  KEY idx_tramites_dependencia (dependencia_id),
  CONSTRAINT fk_tramites_tipo FOREIGN KEY (tipo_tramite_id) REFERENCES tipos_tramite (id) ON DELETE RESTRICT,
  CONSTRAINT fk_tramites_estado FOREIGN KEY (estado_id) REFERENCES tramite_estados (id) ON DELETE RESTRICT,
  CONSTRAINT fk_tramites_dependencia FOREIGN KEY (dependencia_id) REFERENCES dependencias (id) ON DELETE SET NULL,
  CONSTRAINT fk_tramites_asignado FOREIGN KEY (asignado_a) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Secuencia de radicado. Bloqueo transaccional al incrementar.
CREATE TABLE radicado_secuencias (
  anio            SMALLINT     NOT NULL,
  prefijo         VARCHAR(20)  NOT NULL,
  ultimo          INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (anio, prefijo)
) ENGINE=InnoDB;

CREATE TABLE tramite_valores (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tramite_id      BIGINT UNSIGNED NOT NULL,
  campo_id        BIGINT UNSIGNED NOT NULL,
  valor           TEXT         NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tramvalor (tramite_id, campo_id),
  CONSTRAINT fk_tramvalor_tramite FOREIGN KEY (tramite_id) REFERENCES tramites (id) ON DELETE CASCADE,
  CONSTRAINT fk_tramvalor_campo FOREIGN KEY (campo_id) REFERENCES tramite_campos (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tramite_seguimiento (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tramite_id          BIGINT UNSIGNED NOT NULL,
  estado_anterior_id  BIGINT UNSIGNED NULL,
  estado_nuevo_id     BIGINT UNSIGNED NULL,
  nota                TEXT         NULL,
  visible_ciudadano   TINYINT(1)   NOT NULL DEFAULT 0,
  usuario_id          BIGINT UNSIGNED NULL,
  creado_en           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tramseg_tramite (tramite_id, creado_en),
  CONSTRAINT fk_tramseg_tramite FOREIGN KEY (tramite_id) REFERENCES tramites (id) ON DELETE CASCADE,
  CONSTRAINT fk_tramseg_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE tramite_adjuntos (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tramite_id      BIGINT UNSIGNED NOT NULL,
  archivo_id      BIGINT UNSIGNED NOT NULL,
  origen          ENUM('ciudadano','hospital') NOT NULL,
  descripcion     VARCHAR(255) NULL,
  creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tramadj_tramite (tramite_id),
  CONSTRAINT fk_tramadj_tramite FOREIGN KEY (tramite_id) REFERENCES tramites (id) ON DELETE CASCADE,
  CONSTRAINT fk_tramadj_archivo FOREIGN KEY (archivo_id) REFERENCES archivos (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =====================================================================
-- 7. CONVOCATORIAS
-- =====================================================================

CREATE TABLE convocatorias (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo          VARCHAR(300) NOT NULL,
  slug            VARCHAR(320) NOT NULL,
  tipo            ENUM('empleo','contratacion','proveedores','otra') NOT NULL,
  descripcion     MEDIUMTEXT   NOT NULL,
  dependencia_id  BIGINT UNSIGNED NULL,
  estado          ENUM('proxima','abierta','en_evaluacion','cerrada','desierta','adjudicada') NOT NULL DEFAULT 'proxima',
  apertura        DATETIME     NULL,
  cierre          DATETIME     NULL,
  url_externa     VARCHAR(500) NULL,
  publicada       TINYINT(1)   NOT NULL DEFAULT 0,
  eliminado_en    DATETIME     NULL,
  creado_por      BIGINT UNSIGNED NULL,
  creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_convocatorias_slug (slug),
  KEY idx_convocatorias_estado (estado, publicada, cierre),
  CONSTRAINT fk_convocatorias_dependencia FOREIGN KEY (dependencia_id) REFERENCES dependencias (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE convocatoria_documentos (
  convocatoria_id BIGINT UNSIGNED NOT NULL,
  documento_id    BIGINT UNSIGNED NOT NULL,
  orden           SMALLINT     NOT NULL DEFAULT 0,
  PRIMARY KEY (convocatoria_id, documento_id),
  CONSTRAINT fk_cd_convocatoria FOREIGN KEY (convocatoria_id) REFERENCES convocatorias (id) ON DELETE CASCADE,
  CONSTRAINT fk_cd_documento FOREIGN KEY (documento_id) REFERENCES documentos (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- 8. CUMPLIMIENTO Y DATOS PERSONALES
-- =====================================================================

CREATE TABLE politicas (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo            ENUM('tratamiento_datos','aviso_privacidad','cookies','terminos','accesibilidad','seguridad') NOT NULL,
  version         VARCHAR(20)  NOT NULL,
  titulo          VARCHAR(255) NOT NULL,
  contenido       MEDIUMTEXT   NOT NULL,
  documento_id    BIGINT UNSIGNED NULL,
  vigente_desde   DATE         NOT NULL,
  vigente_hasta   DATE         NULL,
  activa          TINYINT(1)   NOT NULL DEFAULT 0,
  creado_por      BIGINT UNSIGNED NULL,
  creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_politicas_tipo_version (tipo, version),
  KEY idx_politicas_activa (tipo, activa),
  CONSTRAINT fk_politicas_documento FOREIGN KEY (documento_id) REFERENCES documentos (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Evidencia de autorización. Ley 1581 de 2012.
CREATE TABLE autorizaciones_datos (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  origen            ENUM('tramite','contacto','suscripcion','otro') NOT NULL,
  origen_id         BIGINT UNSIGNED NULL,
  numero_documento  VARCHAR(40)  NULL,
  correo            VARCHAR(160) NULL,
  politica_id       BIGINT UNSIGNED NOT NULL,
  finalidad         VARCHAR(500) NOT NULL,
  aceptado          TINYINT(1)   NOT NULL,
  ip                VARBINARY(16) NOT NULL,
  user_agent        VARCHAR(500) NULL,
  revocado_en       DATETIME     NULL,
  creado_en         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_autoriz_origen (origen, origen_id),
  KEY idx_autoriz_documento (numero_documento),
  CONSTRAINT fk_autoriz_politica FOREIGN KEY (politica_id) REFERENCES politicas (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE consentimientos_cookies (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  token           CHAR(36)     NOT NULL,
  necesarias      TINYINT(1)   NOT NULL DEFAULT 1,
  analiticas      TINYINT(1)   NOT NULL DEFAULT 0,
  funcionales     TINYINT(1)   NOT NULL DEFAULT 0,
  politica_id     BIGINT UNSIGNED NULL,
  ip              VARBINARY(16) NULL,
  creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cookies_token (token)
) ENGINE=InnoDB;

-- =====================================================================
-- 9. BÚSQUEDA UNIFICADA
-- Índice denormalizado. Se reconstruye por evento al publicar/actualizar.
-- =====================================================================

CREATE TABLE indice_busqueda (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo            ENUM('pagina','noticia','documento','especialidad','servicio','profesional','sede','faq','convocatoria','tramite_tipo','dataset') NOT NULL,
  entidad_id      BIGINT UNSIGNED NOT NULL,
  titulo          VARCHAR(500) NOT NULL,
  resumen         VARCHAR(1000) NULL,
  contenido       MEDIUMTEXT   NULL,
  url             VARCHAR(500) NOT NULL,
  peso            SMALLINT     NOT NULL DEFAULT 0,
  actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_indice (tipo, entidad_id),
  FULLTEXT KEY ft_indice (titulo, resumen, contenido)
) ENGINE=InnoDB;

-- Registro de búsquedas sin resultado. Insumo para FAQ y contenido nuevo.
CREATE TABLE busquedas_log (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  termino         VARCHAR(255) NOT NULL,
  resultados      INT UNSIGNED NOT NULL DEFAULT 0,
  creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_busquedas_termino (termino),
  KEY idx_busquedas_sin_resultado (resultados, creado_en)
) ENGINE=InnoDB;

-- =====================================================================
-- 10. IA EN EL PANEL (Fase 8, opcional y desactivable)
-- Registro de uso y consumo. Proveedor y modelo viven en `configuracion`
-- (ia.activada, ia.proveedor, ia.modelo); las claves de API en .env:
-- son credenciales de infraestructura, nunca contenido editable.
-- =====================================================================

CREATE TABLE ia_usos (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  funcion         ENUM('resumen_documento','sugerencia_etiquetas','texto_alternativo','metadatos_seo','borrador_noticia') NOT NULL,
  proveedor       VARCHAR(40)  NOT NULL,
  modelo          VARCHAR(80)  NOT NULL,
  tokens_entrada  INT UNSIGNED NOT NULL DEFAULT 0,
  tokens_salida   INT UNSIGNED NOT NULL DEFAULT 0,
  duracion_ms     INT UNSIGNED NOT NULL DEFAULT 0,
  exito           TINYINT(1)   NOT NULL DEFAULT 1,
  error           VARCHAR(255) NULL,
  usuario_id      BIGINT UNSIGNED NULL,
  creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_iausos_fecha (creado_en),
  KEY idx_iausos_funcion (funcion, creado_en),
  CONSTRAINT fk_iausos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- SEMILLAS REQUERIDAS (implementar como seeders)
-- =====================================================================
-- 1. roles: SuperAdmin, Comunicaciones, Atencion al Usuario, Juridica,
--    Planeacion, Talento Humano, Consulta
-- 2. dependencias: según organigrama real del hospital
-- 3. transparencia_secciones: estructura del Anexo 2 Res. 1519 de 2020
--    (VERIFICAR la versión vigente del anexo antes de sembrar)
-- 4. tipos_tramite + tramite_estados + tramite_campos:
--    - PQRSDF: peticion general (15 h), peticion de informacion (10 h),
--      consulta (30 h), queja, reclamo, sugerencia, denuncia, felicitacion
--      VALIDAR los términos con la oficina jurídica antes de sembrar
--    - Solicitud de cita
--    - Solicitud de historia clínica
--    - Solicitud de certificados
--    - Habeas data (supresión, actualización, copia, revocación)
-- 5. festivos: calendario colombiano del año en curso y el siguiente
-- 6. menus: principal, superior (Transparencia, Atencion a la Ciudadania,
--    Participa, Noticias), pie de página según Res. 1519
-- 7. configuracion: nombre, NIT, direcciones, teléfonos, horarios,
--    correo institucional, redes sociales, colores institucionales
-- 8. politicas: tratamiento de datos, cookies, aviso de privacidad

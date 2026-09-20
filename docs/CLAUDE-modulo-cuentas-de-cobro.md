# MÓDULO DE CUENTAS DE COBRO — E.S.E. HOSPITAL ROSARIO PUMAREJO DE LÓPEZ

> System prompt para el asistente de código. Versión 1.0 — 2026-09-20.
> Este documento es la fuente de verdad del módulo. Si algo aquí contradice el código existente de la plataforma, pregunta antes de decidir; si algo no está definido aquí, pregunta antes de inventarlo.

---

## 0. Cómo usar este documento

Este archivo describe un módulo nuevo que se integra en la plataforma institucional del hospital (sitio web + panel administrativo, PHP 8.2 / MySQL 8 / TailwindCSS / JavaScript vanilla, sin frameworks). Lee primero el `CLAUDE.md` raíz de la plataforma y su esquema MySQL: el módulo REUTILIZA las tablas de usuarios, roles, auditoría y notificaciones que ya existen. Antes de crear cualquier tabla de la sección 6, verifica qué existe ya y adapta las claves foráneas a los nombres reales.

Construye por fases (sección 12), en orden. Al terminar cada fase, ejecuta los criterios de aceptación de esa fase y repórtalos antes de continuar.

---

## 1. Problema que resuelve

Cada mes, cada contratista de prestación de servicios del hospital debe "legalizar" su cuenta de cobro entregando al supervisor un paquete de tres documentos más anexos:

1. **Cuenta de cobro** — formato GF-FR-002 v001 (hoy en Excel). La firma el contratista.
2. **Informe de actividades** — hoy en Word, sin código SGC. Lo firma el contratista.
3. **Informe de supervisión contractual** — formato GJ-FR-004 v002 (hoy en Word). Lo firma el supervisor y autoriza el pago.

Anexos: planilla PILA del mes, RUT, cédula, copia del contrato, CDP, RP, acta de inicio, paz y salvo (último pago), impuestos departamentales cuando aplique.

Hoy los tres documentos se llenan a mano, copiando y pegando. Los mismos datos del contrato (número, objeto, valor, plazo, fechas, supervisor, CDP/RP, obligaciones, cuenta bancaria) se transcriben tres veces cada mes. En una muestra de documentos reales se encontraron: NIT del hospital escrito de dos formas, dos fechas de terminación distintas para el mismo contrato, "Veintisiete días (16)", periodos que cambian de un párrafo al siguiente, valores en letras mal escritos, columnas corridas en tablas de obligaciones y listas de obligaciones que no coinciden entre el informe del contratista y el del supervisor.

**El módulo elimina la transcripción**: el contrato se carga UNA vez; el contratista solo describe qué hizo en el periodo y reporta su planilla; el sistema calcula todo lo demás y genera los tres PDF con los formatos institucionales exactos.

---

## 2. Usuarios y sus condiciones

**Contratistas** (prestación de servicios, personas naturales; el personal de planta NO usa este módulo). Muchos son adultos mayores con poca experiencia tecnológica. Todo el portal del contratista se diseña para ellos:

- Una tarea por pantalla. Un botón principal grande por pantalla ("Continuar", "Enviar").
- Tipografía mínima 18 px en cuerpo, 22 px en títulos; botones de al menos 56 px de alto; contraste AA como mínimo.
- Lenguaje cotidiano, sin jerga: "Sus datos", "Qué hizo este mes", "Su planilla de seguridad social", "Revisar y enviar". Nunca "imputación presupuestal", "IBC", "CDP" sin explicación al lado.
- Nada de contraseñas complejas. Nada de menús anidados. Sin modales sorpresa.
- Todo lo que el sistema pueda calcular o recordar, lo calcula o recuerda. El contratista escribe lo mínimo.
- Debe funcionar en un celular Android gama baja con Chrome y en un PC viejo.
- Guardado automático de borrador en cada paso: si cierran el navegador, al volver retoman donde iban.

**Supervisores**: empleados del hospital (profesionales universitarios, jefes de área). Usan el panel administrativo con su usuario de la plataforma. Revisan, marcan cumplimiento, firman y descargan el paquete.

**Administrador del sistema**: carga y activa contratos, registra el RP, gestiona contratistas y supervisores, parámetros anuales, plantillas y reportes.

**Fuera de alcance**: lo que pasa con el paquete después del supervisor (Financiera, Tesorería, pago). El flujo termina cuando el supervisor aprueba y el paquete PDF queda generado.

---

## 3. Reglas de ingeniería (heredadas de la plataforma, reforzadas aquí)

- PHP 8.2+, `declare(strict_types=1)`, PDO con consultas preparadas siempre, MySQL 8 con InnoDB y `utf8mb4_unicode_ci`.
- Sin frameworks PHP ni JS. Se permiten librerías puntuales vía Composer: **Dompdf** (PDF), **PHPMailer** (correo). Nada más sin preguntar.
- TailwindCSS para el panel y el portal. JavaScript vanilla, módulos ES, sin jQuery.
- CSRF en todo formulario. Rate limit en login/OTP. Cabeceras de seguridad ya definidas en la plataforma.
- Toda acción relevante escribe en la tabla de auditoría de la plataforma: quién, qué, cuándo, desde qué IP, valores antes/después cuando aplique.
- Dinero: `DECIMAL(14,2)` en base de datos, enteros en centavos o `bcmath` en PHP; nunca `float` para sumar dinero. En pantalla y PDF: `$ 2.350.000` (separador de miles punto, sin decimales salvo que existan).
- Fechas: `DATE`/`DATETIME` en MySQL, zona horaria `America/Bogota`. En PDF, formato largo en español: "28 de enero de 2026".
- Archivos subidos: fuera del document root, nombre aleatorio, extensión validada por contenido (finfo), máximo 10 MB por archivo, solo PDF/JPG/PNG. Se sirven a través de un controlador que verifica permisos.
- Datos personales (Ley 1581 de 2012): el contratista solo ve sus propios datos; el supervisor solo los de los contratos que supervisa; el administrador todo. Aviso de privacidad y autorización de tratamiento en el primer ingreso del contratista (checkbox obligatorio con texto fijo, se guarda fecha/IP de aceptación).
- Accesibilidad WCAG 2.1 AA en el portal del contratista: navegación por teclado, `label` en todo campo, mensajes de error asociados con `aria-describedby`, foco visible, sin depender solo del color.
- Código y comentarios en español. Nombres de tablas y columnas en español, snake_case, prefijo `cc_` para todas las tablas del módulo.

---

## 4. Conceptos y vocabulario

| Término | Significado en el módulo |
|---|---|
| Contrato | Contrato de prestación de servicios entre el hospital y una persona natural. Tiene un cronograma de pagos (cuotas). |
| Cuota | Cada pago pactado en la cláusula "Forma de pago" del contrato. Tiene número, periodo (desde/hasta) y valor. |
| Cuenta de cobro | Solicitud de pago de una cuota. Una cuenta = una cuota. Genera el paquete de tres PDF. Consecutivo por contrato (N° 1, 2, 3…). |
| Periodo | Rango de fechas que cubre la cuenta. Sale del cronograma de pagos. |
| Obligación | Cada obligación específica numerada del contrato. El contratista describe su ejecución por obligación; el supervisor marca Sí/No/Parcial. |
| CDP | Certificado de Disponibilidad Presupuestal. Número, fecha, rubro, valor. Viene en la minuta. |
| RP | Registro Presupuestal. Se expide después de la firma; lo registra el administrador. |
| IBC | Ingreso Base de Cotización a seguridad social. Ver reglas en sección 8. |
| PILA | Planilla Integrada de Liquidación de Aportes. El contratista reporta el número de planilla y los valores de salud, pensión y ARL. |
| Paquete | Los tres PDF (cuenta de cobro, informe de actividades, informe de supervisión) más los anexos, combinados en un solo PDF y también disponibles por separado. |

---

## 5. Flujo de estados de una cuenta de cobro

```
BORRADOR ──(contratista envía)──▶ ENVIADA ──(supervisor aprueba)──▶ APROBADA
    ▲                                 │
    └────────(supervisor devuelve)────┘  → estado DEVUELTA (vuelve a editarse; al reenviar pasa a ENVIADA)

APROBADA ──(marca manual opcional del supervisor o admin)──▶ ENTREGADA_FINANCIERA
Cualquier estado ──(admin, con motivo)──▶ ANULADA
```

- `BORRADOR`: solo el contratista la ve y edita. Se crea automáticamente al entrar al paso "Qué hizo este mes".
- `ENVIADA`: el contratista ya no edita. El supervisor recibe notificación. Se generan y guardan PDF de cuenta de cobro e informe de actividades con la firma del contratista.
- `DEVUELTA`: el supervisor escribió una observación. El contratista ve la observación en su pantalla de inicio en lenguaje claro y puede corregir y reenviar. Cada devolución queda en el historial.
- `APROBADA`: el supervisor marcó cumplimiento, el sistema generó el informe de supervisión con su firma y el paquete completo. Inmutable. Cuenta para "pagos anteriores acumulados" de las siguientes cuentas.
- `ENTREGADA_FINANCIERA`: marca informativa. No cambia nada más.
- `ANULADA`: no cuenta para consecutivos ni acumulados. Requiere motivo. Solo administrador.

Cada transición escribe una fila en `cc_cuenta_historial` con usuario, fecha, estado anterior, estado nuevo y observación.

---

## 6. Modelo de datos

Ajusta `usuarios(id)` al nombre real de la tabla de usuarios de la plataforma. Todas las tablas: `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`, con `creado_en DATETIME`, `actualizado_en DATETIME` y `creado_por`/`actualizado_por` (FK a usuarios) salvo donde no aplique.

```sql
-- Contratistas (personas naturales). Uno por cédula. Puede tener varios contratos en el tiempo.
CREATE TABLE cc_contratistas (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id            INT UNSIGNED NULL,                 -- FK usuarios; se crea al primer ingreso
  tipo_documento        ENUM('CC','CE','PPT','NIT') NOT NULL DEFAULT 'CC',
  numero_documento      VARCHAR(20) NOT NULL UNIQUE,
  lugar_expedicion      VARCHAR(80) NULL,                  -- "Valledupar"
  nombres               VARCHAR(120) NOT NULL,
  apellidos             VARCHAR(120) NOT NULL,
  nombre_completo       VARCHAR(240) GENERATED ALWAYS AS (CONCAT(nombres,' ',apellidos)) STORED,
  direccion             VARCHAR(200) NULL,
  telefono              VARCHAR(30) NULL,
  celular               VARCHAR(30) NULL,                  -- para OTP por WhatsApp/SMS
  correo                VARCHAR(150) NULL,
  banco                 VARCHAR(100) NULL,
  tipo_cuenta           ENUM('AHORROS','CORRIENTE') NULL,
  numero_cuenta         VARCHAR(40) NULL,
  clase_riesgo_arl      TINYINT UNSIGNED NULL,             -- 1..5
  firma_archivo         VARCHAR(255) NULL,                 -- imagen de firma escaneada (PNG/JPG)
  firma_cargada_en      DATETIME NULL,
  rut_archivo           VARCHAR(255) NULL,
  cedula_archivo        VARCHAR(255) NULL,
  certificacion_bancaria_archivo VARCHAR(255) NULL,
  datos_completos       TINYINT(1) NOT NULL DEFAULT 0,     -- 1 cuando llenó contacto, banco, firma y anexos fijos
  acepto_tratamiento_datos_en DATETIME NULL,
  acepto_tratamiento_datos_ip VARCHAR(45) NULL,
  pin_hash              VARCHAR(255) NULL,                 -- PIN de 4-6 dígitos (opcional, ver sección 9)
  activo                TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Supervisores: empleados con usuario en la plataforma.
CREATE TABLE cc_supervisores (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id      INT UNSIGNED NOT NULL UNIQUE,            -- FK usuarios
  nombre_completo VARCHAR(240) NOT NULL,                   -- como debe aparecer en el PDF
  cargo           VARCHAR(160) NOT NULL,                   -- "PROFESIONAL UNIVERSITARIO (FACTURACIÓN)"
  dependencia     VARCHAR(160) NULL,
  firma_archivo   VARCHAR(255) NULL,
  activo          TINYINT(1) NOT NULL DEFAULT 1
);

-- Contratos.
CREATE TABLE cc_contratos (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  numero                VARCHAR(20) NOT NULL UNIQUE,        -- "1407-2026"
  contratista_id        INT UNSIGNED NOT NULL,              -- FK cc_contratistas
  supervisor_id         INT UNSIGNED NULL,                  -- FK cc_supervisores (se asigna al activar)
  cargo_supervisor_minuta VARCHAR(160) NULL,                -- como viene en la minuta, solo el cargo
  tipo_contrato         VARCHAR(120) NOT NULL DEFAULT 'PRESTACIÓN DE SERVICIOS',
  ciudad_suscripcion    VARCHAR(80) NOT NULL DEFAULT 'Valledupar',
  fecha_suscripcion     DATE NOT NULL,
  objeto                TEXT NOT NULL,
  valor_total           DECIMAL(14,2) NOT NULL,             -- valor inicial sin adiciones
  valor_total_letras    VARCHAR(400) NOT NULL,              -- generado por el sistema, editable
  plazo_texto           VARCHAR(120) NOT NULL,              -- "CINCO (5) MESES", "25 DÍAS Y 5 MESES"
  plazo_meses           TINYINT UNSIGNED NOT NULL DEFAULT 0,
  plazo_dias            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  modalidad_periodos    ENUM('CALENDARIO','ANIVERSARIO') NOT NULL DEFAULT 'CALENDARIO', -- ver sección 8.1
  fecha_acta_inicio     DATE NULL,                          -- la registra el supervisor
  fecha_terminacion     DATE NULL,                          -- calculada al registrar acta; editable por admin
  garantias             TINYINT(1) NOT NULL DEFAULT 0,
  seccion_ubicacion     VARCHAR(160) NULL,                  -- "AUTORIZADORA URGENCIAS" (para GF-FR-002)
  forma_pago_texto      TEXT NULL,                          -- cláusula 3 textual, referencia
  requisitos_pago_texto TEXT NULL,                          -- literales a), b), c), d) de la cláusula 3
  minuta_archivo        VARCHAR(255) NULL,                  -- PDF escaneado
  estado                ENUM('EN_REVISION','ACTIVO','SUSPENDIDO','TERMINADO','LIQUIDADO','ANULADO') NOT NULL DEFAULT 'EN_REVISION',
  activado_en           DATETIME NULL,
  activado_por          INT UNSIGNED NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (contratista_id), INDEX (supervisor_id), INDEX (estado)
);

-- Imputación presupuestal: CDP y RP (uno o varios de cada uno; las adiciones traen los suyos).
CREATE TABLE cc_contrato_imputaciones (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contrato_id   INT UNSIGNED NOT NULL,
  tipo          ENUM('CDP','RP') NOT NULL,
  numero        VARCHAR(30) NOT NULL,                       -- "1611", "2055"
  fecha         DATE NOT NULL,
  codigo_rubro  VARCHAR(40) NOT NULL,                       -- "2.1.2.02.02.008-83118"
  nombre_rubro  VARCHAR(200) NOT NULL,                      -- "SERVICIOS DE GESTIÓN Y ADMINISTRACIÓN EMPRESARIAL (OFICINA CENTRAL)"
  recurso       VARCHAR(120) NULL,                          -- "1.2.3.2.09 VENTA DE BIENES Y SERVICIOS"
  valor         DECIMAL(14,2) NOT NULL,
  novedad_id    INT UNSIGNED NULL,                          -- FK cc_contrato_novedades si pertenece a una adición
  INDEX (contrato_id)
);

-- Obligaciones específicas del contrato, en el orden de la minuta.
CREATE TABLE cc_contrato_obligaciones (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contrato_id   INT UNSIGNED NOT NULL,
  orden         TINYINT UNSIGNED NOT NULL,
  grupo         ENUM('ESPECIFICA','SGIC') NOT NULL DEFAULT 'ESPECIFICA',
  texto         TEXT NOT NULL,
  activa        TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE (contrato_id, grupo, orden)
);

-- Cronograma de pagos generado desde la cláusula "Forma de pago".
CREATE TABLE cc_contrato_pagos (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contrato_id   INT UNSIGNED NOT NULL,
  numero_cuota  TINYINT UNSIGNED NOT NULL,
  descripcion   VARCHAR(120) NOT NULL,                      -- "25 días de julio de 2026", "Agosto de 2026"
  periodo_desde DATE NULL,                                  -- se llenan al registrar el acta de inicio
  periodo_hasta DATE NULL,
  dias          SMALLINT UNSIGNED NULL,
  valor         DECIMAL(14,2) NOT NULL,
  UNIQUE (contrato_id, numero_cuota)
);

-- Adiciones, prórrogas, suspensiones, reinicios, cesiones, terminación anticipada.
CREATE TABLE cc_contrato_novedades (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contrato_id   INT UNSIGNED NOT NULL,
  tipo          ENUM('ADICION','PRORROGA','SUSPENSION','REINICIO','TERMINACION_ANTICIPADA','OTRA') NOT NULL,
  fecha         DATE NOT NULL,
  fecha_fin     DATE NULL,                                  -- para suspensiones
  valor         DECIMAL(14,2) NULL,                         -- para adiciones
  plazo_meses   TINYINT UNSIGNED NULL,
  plazo_dias    SMALLINT UNSIGNED NULL,
  descripcion   VARCHAR(400) NULL,
  documento_archivo VARCHAR(255) NULL,
  creado_por    INT UNSIGNED NOT NULL,
  creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (contrato_id)
);

-- Cuentas de cobro (una por cuota).
CREATE TABLE cc_cuentas (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contrato_id           INT UNSIGNED NOT NULL,
  pago_id               INT UNSIGNED NOT NULL,              -- FK cc_contrato_pagos (la cuota)
  consecutivo           TINYINT UNSIGNED NOT NULL,          -- N° de cuenta e informe (= número de cuota salvo anulaciones)
  radicado              VARCHAR(30) NOT NULL UNIQUE,        -- "CC-2026-000123"
  periodo_desde         DATE NOT NULL,
  periodo_hasta         DATE NOT NULL,
  dias_periodo          SMALLINT UNSIGNED NOT NULL,
  fecha_cobro           DATE NOT NULL,                      -- por defecto = periodo_hasta; editable
  valor                 DECIMAL(14,2) NOT NULL,
  valor_letras          VARCHAR(400) NOT NULL,
  pagos_anteriores      DECIMAL(14,2) NOT NULL DEFAULT 0,   -- suma de cuentas APROBADAS anteriores del contrato (congelado al aprobar)
  saldo_por_ejecutar    DECIMAL(14,2) NOT NULL DEFAULT 0,   -- congelado al aprobar
  estado                ENUM('BORRADOR','ENVIADA','DEVUELTA','APROBADA','ENTREGADA_FINANCIERA','ANULADA') NOT NULL DEFAULT 'BORRADOR',
  enviada_en            DATETIME NULL,
  aprobada_en           DATETIME NULL,
  aprobada_por          INT UNSIGNED NULL,                  -- FK cc_supervisores
  fecha_informe_supervision DATE NULL,                      -- "Dado en Valledupar a los X días de…"
  observacion_supervisor TEXT NULL,
  pdf_cuenta_cobro      VARCHAR(255) NULL,
  pdf_informe_actividades VARCHAR(255) NULL,
  pdf_informe_supervision VARCHAR(255) NULL,
  pdf_paquete           VARCHAR(255) NULL,                  -- todo combinado + anexos
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE (contrato_id, consecutivo),
  INDEX (estado), INDEX (contrato_id, estado)
);

-- Ejecución por obligación (lo escribe el contratista) y cumplimiento (lo marca el supervisor).
CREATE TABLE cc_cuenta_actividades (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cuenta_id       INT UNSIGNED NOT NULL,
  obligacion_id   INT UNSIGNED NOT NULL,
  descripcion_ejecucion TEXT NULL,
  cumplimiento    ENUM('SI','NO','PARCIAL') NULL,           -- lo llena el supervisor; por defecto SI al abrir
  UNIQUE (cuenta_id, obligacion_id)
);

-- Seguridad social del periodo.
CREATE TABLE cc_cuenta_seguridad_social (
  cuenta_id         INT UNSIGNED PRIMARY KEY,
  numero_planilla   VARCHAR(30) NOT NULL,
  mes_salud         CHAR(7) NOT NULL,                       -- "2026-06"
  mes_pension       CHAR(7) NOT NULL,
  ibc               DECIMAL(14,2) NOT NULL,
  aporte_salud      DECIMAL(14,2) NOT NULL,
  aporte_pension    DECIMAL(14,2) NOT NULL,
  aporte_fsp        DECIMAL(14,2) NOT NULL DEFAULT 0,       -- fondo de solidaridad pensional
  aporte_arl        DECIMAL(14,2) NOT NULL,
  tarifa_arl        DECIMAL(6,5) NOT NULL,                  -- 0.02436
  aporte_sena       DECIMAL(14,2) NOT NULL DEFAULT 0,
  aporte_icbf       DECIMAL(14,2) NOT NULL DEFAULT 0,
  aporte_ccf        DECIMAL(14,2) NOT NULL DEFAULT 0,
  total_aportes     DECIMAL(14,2) NOT NULL,
  valores_sugeridos_json JSON NULL                          -- lo que el sistema calculó, para comparar con lo reportado
);

-- Anexos de la cuenta.
CREATE TABLE cc_cuenta_adjuntos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cuenta_id   INT UNSIGNED NOT NULL,
  tipo        ENUM('PLANILLA_PILA','PAZ_Y_SALVO','IMPUESTOS_DEPARTAMENTALES','OTRO') NOT NULL,
  nombre_original VARCHAR(255) NOT NULL,
  archivo     VARCHAR(255) NOT NULL,
  mime        VARCHAR(80) NOT NULL,
  tamano      INT UNSIGNED NOT NULL,
  subido_por  INT UNSIGNED NOT NULL,
  subido_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (cuenta_id)
);

-- Historial de estados (auditoría propia del flujo, además de la auditoría general).
CREATE TABLE cc_cuenta_historial (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cuenta_id       INT UNSIGNED NOT NULL,
  estado_anterior VARCHAR(30) NULL,
  estado_nuevo    VARCHAR(30) NOT NULL,
  usuario_id      INT UNSIGNED NOT NULL,
  rol             ENUM('CONTRATISTA','SUPERVISOR','ADMIN','SISTEMA') NOT NULL,
  observacion     TEXT NULL,
  ip              VARCHAR(45) NULL,
  creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (cuenta_id)
);

-- Evidencia de firma (firma electrónica simple: quién, cuándo, desde dónde, sobre qué hash).
CREATE TABLE cc_cuenta_firmas (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cuenta_id     INT UNSIGNED NOT NULL,
  documento     ENUM('CUENTA_COBRO','INFORME_ACTIVIDADES','INFORME_SUPERVISION') NOT NULL,
  firmante_rol  ENUM('CONTRATISTA','SUPERVISOR') NOT NULL,
  usuario_id    INT UNSIGNED NOT NULL,
  firma_archivo VARCHAR(255) NOT NULL,                      -- imagen usada
  hash_pdf      CHAR(64) NOT NULL,                          -- SHA-256 del PDF generado
  ip            VARCHAR(45) NOT NULL,
  user_agent    VARCHAR(300) NULL,
  firmado_en    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (cuenta_id)
);

-- Parámetros por año.
CREATE TABLE cc_parametros_anuales (
  anio                  SMALLINT UNSIGNED PRIMARY KEY,
  smlv                  DECIMAL(14,2) NOT NULL,             -- 2026: 1750905.00
  porcentaje_ibc        DECIMAL(5,4) NOT NULL DEFAULT 0.4000,
  tarifa_salud          DECIMAL(5,4) NOT NULL DEFAULT 0.1250,
  tarifa_pension        DECIMAL(5,4) NOT NULL DEFAULT 0.1600,
  redondeo_aportes      INT UNSIGNED NOT NULL DEFAULT 100   -- PILA redondea al múltiplo de 100 superior
);

CREATE TABLE cc_arl_clases (
  clase   TINYINT UNSIGNED PRIMARY KEY,
  tarifa  DECIMAL(6,5) NOT NULL,
  descripcion VARCHAR(80) NOT NULL
);
INSERT INTO cc_arl_clases VALUES
 (1,0.00522,'Riesgo mínimo'),(2,0.01044,'Riesgo bajo'),(3,0.02436,'Riesgo medio'),
 (4,0.04350,'Riesgo alto'),(5,0.06960,'Riesgo máximo');

INSERT INTO cc_parametros_anuales (anio, smlv) VALUES (2026, 1750905.00);

-- Códigos de verificación para el ingreso del contratista.
CREATE TABLE cc_otp (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contratista_id INT UNSIGNED NOT NULL,
  canal          ENUM('WHATSAPP','SMS','CORREO') NOT NULL,
  codigo_hash    VARCHAR(255) NOT NULL,                     -- 6 dígitos, hash
  expira_en      DATETIME NOT NULL,                         -- 10 minutos
  usado_en       DATETIME NULL,
  intentos       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ip             VARCHAR(45) NOT NULL,
  creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (contratista_id, expira_en)
);

-- Plantillas de documentos (código SGC, versión y fecha van en el encabezado del PDF).
CREATE TABLE cc_plantillas (
  clave        VARCHAR(40) PRIMARY KEY,                     -- 'CUENTA_COBRO','INFORME_ACTIVIDADES','INFORME_SUPERVISION'
  nombre       VARCHAR(120) NOT NULL,
  codigo_sgc   VARCHAR(30) NULL,                            -- 'GF-FR-002', 'GJ-FR-004'
  version_sgc  VARCHAR(10) NULL,                            -- '001', '002'
  fecha_sgc    DATE NULL,
  proceso      VARCHAR(80) NULL,                            -- 'Gestión Financiera (GF)', 'Gestión Jurídica'
  archivo_html VARCHAR(255) NOT NULL,                       -- ruta de la plantilla en /templates/pdf/
  activa       TINYINT(1) NOT NULL DEFAULT 1
);

-- Trabajos de extracción asistida por IA de minutas.
CREATE TABLE cc_extracciones (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  minuta_archivo VARCHAR(255) NOT NULL,
  contrato_id    INT UNSIGNED NULL,                         -- se llena al crear el contrato desde la extracción
  estado         ENUM('PENDIENTE','PROCESANDO','LISTA','ERROR','APLICADA') NOT NULL DEFAULT 'PENDIENTE',
  proveedor      VARCHAR(40) NULL,
  modelo         VARCHAR(80) NULL,
  resultado_json JSON NULL,                                 -- esquema de la sección 10
  alertas_json   JSON NULL,                                 -- inconsistencias detectadas
  error          TEXT NULL,
  creado_por     INT UNSIGNED NOT NULL,
  creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  terminado_en   DATETIME NULL
);
```

Claves foráneas: agrégalas todas con `ON DELETE RESTRICT`. Nada se borra físicamente: contratos y cuentas se anulan; contratistas y supervisores se desactivan.

---

## 7. Pantallas

### 7.1 Portal del contratista (`/cuentas/`)

Ruta pública separada del panel administrativo, con su propio layout minimalista: logo del hospital, título grande, contenido, un botón principal. Sin menú lateral. Barra de progreso "Paso 2 de 4" en los pasos del formulario.

**P0. Ingreso** (`/cuentas/ingreso`)
- Campo "Número de cédula" (numérico, teclado numérico en móvil).
- Campo "Número de contrato" (texto, ejemplo visible "1407-2026").
- Botón "Continuar".
- Si la pareja cédula + contrato existe y el contrato está ACTIVO: se envía un código de 6 dígitos al celular registrado (WhatsApp si la plataforma lo tiene, si no SMS, si no correo) y se muestra la pantalla "Escriba el código que le llegó a su celular terminado en ···1234". Reenviar código después de 60 segundos. 5 intentos máximo, luego bloqueo de 15 minutos.
- Si el contratista no tiene celular ni correo registrados (primer ingreso de un contrato recién activado): se le permite entrar SOLO con cédula + contrato + fecha de suscripción del contrato (los tres datos están en su copia del contrato) y lo primero que hace es registrar celular y correo; a partir de ahí, OTP siempre.
- Opcional (parámetro del sistema): PIN de 4 a 6 dígitos que el contratista define una vez y usa en lugar del OTP. Si se activa, el OTP queda como "¿Olvidó su PIN?".
- Mensajes de error en lenguaje claro: "No encontramos un contrato con esos datos. Revise el número de contrato en su copia o comuníquese con la oficina de Contratación al (teléfono)".

**P1. Inicio del contratista** (`/cuentas/inicio`)
- Saludo con nombre. Un solo bloque grande con el estado actual:
  - Si tiene una cuenta DEVUELTA: "Su cuenta N° 3 fue devuelta. Motivo: …" y botón "Corregir y reenviar".
  - Si tiene una cuenta en BORRADOR: "Tiene una cuenta sin terminar" y botón "Continuar".
  - Si tiene una cuenta ENVIADA: "Su cuenta N° 3 está en revisión del supervisor desde el 5 de julio" sin botón de acción.
  - Si la siguiente cuota ya puede cobrarse (periodo_hasta ≤ hoy + 5 días): botón "Presentar cuenta de cobro N° 4 — Periodo del 1 al 31 de agosto".
  - Si no hay nada pendiente: "No tiene cuentas pendientes. La próxima podrá presentarse desde el 27 de agosto".
- Debajo, lista simple de cuentas anteriores con estado y botón "Ver PDF" por cada una.
- Enlace pequeño "Mis datos" (para actualizar teléfono, correo, banco, firma).

**P1a. Primer ingreso — Mis datos** (se muestra obligatoriamente si `datos_completos = 0`)
- Formulario en tres pantallas cortas: (1) celular, correo, dirección; (2) banco, tipo de cuenta, número de cuenta, subir certificación bancaria; (3) subir foto de la cédula, subir RUT, subir imagen de la firma (o dibujarla en pantalla con el dedo y guardarla como PNG; ambas opciones). Clase de riesgo ARL con selector explicado ("Su ARL le indica su clase de riesgo; la mayoría de contratos administrativos son clase 1; los técnicos de mantenimiento suelen ser clase 3").
- Checkbox obligatorio con el aviso de privacidad y autorización de tratamiento de datos (texto fijo entregado por la oficina jurídica; usar un placeholder claro si aún no existe). Se guarda fecha e IP.

**P2. Sus datos del contrato** (`/cuentas/nueva/1`)
- Solo lectura, en tarjetas grandes: contrato N°, objeto (resumido a 200 caracteres con "ver completo"), valor total, valor de esta cuota, periodo de esta cuenta, supervisor, CDP/RP.
- Pregunta: "¿Estos datos están correctos?" Botón principal "Sí, continuar". Botón secundario "Algo está mal" que abre un cuadro de texto y envía un aviso al administrador (correo + notificación en panel) sin bloquear al contratista.

**P3. Qué hizo este mes** (`/cuentas/nueva/2`)
- Encabezado: "Cuenta de cobro N° 4 — Periodo del 1 al 31 de agosto de 2026".
- Una tarjeta por obligación específica del contrato: número, texto de la obligación (tamaño normal), y debajo un `textarea` grande con la etiqueta "Qué hizo usted para cumplirla". Precargado con el texto de la cuenta anterior aprobada del mismo contrato si existe, con un aviso "Copiamos lo del mes pasado; cámbielo si hizo algo diferente". Botón "Limpiar" por tarjeta.
- Validación: mínimo 20 caracteres por obligación. Si dejan una vacía se marca en amarillo y se pide confirmación: "Dejó la obligación 5 sin descripción. ¿Desea continuar así?" (permitido, el supervisor lo verá).
- Las obligaciones del grupo SGIC no se muestran aquí (no se reportan mes a mes en los informes actuales).
- Guardado automático cada 10 segundos y al salir de cada campo.

**P4. Su planilla de seguridad social** (`/cuentas/nueva/3`)
- Explicación de una línea: "Escriba los datos de la planilla PILA que pagó para este periodo".
- Campos: número de planilla; mes de salud y mes de pensión (selectores de mes, por defecto el mes anterior al periodo_hasta, ver sección 8.3); IBC; aporte salud; aporte pensión; aporte ARL. Los cuatro valores vienen precalculados (sección 8.3) y marcados como "valor sugerido — corríjalo si su planilla dice otra cosa". Fondo de solidaridad y parafiscales ocultos salvo que el sistema los calcule distintos de cero.
- Subir la planilla (foto o PDF). Obligatorio.
- Subir paz y salvo e impuestos departamentales si el administrador los marcó como requeridos para este contrato (parámetro por contrato, por defecto no).

**P5. Revisar y enviar** (`/cuentas/nueva/4`)
- Vista previa de la cuenta de cobro y del informe de actividades ya generados (PDF embebido o imágenes de las páginas, según lo que renderice mejor en móvil; probar ambas).
- Bloque "Al enviar, usted firma estos documentos con su firma registrada" con la imagen de la firma. Checkbox "Confirmo que la información es verdadera y que las actividades corresponden a lo realizado en el periodo".
- Botón "Enviar al supervisor". Al enviar: estado ENVIADA, PDF definitivos con firma y hash, fila en `cc_cuenta_firmas`, notificación al supervisor.

**P6. Enviada** (`/cuentas/enviada/{id}`)
- "Su cuenta N° 4 fue enviada a KENNYS MELGAREJO ZULETA. Radicado CC-2026-000123."
- Botones grandes: "Descargar PDF", "Imprimir", "Enviar a mi correo", "Enviar por WhatsApp" (enlace `wa.me` con texto y enlace de descarga protegido por token de un solo uso con vigencia de 7 días; si la plataforma tiene integración de WhatsApp, enviar el archivo directamente).
- "Volver al inicio".

### 7.2 Panel del supervisor (dentro del panel administrativo, menú "Cuentas de cobro")

**S1. Bandeja**: tabla con las cuentas ENVIADAS de sus contratos, ordenadas por fecha de envío. Columnas: radicado, contratista, contrato, N°, periodo, valor, días en espera. Filtros por estado y contrato. Pestañas: Pendientes / Devueltas / Aprobadas / Todas.

**S2. Revisión de cuenta** (`/admin/cuentas/{id}`):
- Cabecera con datos del contrato y avance financiero calculado (valor inicial, adiciones, pagos anteriores acumulados, pago de esta cuenta, saldo por ejecutar).
- Tabla de obligaciones: número, texto, descripción del contratista, y radio Sí / No / Parcial (por defecto Sí). Si marca No o Parcial en alguna, se exige observación.
- Bloque de seguridad social con los valores reportados y, al lado, los sugeridos por el sistema; diferencias mayores al 5 % se resaltan.
- Visor de anexos (planilla, etc.).
- Vista previa del informe de supervisión GJ-FR-004 que se generará.
- Campo "Fecha del informe" (por defecto hoy).
- Botones: "Aprobar y firmar" (genera el informe de supervisión con la firma del supervisor, combina el paquete, estado APROBADA) y "Devolver al contratista" (exige observación; estado DEVUELTA; notificación al contratista por WhatsApp/SMS/correo con el motivo en lenguaje claro).

**S3. Cuenta aprobada**: descarga del paquete completo y de cada PDF; botones "Enviar por correo" (a una o varias direcciones) e "Imprimir"; marca opcional "Entregada a Financiera" con fecha.

**S4. Mis contratos**: lista de contratos que supervisa, con botón "Registrar acta de inicio" cuando falta (fecha + PDF del acta). Al registrarla, el sistema calcula fecha de terminación y periodos de las cuotas (sección 8.1) y muestra el cronograma resultante para confirmación.

### 7.3 Administración (menú "Cuentas de cobro → Administración")

**A1. Contratos**: lista con filtros (estado, supervisor, contratista, año). Acciones: nuevo desde minuta (A2), nuevo manual, ver, editar (solo EN_REVISION o con permiso especial y registro de auditoría), activar, registrar novedad, anular.

**A2. Cargar minuta**: subir PDF → se crea `cc_extracciones` → procesamiento (sección 10) → pantalla de revisión a dos columnas: izquierda las páginas escaneadas (imágenes, con zoom), derecha el formulario prellenado con los campos del contrato, la lista de obligaciones (editable, reordenable) y el cronograma de pagos generado. Las alertas de inconsistencia aparecen arriba en rojo. Botón "Guardar en revisión" y "Guardar y activar". Al activar se exige: contratista con cédula, supervisor asignado, al menos un CDP, cronograma cuya suma = valor total.

**A3. Registrar RP**: en la ficha del contrato, formulario número/fecha/valor/rubro; el rubro y el recurso se precargan desde el CDP.

**A4. Contratistas**: lista, ficha, reenviar código de ingreso, restablecer PIN, ver/reemplazar firma, desactivar.

**A5. Supervisores**: vincular usuario de la plataforma, nombre como va en el PDF, cargo, firma.

**A6. Parámetros**: SMLV por año, tarifas, redondeo, canal OTP preferido, activar PIN, textos legales fijos (sección 11) con control de versiones, requeridos de anexos por defecto.

**A7. Plantillas**: ver código/versión/fecha SGC de cada formato y previsualizar con datos de prueba.

**A8. Reportes**: cuentas por estado y por mes; tiempo promedio entre envío y aprobación por supervisor; contratos sin acta de inicio; contratos con cuotas vencidas sin cuenta presentada; exportación CSV.

---

## 8. Reglas de cálculo (implementar en una clase `CalculosCuenta` con pruebas unitarias)

### 8.1 Cronograma de pagos y periodos

La cláusula "Forma de pago" de la minuta se traduce a una lista de cuotas `[ {numero, descripcion, valor, tipo: 'COMPLETA'|'PARCIAL', mes?: 'YYYY-MM'} ]`. Casos reales observados:

- `CINCO (5) pagos por $2.850.000 al finalizar los meses de agosto, septiembre, octubre, noviembre y diciembre de 2026` → 5 cuotas completas, meses calendario.
- `UN primer pago de $583.333 correspondiente a los días del mes de julio y CINCO pagos de $3.500.000 (agosto a diciembre)` → cuota 1 parcial (julio, desde el acta de inicio hasta el 31), luego 5 completas.
- `SEIS pagos mensuales, el primero por los 25 días de julio ($3.333.333) y cinco de $4.000.000` → igual estructura.
- Contrato 1060-2026 (muestra): 6 cuotas de $2.350.000 con periodos "del 28 de enero al 27 de febrero", etc. → modalidad ANIVERSARIO.

Reglas:
- `modalidad_periodos = CALENDARIO`: cada cuota completa cubre del día 1 al último día del mes indicado. La cuota parcial inicial va de `fecha_acta_inicio` al último día de ese mes.
- `modalidad_periodos = ANIVERSARIO`: la cuota N cubre desde `fecha_acta_inicio + (N-1) meses` hasta `fecha_acta_inicio + N meses − 1 día`.
- La suma de todas las cuotas debe ser exactamente `valor_total` (+ adiciones si las hay). Si no cuadra, el contrato no se puede activar; se muestra la diferencia.
- `fecha_terminacion` = `periodo_hasta` de la última cuota. Si la minuta trae un plazo que no coincide con el cronograma (caso real: recuadro "4 meses", cláusula "2 meses", 4 pagos), se muestra alerta y el administrador decide.
- Los periodos se recalculan solo si aún no hay cuentas ENVIADAS/APROBADAS; si las hay, solo se recalculan las cuotas futuras.
- Suspensión: al registrar una suspensión con fecha_fin, las cuotas posteriores se desplazan y la cuota que contiene la suspensión se prorratea (parágrafo 2 de la cláusula 3 de la minuta). Implementar en fase 4; en fase 1 basta con bloquear la creación de cuentas mientras el contrato esté SUSPENDIDO.

### 8.2 Valor de la cuenta y prorrateo

- Valor de la cuenta = valor de la cuota. El contratista no lo edita.
- Prorrateo (terminación anticipada o cuota parcial no definida en la minuta): `valor_mensual / 30 × días`, redondeado al peso. Verificado con la muestra real: 2.350.000 / 30 × 10 = 783.333.
- `pagos_anteriores` = suma de `valor` de cuentas del mismo contrato en estado APROBADA con consecutivo menor. `saldo_por_ejecutar` = valor_total + adiciones − pagos_anteriores − valor. Ambos se congelan al aprobar.

### 8.3 Seguridad social (valores sugeridos)

- `ibc_sugerido = max(valor_mensual_cuota × porcentaje_ibc, smlv)`; para cuotas parciales usar el valor de la cuota completa del contrato. Con la muestra: 40 % × 2.350.000 = 940.000 < 1.750.905 → IBC = 1.750.905 (coincide con los documentos reales).
- `salud = redondear_arriba(ibc × 0.125, 100)` → 218.900 (coincide con la muestra del contrato 793-2026).
- `pension = redondear_arriba(ibc × 0.16, 100)` → 280.200 (coincide).
- `arl = redondear_arriba(ibc × tarifa_clase, 100)` → clase 3: 42.700 (coincide).
- `fsp` = 1 % del IBC si IBC ≥ 4 SMLV (más el escalonado si ≥ 16 SMLV; implementar solo el 1 % y documentar el resto).
- Parafiscales (SENA, ICBF, CCF) = 0 para independientes; los campos existen porque el formato los trae.
- `total_aportes = salud + pension + fsp + arl`.
- Mes de salud y pensión: por regla general el independiente cotiza mes vencido, así que para un periodo que termina en julio la planilla es de junio. Sugerir `mes(periodo_hasta) − 1` y dejar editar. Son valores SUGERIDOS: lo que vale es lo que dice la planilla del contratista; el sistema solo resalta diferencias > 5 % para el supervisor.

### 8.4 Números y fechas en letras

- Implementar `NumeroALetras::pesos(int|string $valor): string` → `"DOS MILLONES TRESCIENTOS CINCUENTA MIL PESOS M/CTE ($ 2.350.000)"`. Cubrir hasta miles de millones, con "UN MILLÓN" / "MILLONES", "VEINTIÚN", "CIENTO", "QUINIENTOS", "SETECIENTOS", "NOVECIENTOS", sin decimales salvo que existan ("… PESOS CON CINCUENTA CENTAVOS").
- `NumeroALetras::dia(int $d): string` → `"Veintisiete (27)"` para "Dado en Valledupar a los Veintisiete (27) días del mes de julio de 2026".
- `Fechas::larga(DateTime $f)` → `"28 de enero de 2026"`. `Fechas::periodo($desde, $hasta)` → `"del 28 de junio al 8 de julio de 2026"` (mismo año), `"del 28 de diciembre de 2026 al 27 de enero de 2027"` (años distintos).
- Pruebas unitarias con al menos 30 casos, incluidos 1.483.733, 29.303.733, 583.333, 14.100.000, 1.000.000, 21.000, 100, 0.

### 8.5 Consecutivos y radicado

- `consecutivo` de la cuenta = número de cuentas no ANULADAS del contrato + 1, asignado al crear el borrador; el N° de informe de actividades y el N° de informe de supervisión usan el mismo número.
- `radicado` = `CC-{año}-{secuencia de 6 dígitos global}`, asignado al pasar a ENVIADA (no en borrador).

---

## 9. Seguridad e identidad del contratista

- Identificación: cédula + número de contrato + código OTP (6 dígitos, 10 minutos, un solo uso). Cédula y contrato solos NO bastan: el número de contrato es público (SECOP) y detrás hay cuenta bancaria, dirección y teléfono.
- Sesión del contratista separada de la del panel (cookie distinta, `SameSite=Strict`, `HttpOnly`, `Secure`), expira a los 30 minutos de inactividad, con aviso amable de 2 minutos antes.
- Rate limit: 5 solicitudes de código por cédula por hora; 10 intentos fallidos por IP por hora.
- Firma: imagen cargada una vez. Cada uso queda en `cc_cuenta_firmas` con hash SHA-256 del PDF, IP, fecha y usuario. El PDF lleva al pie un renglón pequeño: "Documento generado electrónicamente. Radicado CC-2026-000123. Firmado por (nombre) el 05/08/2026 10:32. Verificación: (hash abreviado)". Esto constituye firma electrónica simple en los términos de la Ley 527 de 1999 y el Decreto 2364 de 2012; no reemplaza firma digital certificada y no se debe afirmar que lo hace.
- Descargas: URLs firmadas con token de un solo uso y expiración; nunca rutas directas al archivo.
- Enlace de WhatsApp: el mensaje contiene el radicado y un enlace con token de 7 días; no se adjunta el PDF a un mensaje sin autenticación.
- Todos los accesos a PDF y anexos se auditan.

---

## 10. Extracción asistida de minutas (IA en el panel administrativo únicamente)

Las minutas llegan como PDF escaneado sin capa de texto, 7 a 10 páginas, todas con la misma plantilla de la Oficina Jurídica:

- Página 1: recuadro "INFORMACIÓN GENERAL" con: N° de contrato, ciudad y fecha, tipo de contrato, objeto, contratante, NIT ESE, contratista, rep. legal, NIT contratista, No CC rep. legal, valor, plazo, garantías Sí/No, cargo del supervisor, tabla CDP (No, fecha, código de rubro, nombre de rubro), adición Sí/No, suspensión Sí/No. Luego "Entre los suscritos…" y considerandos A–L.
- Páginas 2–4: CLÁUSULA 1 OBJETO, 2 VALOR, 3 FORMA DE PAGO (con parágrafos), 4 DISPONIBILIDAD PRESUPUESTAL (tabla CDP), 5 PLAZO DE EJECUCIÓN, 6 OBLIGACIONES DEL HOSPITAL, 7 OBLIGACIONES GENERALES DEL CONTRATISTA + "OBLIGACIONES ESPECÍFICAS" (numeradas) + "OBLIGACIONES FRENTE AL SISTEMA DE GESTIÓN INTEGRAL DE CALIDAD" (numeradas, casi siempre las mismas 13).
- Resto: cláusulas 8–31 de texto fijo, y cierre "Se firma en E.S.E. Hospital Rosario Pumarejo de López, por las partes, el (día) de (mes) de 2026" con firmas.

Proceso:
1. Convertir páginas 1 a 5 a PNG (pdftoppm a 150 dpi o Imagick). Guardar las imágenes para la pantalla de revisión (todas las páginas, 100 dpi).
2. Enviar las imágenes de las páginas 1–5 al proveedor de IA configurado en la plataforma (interfaz provider-agnostic existente; debe soportar imágenes) con la instrucción de devolver SOLO este JSON:

```json
{
  "numero_contrato": "1407-2026",
  "ciudad": "Valledupar",
  "fecha_suscripcion": "2026-08-01",
  "tipo_contrato": "PRESTACIÓN DE SERVICIOS DE APOYO A LA GESTIÓN",
  "objeto": "…texto completo…",
  "contratista": { "nombre_completo": "…", "tipo_documento": "CC", "numero_documento": "…" },
  "valor_total": 14250000,
  "valor_total_letras": "CATORCE MILLONES DOSCIENTOS CINCUENTA MIL PESOS M/CTE",
  "plazo_texto": "CINCO (5) MESES",
  "plazo_recuadro": "CINCO (5) MESES",
  "plazo_clausula_5": "CINCO (5) MESES",
  "garantias": false,
  "cargo_supervisor": "PROFESIONAL UNIVERSITARIO DE SISTEMAS",
  "cdp": [ { "numero": "1611", "fecha": "2026-07-24", "codigo_rubro": "2.1.2.02.02.008-83118", "nombre_rubro": "SERVICIOS DE GESTIÓN Y ADMINISTRACIÓN EMPRESARIAL (OFICINA CENTRAL)", "valor": null } ],
  "forma_pago_texto": "…cláusula 3 completa…",
  "cuotas": [ { "numero": 1, "descripcion": "Agosto de 2026", "mes": "2026-08", "valor": 2850000, "tipo": "COMPLETA" } ],
  "requisitos_pago": [ "Presentación de informe de ejecución de actividades…", "…" ],
  "obligaciones_especificas": [ "Cubrir el servicio de mantenimiento…", "…" ],
  "obligaciones_sgic": [ "…" ],
  "fecha_firma": "2026-08-01",
  "confianza": { "valor_total": 0.98, "cuotas": 0.85 }
}
```

3. Validar en servidor y generar `alertas_json`: suma de cuotas ≠ valor_total; valor en letras ≠ valor en número (comparar con `NumeroALetras`); `plazo_recuadro` ≠ `plazo_clausula_5`; número de cuotas incoherente con el plazo; objeto que menciona "profesional" con tipo "apoyo a la gestión" o viceversa; obligaciones específicas que no mencionan ninguna palabra del objeto (heurística simple, solo aviso); contratista ya existente con otro nombre para la misma cédula; número de contrato ya registrado.
4. Mostrar la pantalla A2. Nada se activa sin revisión humana. Registrar en auditoría quién revisó y qué campos cambió respecto a la extracción.
5. Si el proveedor de IA no está configurado o falla, la pantalla A2 funciona igual con el formulario vacío y las imágenes a la izquierda (carga manual). Como respaldo opcional, OCR con Tesseract (`-l spa --psm 4`) sobre las páginas 2–4 y expresiones regulares sobre "CLAUSULA 2", "FORMA DE PAGO", "PLAZO DE EJECUCIÓN", "OBLIGACIONES ESPECIFICAS"; el recuadro de la página 1 no se extrae bien con OCR y se deja al revisor.

---

## 11. Plantillas PDF

Tres plantillas HTML en `/templates/pdf/`, renderizadas con Dompdf (tamaño carta, márgenes 2 cm, fuente DejaVu Sans o Arial 10 pt; usar tablas con bordes para el diseño, no flexbox; imágenes embebidas como base64). Reproducir fielmente los formatos institucionales existentes. El encabezado de cada página lleva: logo del hospital a la izquierda, "NIT: 892399994-5", nombre del formato, y a la derecha la tabla CÓDIGO / VERSIÓN / FECHA / HOJA (Página X de Y) con los valores de `cc_plantillas`. Reemplazo de marcadores `{{campo}}` con escapado HTML; bucles simples `{{#obligaciones}}…{{/obligaciones}}`. Tests de render con un contrato de prueba.

### 11.1 Cuenta de cobro — GF-FR-002 v001, proceso Gestión Financiera (GF)

Estructura (una página):
1. Encabezado. Título "CUENTA DE COBRO". Línea "Cuenta de cobro N° {{consecutivo}}".
2. "E.S.E HOSPITAL ROSARIO PUMAREJO DE LOPEZ / 892.399.994 - 5 / DEBE A:".
3. Tabla de datos: Nombre del contratista; RUT (número de documento); Dirección; Teléfono / celular; Correo electrónico; Contacto; Fecha de cobro ({{fecha_cobro_larga}}); Contrato N° {{numero_contrato}} con CDP {{cdp_numero}} y R.P. {{rp_numero}}; Adición: CDP / R.P. (vacíos si no hay).
4. Tabla CONCEPTO | FECHA DE INICIO CONTRATO | FECHA DE TERMINACIÓN CONTRATO | SECCIÓN O UBICACIÓN | TOTAL SERVICIOS. Concepto = `"{{objeto}}, según contrato N° {{numero_contrato}}, suscrito el {{fecha_suscripcion_larga}} y conforme al informe de supervisión y ejecución de las actividades adjunto a la presente cuenta de cobro y correspondiente al periodo comprendido {{periodo_texto}}"`. Fila TOTALES.
5. "FIRMA DEL CONTRATISTA" con imagen de firma, nombre y "C.C. {{numero_documento}}".
6. Texto fijo: "Me permito certificar bajo la gravedad de juramento, que mi ingreso base de cotización corresponde al 40% del total de mis ingresos correspondiente al presente contrato. Así mismo solicito que se aplique la tabla de retenciones que trata el art. 383 de E.T. y que este no es inferior a un (1) SMLV."
7. Dos bloques lado a lado: "Anexos Cuenta de Cobro" (SI/NO: Planilla pagos a seguridad social; Copia RUT actualizado; Informe de Actividades; Informe de Supervisión; Otros: Cont, CDP, RP, RUT, cédula) y "CÁLCULO APORTES OBLIGATORIOS A SEGURIDAD SOCIAL (SALUD, PENSIÓN Y ARL)": Aporte obligatorio a salud (12,5%) {{aporte_salud}}; Aporte obligatorio a pensión (16%) {{aporte_pension}}; Fondo de solidaridad pensional 1% {{aporte_fsp}}; Aportes a Riesgos laborales (ARL) {{tarifa_arl}} {{aporte_arl}}; Total Aportes Obligatorios {{total_aportes}}.

### 11.2 Informe de actividades (sin código SGC hoy; dejar código/versión configurables en `cc_plantillas`; el Interventor definirá el código)

1. Recuadro título: "INFORME DE ACTIVIDADES No {{consecutivo}} / CORRESPONDIENTE AL PERIODO COMPRENDIDO ENTRE EL {{periodo_desde_larga_mayus}} AL {{periodo_hasta_larga_mayus}}".
2. "1. INFORMACIÓN GENERAL": tabla con No DE CONTRATO | {{numero_contrato}} | {{ciudad}}, {{fecha_suscripcion_larga}}; TIPO DE CONTRATO; OBJETO; CONTRATANTE "HOSPITAL ROSARIO PUMAREJO DE LÓPEZ"; NIT ESE "892399994-5"; CONTRATISTA; Rep. Legal "N/A"; NIT CONTRATISTA | No CC Rep. legal; VALOR ({{valor_total_letras}} ({{valor_total}})); PLAZO | Garantías Sí/No; SUPERVISOR {{supervisor_nombre}} | CARGO DE SUPERVISOR {{supervisor_cargo}}; FECHA DE INICIO {{fecha_acta_inicio}} | ADICIÓN Sí/No; FECHA DE TERMINACIÓN {{fecha_terminacion}} | SUSPENSIÓN Sí/No.
3. "2. IMPUTACIÓN PRESUPUESTAL": una fila por CDP y una por RP: CONTRATO | Fecha | Rubro | Concepto (nombre de rubro) | Recurso | CDP No. / RP No. | Valor.
4. "3. EJECUCIÓN DE ACTIVIDADES FRENTE A LAS OBLIGACIONES DURANTE EL PERÍODO REPORTADO": tabla OBLIGACIÓN CONTRACTUAL | OBLIGACIÓN EJECUTADA, una fila por obligación específica con su descripción.
5. "4. CUMPLIMIENTO DE LAS OBLIGACIONES DE SEGURIDAD SOCIAL Y PARAFISCALES": OBLIGACIONES PRESTACIONALES | CUMPLIMIENTO DE LA SEGURIDAD SOCIAL Y PARAFISCALES | VALOR DEL I.B.C {{ibc}}; Mes de salud {{mes_salud}} | Mes de pensión {{mes_pension}}; SEGURIDAD SOCIAL PLANILLA N° {{numero_planilla}} | VALOR DEL APORTE {{total_ss}}; Salud; Pensión; Riesgos Laborales; PARAFISCALES VALOR DEL APORTE {{total_parafiscales}}; Sena; ICBF; Caja de Compensación Familiar.
6. "5. DECLARACIONES GENERALES" (texto fijo): "Manifiesto que he cumplido con las obligaciones derivadas del contrato y que las actividades mencionadas en el presente informe corresponden a las labores efectivamente desarrolladas en el periodo indicado, declarando que seré responsable por las afirmaciones contenidas en el presente documento, que sirve como soporte para certificar el cumplimiento del objeto del contrato. También declaro que he cumplido con la obligación de realizar los aportes al Sistema General de Seguridad Social Integral – SGSSI en los periodos y valores señalados."
7. "6. DECLARACIONES ESPECIALES": "Que la E.S.E. Hospital ROSARIO PUMAREJO DE LOPEZ - Cesar, debe por concepto de servicios prestados profesionales por ejecución de las obligaciones del contrato de la referencia, en el periodo comprendido {{periodo_texto}}, la suma de {{valor_letras}} (${{valor}})". Luego, texto fijo: "En virtud de lo contemplado en el parágrafo 2 del Artículo 17 de la Ley 1819 del 29/12/2016 que modificó el Artículo 383 del Estatuto Tributario, declaro bajo la gravedad de juramento que no tengo otro contrato o vinculados dos (2) o más trabajadores asociados a la actividad que realizo en esta entidad." Luego: "Autorizo que el valor adeudado a mi favor sea consignado en la CUENTA DE {{tipo_cuenta}} N° {{numero_cuenta}} de {{banco}}. El presente documento se asimila a soporte equivalente de pago o cuenta de cobro."
8. "7. ANEXOS": lista de anexos adjuntos (Planilla de pago aportes SGSS; Cuenta de cobro N° {{consecutivo}}; los demás que se hayan subido).
9. Firma del contratista, nombre, "No CC {{numero_documento}} de {{lugar_expedicion}}".

### 11.3 Informe de supervisión contractual — GJ-FR-004 v002, proceso Gestión Jurídica

1. Encabezado con la tabla CÓDIGO GJ-FR-004 / VERSIÓN 002 / FECHA (la de `cc_plantillas`) / HOJA. Título "INFORME DE SUPERVISIÓN N° {{consecutivo}}".
2. "1. INFORMACIÓN GENERAL": misma tabla de 11.2 (2), con VALOR ADICIÓN, VALOR CONTRATO + ADICIÓN, PLAZO ADICIÓN cuando existan novedades.
3. "2. IMPUTACIÓN PRESUPUESTAL": igual que 11.2 (3), más filas "ADICIÓN" si las hay.
4. "3. AVANCE FINANCIERO": CONCEPTO | VALOR / N. A.: VALOR INICIAL DEL CONTRATO; VALOR ADICIÓN; ANTICIPO ($0); PAGOS ANTERIORES ACUMULADOS; PAGO AUTORIZADO EN EL PRESENTE INFORME; SALDO POR EJECUTAR; VALOR TOTAL DEL CONTRATO. Todos calculados; ninguno en blanco.
5. "4. CUMPLIMIENTO DE OBLIGACIONES": tabla No | Obligación | Descripción de ejecución por parte del contratista | Sí | No | Parcial, una fila por obligación específica, con "X" en la columna marcada por el supervisor. Las mismas obligaciones y las mismas descripciones que el informe de actividades, sin excepción.
6. "5. CUMPLIMIENTO DE LAS OBLIGACIONES DE SEGURIDAD SOCIAL Y PARAFISCALES": párrafo fijo "Mediante la adjunta al informe, se pasó a verificar el cumplimiento del artículo 50 de la Ley 789 de 2002 como documento de verificación y constancia del cumplimiento del pago de la seguridad social en mi condición de supervisor del contrato estatal." y la misma tabla de 11.2 (5).
7. "6. CERTIFICACIÓN DE CUMPLIMIENTO": "De acuerdo a la información anteriormente suministrada en mi calidad de Supervisor (a), certifico que durante el periodo comprendido {{periodo_texto}}, el Contratista cumplió oportunamente las actividades objeto del contrato de la referencia y cumplió con el pago de Seguridad Social y/o parafiscales correspondiente al mes de {{mes_salud_texto}}, de conformidad con el artículo 50 de la ley 789 del 2002, Artículo 23 de la ley 1150 del 2007, Decreto 1703 del 2002 y demás leyes y Decretos que lo reglamentan." Luego: "Por lo anterior, el (a) suscrito (a) autoriza el pago correspondiente al periodo comprendido {{periodo_texto}} por valor de {{valor_letras}} (${{valor}}) y se han consignado en la CUENTA DE {{tipo_cuenta}} N° {{numero_cuenta}} de {{banco}} a nombre de {{contratista_nombre}}, identificado(a) con cédula de ciudadanía N° {{numero_documento}} de {{lugar_expedicion}}." Nota: el periodo es UNO SOLO en todo el documento; se toma de la cuenta.
8. "Se adjunta la relación de los siguientes anexos:" lista generada (Informe de actividades Contratista; Certificado de pago seguridad social integral; y los anexos subidos).
9. "Dado en el municipio de Valledupar, Cesar, a los {{dia_letras}} días del mes de {{mes}} de {{año}}."
10. Firma del supervisor (imagen), nombre en mayúsculas, cargo, "ESE Hospital Rosario Pumarejo De López", "Supervisión".

### 11.4 Paquete

Orden del PDF combinado: cuenta de cobro, informe de actividades, informe de supervisión, planilla PILA, demás anexos. Combinar con una librería PHP pura (por ejemplo FPDI con las páginas ya generadas por Dompdf; si FPDI no puede importar alguna página, dejar el paquete como ZIP de PDFs y avisar). Los anexos en imagen se convierten a página PDF.

---

## 12. Fases de construcción y criterios de aceptación

Trabaja una fase a la vez. No empieces la siguiente sin cumplir los criterios de la anterior.

### Fase 1 — Modelo, administración y contratos (base)
Construir: tablas de la sección 6 (migración SQL idempotente), pantallas A1 (contratos, alta manual), A3 (RP), A4, A5, A6, A7; clase `CalculosCuenta` con cronograma (8.1), prorrateo (8.2), seguridad social (8.3); `NumeroALetras` y `Fechas` (8.4); pruebas unitarias.
Aceptación:
- Cargar manualmente los 7 contratos de muestra (sección 13) y activarlos; el cronograma de cada uno suma exactamente el valor total.
- Registrar acta de inicio del 1407-2026 con fecha 2026-08-01 → 5 periodos calendario agosto–diciembre, fecha de terminación 2026-12-31.
- Registrar acta del 1414-2026 con fecha 2026-07-27 → cuota 1 del 27 al 31 de julio ($583.333) y 5 cuotas de $3.500.000.
- `NumeroALetras::pesos(29303733)` = "VEINTINUEVE MILLONES TRESCIENTOS TRES MIL SETECIENTOS TREINTA Y TRES PESOS M/CTE ($ 29.303.733)".
- Seguridad social sugerida para cuota de 2.350.000 y clase 3: IBC 1.750.905, salud 218.900, pensión 280.200, ARL 42.700.
- Toda acción de administración aparece en la auditoría de la plataforma.

### Fase 2 — Portal del contratista
Construir: P0 a P6 completas, OTP, borrador con autoguardado, carga de anexos, generación de PDF de cuenta de cobro e informe de actividades (11.1, 11.2), firma, envío por correo y enlace de WhatsApp, notificación al supervisor.
Aceptación:
- Un contratista de prueba entra con cédula + contrato + OTP en menos de 4 pantallas y completa su primera cuenta en menos de 10 minutos con datos precargados.
- El PDF de cuenta de cobro e informe de actividades reproducen los formatos con los mismos títulos, secciones y textos fijos de la sección 11; validar visualmente contra los documentos originales de muestra.
- Cerrar el navegador a mitad del paso 2 y volver: el texto escrito está ahí.
- Con un lector de pantalla (NVDA o TalkBack) se completa el flujo. Puntaje Lighthouse accesibilidad ≥ 95 en P0–P5.
- Todo el portal funciona en un viewport de 360 px.

### Fase 3 — Supervisor y paquete
Construir: S1 a S4, informe de supervisión (11.3), paquete combinado (11.4), devolución con notificación, avance financiero congelado, `cc_cuenta_firmas`.
Aceptación:
- Aprobar la cuenta N° 1 y luego crear la N° 2: "pagos anteriores acumulados" de la N° 2 = valor de la N° 1; saldo correcto.
- Devolver una cuenta: el contratista ve el motivo en su inicio, corrige, reenvía; el historial muestra las tres transiciones.
- El informe de supervisión tiene exactamente las mismas obligaciones y descripciones que el informe de actividades de la misma cuenta.
- Ningún campo de avance financiero queda en blanco. El periodo aparece idéntico en todos los párrafos.
- El paquete combinado abre en Adobe Reader y en Chrome, con las páginas en el orden de 11.4.

### Fase 4 — Novedades, reportes y robustez
Construir: novedades (adiciones con CDP/RP propios, prórrogas, suspensiones con recálculo de periodos, terminación anticipada con prorrateo), A8 reportes, recordatorios automáticos (al contratista cuando su cuota está disponible y no ha presentado; al supervisor a los 3 días hábiles sin revisar), marca "Entregada a Financiera", anulación.
Aceptación:
- Adición de $2.850.000 y un mes al 1407-2026 → cuota 6 de enero de 2027, VALOR ADICIÓN y VALOR CONTRATO + ADICIÓN aparecen en el informe de supervisión.
- Suspensión del 10 al 20 de septiembre → la cuota de septiembre se prorratea a 20 días y las siguientes se desplazan; ninguna cuenta ya aprobada cambia.
- Reporte de tiempos por supervisor exporta CSV.

### Fase 5 — Extracción asistida de minutas
Construir: A2 completa con la sección 10, conversión de PDF a imágenes, llamada al proveedor de IA, validaciones y alertas, pantalla de revisión a dos columnas, respaldo OCR.
Aceptación:
- Con las 7 minutas de muestra, la extracción llena correctamente número, fecha, contratista, cédula, valor, plazo, CDP y cronograma en al menos 6 de 7 sin corrección manual; las obligaciones específicas salen completas y en orden.
- La minuta 1473-2026 genera la alerta "plazo del recuadro (4 meses) ≠ plazo de la cláusula 5 (2 meses)".
- Sin proveedor de IA configurado, A2 permite la carga manual con las imágenes al lado.

---

## 13. Datos de muestra para desarrollo y pruebas

Contratos reales de la vigencia 2026 (para pruebas usa cédulas, teléfonos y cuentas bancarias FICTICIAS; los datos personales reales solo se cargan en producción por el administrador). Rubro en todos: 2.1.2.02.02.008-83118 "SERVICIOS DE GESTIÓN Y ADMINISTRACIÓN EMPRESARIAL (OFICINA CENTRAL)"; recurso "1.2.3.2.09 VENTA DE BIENES Y SERVICIOS".

| Contrato | Fecha | Objeto (resumen) | Valor | Plazo | Cuotas | CDP |
|---|---|---|---|---|---|---|
| 1305-2026 | 2026-07-07 | Profesional de apoyo al área de sistemas, seguridad TI | 23.333.333 | 25 días y 5 meses | 3.333.333 (25 días de julio) + 5 × 4.000.000 (ago–dic) | 1495 del 2026-07-06 |
| 1407-2026 | 2026-08-01 | Apoyo a la gestión como tecnólogo en sistemas, mantenimiento | 14.250.000 | 5 meses | 5 × 2.850.000 (ago–dic) | 1611 del 2026-07-24 |
| 1408-2026 | 2026-08-01 | Apoyo a la gestión como técnico en sistemas, mantenimiento | 13.750.000 | 5 meses | 5 × 2.750.000 (ago–dic) | 1612 del 2026-07-24 |
| 1409-2026 | 2026-07-24 | Profesional de apoyo al área de sistemas, seguridad TI | 29.303.733 | 8 días y 5 meses | 1.483.733 (8 días de julio) + 5 × 5.564.000 | 1613 del 2026-07-24 |
| 1414-2026 | 2026-07-27 | Profesional: ingeniero de sistemas | 18.083.333 | 5 días y 5 meses | 583.333 (julio) + 5 × 3.500.000 | 1622 del 2026-07-27 |
| 1415-2026 | 2026-07-27 | Apoyo a la gestión como tecnólogo en sistemas, mantenimiento | 14.250.000 | 5 meses | 5 × 2.850.000 (ago–dic) | 1623 del 2026-07-27 |
| 1473-2026 | 2026-08-01 | Técnico en sistemas, mantenimiento | 10.600.000 | 4 meses (recuadro) / 2 meses (cláusula 5) — inconsistente | 4 × 2.650.000 (ago–nov) | 1669 del 2026-07-30 |

Contrato de referencia para los formatos (documentos de muestra completos): 1060-2026, técnico administrativo (facturador), $14.100.000, 6 meses, 6 × 2.350.000, modalidad ANIVERSARIO desde el 2026-01-28, CDP 271 del 2026-01-28 por $282.000.000 (CDP global), RP 2055 por $14.100.000, supervisor "Profesional Universitario (Facturación)", sección "Autorizadora urgencias", clase ARL 3.

Obligaciones específicas típicas (contrato 1407-2026), para el seed:
1. Cubrir el servicio de mantenimiento preventivo y correctivo hospitalario durante los turnos asignados, de lunes a domingo incluido los días festivos, conforme al plan de mantenimiento de la E.S.E. y las necesidades.
2. Asegurar el funcionamiento continuo y eficaz de los equipos, minimizando las posibles paradas como consecuencia de averías. El objetivo de disponibilidad total ha de entenderse como alcanzable.
3. Presentar una relación mensual y físico de repuestos y/o accesorios utilizados en el desarrollo contractual.
4. Apoyar en el proceso de levantamiento de inventario de activos fijos de equipos de cómputo y redes, además actualización de las hojas de vida de los equipos de cómputo (hardware y software), también se cubrirán las visitas de inspección, vigilancia y control, cuando ejerzan funciones de inspección, vigilancia y control sobre el mantenimiento hospitalario y demás procesos desarrollados de Gestión Tecnológica en Salud, en la Institución.
5. Apoyar el proceso de bajas administrativas de equipos de cómputo y componentes de redes de datos.
6. Elaborar, actualizar, custodiar y centralizar el historial de mantenimiento de equipos de cómputo.
7. Apoyo en la realización y actualización del Inventario Técnico de los bienes de la Institución en relación a equipos de cómputo y comunicación.
8. Apoyo en la realización y actualización del diseño lógico y físico de la red LAN de los bienes de la institución en relación a equipos de comunicación.
9. Apoyo en la realización y actualización en el rotulado e identificación de los tendidos de cableado horizontal ANSI/UL 696.
10. Asesorar y acompañar en el diseño, elaboración y actualización del Plan de Mantenimiento de cómputos.
11. Acompañamiento para la recepción, inventariado y puesta en marcha de los nuevos equipos e instalaciones adquiridos o introducidos por la entidad.
12. Realizar seguimiento al estado actual de los equipos de cómputo y redes de la ESE.
13. Apoyar en la ejecución de los planes de mejoramiento que se deriven del proceso de mantenimiento hospitalario.
14. Realizar el mantenimiento preventivo y correctivo de las impresoras que actualmente tiene el hospital.

---

## 14. Decisiones ya tomadas (no volver a preguntar)

- Solo contratistas de prestación de servicios; el personal de planta no usa el módulo.
- El flujo termina en la aprobación del supervisor; lo que sigue (Financiera) está fuera de alcance.
- Firma: imagen escaneada + evidencia electrónica (sección 9). No hay firma digital certificada.
- RP lo registra el administrador; acta de inicio la registra el supervisor.
- Formatos: los custodia el Agente Especial Interventor; el informe de actividades se puede unificar bajo un código SGC (pendiente el código; dejar configurable).
- La IA solo vive en el panel administrativo (extracción de minutas). El portal del contratista no usa IA.
- No se conoce el número de contratos vigentes ni si existe una base: la carga es minuta por minuta; importación masiva por CSV queda como mejora opcional al final de la fase 5.
- Ingreso del contratista: cédula + contrato + OTP (o PIN opcional). Nunca cédula + contrato solos.

## 15. Preguntas que sí debes hacer si surgen

- Nombre real de la tabla de usuarios, roles y auditoría de la plataforma, y cómo se envían hoy correos y WhatsApp.
- Si el proveedor de IA de la plataforma soporta entrada de imágenes; si no, qué proveedor usar para la fase 5.
- Texto oficial del aviso de privacidad para el primer ingreso del contratista.
- Código SGC definitivo del informe de actividades.
- Qué anexos deben ser obligatorios por defecto además de la planilla PILA.

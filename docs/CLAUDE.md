# CLAUDE.md

Instrucciones permanentes para el asistente de código en este repositorio.

**Proyecto:** Sitio web institucional de la E.S.E. Hospital Rosario Pumarejo de López (Valledupar, Cesar).
**Documentos de referencia:** `docs/plan-de-desarrollo.md` · `docs/00-plan-de-arranque.md` · `database/esquema.sql`

> **Decisión 2026-08-14 — versión de PHP.** El entorno de desarrollo (Laragon) trae
> PHP 8.3.30. Se fija **8.3 como versión objetivo**. No se usan *property hooks* ni
> visibilidad asimétrica. Todo lo demás del documento aplica sin cambios.

---

## 1. Qué es esto

Un **sitio web institucional con panel administrativo completo**. Un solo hospital.

No es un CMS como producto. No es multi-tenant. No es SaaS. Si una decisión de diseño solo tiene sentido para vender la plataforma a otros hospitales, es la decisión equivocada.

**Dos criterios de éxito, en este orden:**

1. **El ciudadano encuentra lo que busca desde el celular en menos de treinta segundos.** Llega con conexión mala buscando una de seis cosas: una cita, un teléfono, urgencias, un documento, radicar una queja, o saber si un especialista atiende.
2. **El hospital nunca necesita llamar a un desarrollador para cambiar algo del sitio.** Todo lo que cambia se administra desde el panel.

Si algo mejora el segundo criterio a costa del primero, gana el primero.

---

## 2. Reglas de oro

1. **No construyas nada fuera del alcance de la sección 7.** Si tienes una buena idea, escríbela en `docs/BACKLOG.md` y sigue con lo que toca.
2. **Todo lo que un funcionario deba poder cambiar va en base de datos.** Textos, colores, teléfonos, horarios, menús, plantillas de correo, campos de formulario. Nunca en código.
3. **Toda acción con efecto institucional deja auditoría.** Sin excepciones.
4. **Presupuesto de rendimiento antes que funcionalidad.** LCP < 2.5s en 3G simulado, JS inicial < 100KB comprimido.
5. **Accesibilidad WCAG 2.1 AA no es opcional.** Es exigible por norma desde 2022.
6. **Los datos de salud son sensibles.** Ningún dato clínico entra a este sistema. Ni resultados, ni diagnósticos, ni historia clínica. Solo solicitudes.
7. **Nada de sobre-ingeniería.** Un hospital, un servidor, decenas de miles de registros. No hay event bus distribuido, ni CQRS, ni microservicios, ni data warehouse.

---

## 3. Stack

| | Elección |
|---|---|
| Lenguaje | PHP 8.3+, `declare(strict_types=1)` en todos los archivos |
| Base de datos | MySQL 8+, InnoDB, `utf8mb4_0900_ai_ci` |
| Framework | Ninguno completo. Componentes Symfony vía Composer |
| Frontend | HTML semántico + Tailwind CSS + Alpine.js |
| Editor | CKEditor 5 |
| PDF | DomPDF · Correo: PHPMailer (detrás de interfaz) |
| Calendario | FullCalendar · Gráficas: Chart.js |
| Caché | Redis, con fallback a tabla MySQL |
| Tests | PHPUnit + Playwright |

**Componentes Symfony permitidos:** `http-foundation`, `routing`, `dependency-injection`, `console`, `mailer`, `validator`, `translation`, `html-sanitizer`. Más `doctrine/dbal` y `doctrine/migrations`.

> **Decisión 2026-08-21 — `symfony/html-sanitizer`.** La lista blanca de HTML
> de CKEditor (§6, Contenido) se aplica con este componente. Escribir un
> sanitizador a mano es exactamente el tipo de rueda que este documento
> prohíbe reinventar: un fallo ahí es XSS en el sitio de un hospital.

No escribas a mano un router, un contenedor de dependencias ni un sistema de migraciones. Eso es reconstruir un framework peor.

**Prohibido:** WordPress, Joomla, Laravel completo, Symfony completo, React, Vue, jQuery, Bootstrap, cualquier ORM Active Record.

---

## 4. Arquitectura

Monolito modular. Capas simples, sin ceremonia.

```
public/                  ← document root, único punto de entrada
src/
├── Core/                ← infraestructura compartida
│   ├── Auth/            ← autenticación, sesión, permisos
│   ├── Audit/           ← registro de auditoría
│   ├── Config/          ← configuración dinámica
│   ├── Database/        ← conexión, query builder ligero
│   ├── Events/          ← despachador simple, síncrono
│   ├── Storage/         ← archivos
│   └── Support/
├── Modules/
│   ├── Contenido/       ← páginas, noticias, campañas, avisos, FAQ
│   ├── Directorio/      ← sedes, especialidades, servicios, profesionales
│   ├── Documentos/      ← gestión documental
│   ├── Transparencia/   ← Ley 1712, datos abiertos
│   ├── Tramites/        ← motor de trámites, PQRSDF, radicación
│   ├── Cumplimiento/    ← políticas, cookies, habeas data
│   ├── Busqueda/        ← índice unificado
│   └── Sistema/         ← usuarios, roles, menús, redirecciones, respaldos
├── Http/
│   ├── Web/             ← controladores del sitio público
│   ├── Admin/           ← controladores del panel
│   └── Api/             ← API de solo lectura, versionada
├── Ports/               ← interfaces de servicios externos
└── Adapters/            ← implementaciones concretas
resources/views/         ← plantillas
resources/{css,js}/
database/{migrations,seeders}/
storage/{uploads,cache,logs,backups}/
tests/{Unit,Feature,E2E}/
```

**Cada módulo contiene:** `Entity/`, `Repository/`, `Service/`, `Controller/`, `Request/`.

**Regla de dependencias:** los módulos no se importan entre sí. Si `Tramites` necesita algo de `Documentos`, pasa por un servicio público del módulo o por un evento. Nada de acoplar repositorios entre módulos.

**Ports y Adapters** aplica solo a servicios externos: correo, almacenamiento, IA, WhatsApp, HIS. El resto del código llama a sus repositorios directamente. No conviertas todo en una interfaz.

---

## 5. Convenciones de código

- PSR-12, PSR-4 (`App\` → `src/`)
- Tipado estricto. Sin `mixed`, sin `array` como contrato. DTOs y value objects.
- Clases `final` y `readonly` por defecto
- Enums nativos para estados. Nunca strings mágicos
- Inyección por constructor. Cero estado global, cero `static` salvo factorías puras
- **Todo SQL con sentencias preparadas.** Nunca concatenación
- Nombres de dominio en español (`Tramite`, `Radicado`, `Sede`), sufijos técnicos en inglés (`Repository`, `Service`, `Controller`)
- Excepciones tipadas, nunca `\Exception` genérica
- Comentarios solo para explicar *por qué*

---

## 6. Reglas por área

### Contenido y publicación
- Todo contenido publicable tiene `estado_aprobacion`: `borrador` → `en_revision` → `aprobado` → `publicado`
- Campos `publicar_en` y `despublicar_en` funcionales mediante tarea programada
- Versionado en `contenido_versiones` con snapshot JSON. Restaurable a cualquier versión
- El HTML de CKEditor se sanitiza con lista blanca **antes** de guardarse
- Borrado lógico (`eliminado_en`) en todo contenido, documento y trámite. Nunca `DELETE` físico

### Documentos
- Un documento es un registro con metadatos, no un archivo suelto
- Campos obligatorios: título, resumen, dependencia, categoría, fecha, vigencia
- Serie y subserie documental (Tablas de Retención Documental del AGN)
- `vigencia_hasta` y `reemplaza_a_id` permiten detectar documentos vencidos y normas derogadas
- Los PDF nunca son la interfaz. Siempre ficha HTML + descarga

### Trámites
- **Motor configurable, no BPM.** Un trámite se define en `tipos_tramite` + `tramite_campos` + `tramite_estados`, desde el panel
- PQRSDF es un tipo de trámite, no una tabla especial
- Radicado obligatorio, formato `HRPL-{AAAA}-{secuencia}`, único, generado con bloqueo transaccional
- Término legal calculado en **días hábiles** usando la tabla `festivos`
- Consulta pública por radicado + número de documento. Sin cuenta de usuario
- Solo los seguimientos marcados `visible_ciudadano` se muestran públicamente
- **Nunca** se entrega información clínica por el sitio. La solicitud de historia clínica se radica en línea y se entrega por canal controlado

### Datos personales
- Casilla de autorización **no premarcada** en todo formulario, con enlace a la política vigente
- Registrar evidencia en `autorizaciones_datos`: fecha, hora, IP, versión de la política aceptada
- Trámite específico de habeas data (supresión, actualización, copia, revocación)
- Banner de cookies que **bloquea** los scripts hasta la aceptación, con consentimiento granular. No decorativo

### Seguridad
- Cookies `HttpOnly`, `Secure`, `SameSite=Strict`. Regeneración de ID al autenticar
- CSRF en toda mutación. Rate limiting en login, trámites y búsqueda
- Contraseñas con Argon2id. MFA para roles administrativos
- Cabeceras: HSTS, CSP, `X-Content-Type-Options`, `Referrer-Policy`
- Subida de archivos: validación por contenido real, fuera del document root, servidos por controlador que valida permisos
- `auditoria` es append-only. Sin update ni delete

### Frontend
- Presupuesto: LCP < 2.5s en 3G, JS inicial < 100KB, cero layout shift
- WCAG 2.1 AA: contraste ≥ 4.5:1, navegación por teclado, `alt` en toda imagen, foco visible, jerarquía de encabezados, `label` asociado
- Área táctil mínima 44×44px, tipografía base 16px
- CSS moderno: nesting nativo, container queries, `clamp()` para tipografía fluida
- Imágenes AVIF/WebP con `srcset` y `loading="lazy"`
- Fuentes variables autoalojadas. Nada de Google Fonts por CDN (privacidad)
- Alpine.js solo para interactividad puntual. Si una página necesita más de ~50 líneas de JS, replantea
- La home muestra máximo 8 acciones destacadas
- Sin tablas anchas en móvil: reconviértelas en tarjetas

### Panel administrativo
- Publicar una noticia: título, contenido, imagen, botón. Nada más en el camino feliz
- Vista previa antes de publicar. Autoguardado de borradores
- Búsqueda global dentro del panel
- Mensajes de error en español, comprensibles para un funcionario
- Responsivo: el jefe de prensa publica desde el celular
- Sin jerga técnica en la interfaz. Nada de "alias", "ACL", "posición de módulo"

### IA (opcional, desactivable)
- **Solo en el panel, nunca de cara al ciudadano en v1**
- Usos permitidos: resumen de documentos, sugerencia de etiquetas, generación de texto alternativo para imágenes, sugerencia de metadatos SEO, borrador de noticia
- Detrás de la interfaz `Ports\AiProvider`, con proveedor y modelo configurables desde el panel
- El resultado siempre se presenta como sugerencia editable, nunca se aplica automáticamente
- Si la IA está desactivada, el sistema funciona igual

---

## 7. Alcance

### Entra en v1

Portal público completo · Menús obligatorios de la Resolución 1519 (Transparencia, Atención y Servicios a la Ciudadanía, Participa, Noticias) · Gestión documental con metadatos, versiones, vigencia y TRD · Buscador unificado · Motor de trámites configurable · PQRSDF · Solicitud de cita · Solicitud de historia clínica · Solicitud de certificados · Habeas data · Convocatorias · Directorio, especialidades, servicios, sedes · Avisos y modo emergencia · FAQ · Datos abiertos · Políticas, cookies y autorizaciones con evidencia · Flujo de aprobación, programación y versionado · Panel administrativo completo · Auditoría · Accesibilidad AA · PWA · SEO técnico · Redirecciones 301 desde Joomla · IA en el panel

### Preparado pero no implementado

Interfaces definidas en `Ports/` con implementación nula. Nada más.

`AiProvider` · `MessagingProvider` (WhatsApp) · `HisConnector` · `NotificationSender`

### Fuera de v1 — no construir

Cuenta de usuario ciudadano · Agendamiento real de citas · Resultados de laboratorio · Chat en vivo · Chatbot de cara al ciudadano · WhatsApp · Portal del colaborador, proveedor o contratista · Encuestas automáticas · Tablero de cumplimiento normativo

### Nunca — no construir

Multi-tenant, SaaS, marketplace, sistema de plugins, SDK público · Historia clínica electrónica, facturación, LIS, PACS, farmacia, camas · Event bus distribuido, API Gateway, colas distribuidas, DDD estricto, arquitectura hexagonal completa · Data lake, data warehouse, ETL · Kioscos, pantallas, turnos · Telemedicina · Firma digital certificada · Modo offline con sincronización

---

## 8. Cómo trabajar

**Antes de escribir código:**
1. Lee la estructura existente. No inventes patrones si ya hay uno
2. Si la tarea está fuera del alcance de la sección 7, dilo y detente
3. Si requiere una decisión no cubierta aquí, **pregunta antes de asumir**

**Al escribir código:**
- Cambios pequeños y verificables. Un caso de uso a la vez
- Migración antes que código que dependa del esquema
- Test primero cuando haya lógica de negocio (cálculo de términos, generación de radicado, permisos)
- Cuando toques permisos, datos personales o radicación, explica qué verificaste

**Definición de terminado:**
- [ ] Tests pasan
- [ ] `down()` de la migración probado
- [ ] Auditoría registrada si la acción tiene efecto institucional
- [ ] Navegable por teclado, contraste verificado, `label` asociado
- [ ] Dentro del presupuesto de rendimiento en móvil
- [ ] Sin SQL sin preparar, sin salida sin escapar
- [ ] Textos visibles en `lang/es.php`
- [ ] Si es contenido publicable: versionado, aprobación y programación funcionan

**Commits:** convencionales, en español, describiendo el efecto de negocio.
`feat(tramites): calcular vencimiento en días hábiles con festivos`

---

## 9. Glosario

| Término | Significado |
|---|---|
| **E.S.E.** | Empresa Social del Estado — hospital público |
| **PQRSDF** | Peticiones, Quejas, Reclamos, Sugerencias, Denuncias, Felicitaciones |
| **Radicado** | Número único de recepción. `HRPL-{AAAA}-{secuencia}` |
| **Dependencia** | Área administrativa responsable |
| **Sede** | Ubicación física del hospital |
| **TRD** | Tablas de Retención Documental (Archivo General de la Nación) |
| **HIS** | Hospital Information System — el sistema operativo del hospital |
| **Ley 1712** | Transparencia y acceso a la información pública |
| **Ley 1581** | Protección de datos personales (habeas data) |
| **Ley 1755** | Derecho de petición y sus términos |
| **Res. 1519/2020** | Estándares MinTIC de publicación y accesibilidad web |

---

## 10. Cuándo detenerte y preguntar

Antes de: cambiar el stack o agregar una dependencia grande · modificar la estructura de módulos · construir algo fuera del alcance · tomar decisiones sobre plazos legales, retención o consentimiento · cualquier cosa que toque datos clínicos.

En todo lo demás, avanza.

---

*Este documento es la fuente de verdad. Si el código lo contradice, el código está mal.*

# Valoración de alcance — Sitio web E.S.E. Hospital Rosario Pumarejo de López

**Qué es este documento:** una decisión razonada sobre qué se construye, qué se recorta, qué se aplaza y qué se descarta. Cada decisión lleva su justificación. Alcance: **un sitio web institucional con panel administrativo completo.** No multi-tenant, no SaaS, no producto CMS.

---

## Parte A — El criterio

Antes de decidir caso por caso, cuatro filtros. Todo lo que sigue está evaluado con ellos.

| # | Pregunta | Por qué importa |
|---|---|---|
| 1 | ¿Depende de un tercero que no controlas? | HIS, Meta, proveedor de WhatsApp, pasarela de pago. Si el tercero no coopera, la funcionalidad no existe por más código que escribas |
| 2 | ¿Requiere que alguien del hospital trabaje todos los días para que funcione? | Un chat sin quien responda es peor que no tener chat. Una bandeja de solicitudes sin quien la atienda destruye la confianza |
| 3 | ¿Es obligatorio por norma? | No se negocia. Entra aunque sea incómodo |
| 4 | ¿Su ausencia la nota el ciudadano? | Determina prioridad, no inclusión |

**La conclusión más importante de este análisis:** el factor limitante de casi todas las funcionalidades ambiciosas **no es técnico, es operativo.** No es "¿puedo programarlo?" sino "¿hay alguien en el hospital que lo va a operar?". Esa pregunta se le hace al gerente y al líder de Atención al Usuario **antes** de prometer nada.

---

## Parte B — Las cinco exclusiones, revisadas

### B1. Agendamiento de citas

**Corrijo parcialmente mi posición.** Confundí dos cosas distintas.

| | Depende del HIS | Complejidad | Veredicto |
|---|---|---|---|
| **Agendar** (escribir la cita en la agenda real) | Sí, totalmente | Alta | Fuera de v1 |
| **Solicitar** (radicar una petición de cita) | **No** | Baja | **Entra en v1** |

Agendar requiere escribir en el HIS: cupos, profesionales, consultorios, reglas de EPS. Sin API, imposible.

Solicitar no requiere nada. El ciudadano llena un formulario, el sistema genera radicado, y el equipo de citas del hospital la agenda manualmente en su HIS como ya lo hace hoy por teléfono, pero con la solicitud escrita, ordenada y con trazabilidad.

**Campos:** tipo y número de documento · nombre · EPS · régimen · teléfono · correo · especialidad o servicio · sede · preferencia de horario · orden médica adjunta · autorización de datos.
**Estados:** recibida → en gestión → agendada → no procede (con motivo).

**Beneficio adicional que no es obvio:** genera datos. Cuáles especialidades se solicitan más, desde qué municipios, cuánto se demora la gestión. Hoy el hospital no tiene eso porque las citas se piden por teléfono y no queda registro agregado.

**Riesgo que debes negociar antes de construirlo:** esto crea carga operativa nueva. Si nadie revisa la bandeja, se convierte en un agujero negro y el ciudadano pierde la confianza en todo el sitio. **No lo construyas sin compromiso escrito del hospital sobre quién la atiende y en qué plazo.**

---

### B2. Resultados de laboratorio en línea

**Mantengo el no, y con más firmeza que antes.**

Depende del HIS o del LIS, sí. Pero el problema mayor no es técnico:

- Un resultado de laboratorio es dato personal sensible (Ley 1581) y parte de la historia clínica, sujeta a reserva (Resolución 1995 de 1999).
- Publicarlo requiere autenticación fuerte, no un usuario y contraseña cualquiera. Un resultado entregado a la persona equivocada es una violación de reserva de historia clínica con consecuencias disciplinarias y patrimoniales para el hospital.
- La responsabilidad recaería sobre ti como desarrollador si el diseño de autenticación es débil.

**Cómo se resuelve sin construirlo:** la solicitud de copia de historia clínica **sí** es un derecho del paciente y **sí** puede radicarse por el sitio. Lo que no puede es entregarse por el sitio. Se radica en línea, se entrega por canal controlado (presencial con documento, o correo certificado según política del hospital).

Si en el futuro el hospital quiere resultados en línea, el camino correcto es que el proveedor del HIS habilite su propio portal de paciente, no que tú lo construyas.

---

### B3. Cuenta de usuario ciudadano

**Aquí es donde más matizo mi posición anterior, y donde creo que tú tenías razón parcialmente.**

No depende del HIS en absoluto. Y si el sitio va a tener varios trámites —solicitud de cita, historia clínica, certificados, PQRSDF, habeas data— una cuenta tiene valor real: ver todo en un lugar, no volver a escribir los datos, recibir notificaciones.

Pero antes de construirla, considera el costo completo:

- Recuperación de contraseña, verificación de correo, bloqueo por intentos, MFA opcional
- Datos personales almacenados de forma persistente → obligaciones de habeas data, retención y supresión
- Superficie de ataque nueva y responsabilidad si hay filtración
- Soporte: "olvidé mi contraseña" se convierte en trabajo del hospital

**La alternativa que cubre el 90% del valor con el 10% del costo:** consulta por **radicado + número de documento**. Sin cuenta, sin contraseña, sin base de usuarios. El ciudadano guarda su radicado y consulta el estado cuando quiera. Es lo que hacen las entidades públicas que funcionan bien.

**Mi recomendación:** v1 sin cuenta. La cuenta se justifica cuando existan cuatro o más trámites autenticados y volumen que lo pida. Diseña el modelo de datos para que agregarla después no obligue a reescribir (los trámites ya guardan documento y correo; asociarlos a un `ciudadano_id` posterior es una migración, no un rediseño).

Si insistes en la cuenta desde v1, no es un error grave. Es un costo adicional que conviene tomar con los ojos abiertos.

---

### B4. Motor de workflows

**Cambio de posición. Tenías razón y yo simplifiqué de más.**

No depende del HIS. Y mi argumento anterior —"los estados de PQRSDF son un enum"— era válido solo mientras hubiera un único trámite. Ya hay al menos seis: PQRSDF, solicitud de cita, historia clínica, certificados, habeas data, y los que aparezcan después.

Programar seis flujos independientes es peor que programar uno configurable.

**Pero no un BPM.** La distinción importa:

| | Motor de trámites configurable ✅ | BPM completo ❌ |
|---|---|---|
| Qué es | Tabla que define tipos de trámite: estados, campos requeridos, dependencia responsable, término legal, plantillas de notificación | Diseñador visual de procesos, ramificaciones condicionales, paralelismo, roles dinámicos, motor de reglas |
| Quién lo configura | Administrador desde el panel | Analista de procesos |
| Costo | Dos o tres tablas y un formulario de administración | Meses |
| Cuándo se justifica | Cuando hay más de tres trámites | Cuando hay procesos internos multi-área con aprobaciones cruzadas |

Lo que entra en v1 es la columna izquierda: `tipos_tramite`, `estados_tramite`, `campos_tramite`. Con eso el hospital crea un trámite nuevo desde el panel sin llamarte. Eso **sí** es hacer que el panel sea poderoso.

---

### B5. WhatsApp, chat e IA

Los agrupé mal. Son tres cosas con destinos distintos.

**WhatsApp — aplazar, pero por razones de terceros, no técnicas.**
Requiere cuenta de WhatsApp Business API, verificación de la empresa ante Meta, un proveedor autorizado (Twilio, 360dialog, o similar) y plantillas de mensaje aprobadas una por una. Es un trámite de semanas y con costo por mensaje. El hospital debe iniciarlo, no tú.
*Lo que sí entra gratis en v1:* enlace `wa.me` al número institucional en contacto y pie de página. Cuesta cero.

**Chat en vivo — descartar, y no por costo técnico.**
Un widget de chat sin nadie que responda es peor que no tenerlo: promete atención inmediata y entrega silencio. Es un problema de personal, no de software. Si el hospital designa personas con horario definido, se reconsidera. Mientras no, no.

**IA — aquí separo tajantemente, y creo que es la distinción más útil de todo el documento.**

| IA en el panel (para el funcionario) | IA en el sitio (para el ciudadano) |
|---|---|
| ✅ Resumen automático al subir un PDF | ❌ Chatbot que responde sobre servicios y horarios |
| ✅ Sugerencia de etiquetas y categorías | ❌ Asistente que orienta trámites |
| ✅ Generación de texto alternativo para imágenes (ayuda a cumplir WCAG) | |
| ✅ Sugerencia de metadatos SEO | |
| ✅ Redacción de borrador de noticia | |
| ✅ Búsqueda semántica sobre documentos | |
| **Riesgo si se equivoca:** el funcionario lo corrige antes de publicar | **Riesgo si se equivoca:** el hospital le dio información errónea a un paciente. Sobre horarios de urgencias. Por escrito |

La IA del panel es barata, útil, y el error queda contenido por revisión humana. La IA de cara al ciudadano en una entidad de salud es un riesgo institucional que no vale la pena en v1.

**Veredicto:** IA en el panel, opcional y desactivable, detrás de una interfaz que permita cambiar de proveedor. IA de cara al ciudadano, no.

---

## Parte C — Lo que faltaba y no habíamos contemplado

Del marco normativo de tu documento salieron obligaciones que yo había omitido. Estas **entran en v1 sin discusión**:

| Requisito | Norma | Qué implica |
|---|---|---|
| **Banner y política de cookies** | Ley 1581 / lineamientos SIC | Si usas Analytics, Maps o YouTube embebido, necesitas consentimiento previo y granular. No un banner decorativo: consentimiento real que bloquee los scripts hasta la aceptación |
| **Canal de habeas data** | Ley 1581, Decreto 1377 | Trámite específico para solicitar supresión, actualización, copia o revocación de autorización. Es distinto de PQRSDF y tiene términos propios |
| **Política de tratamiento de datos y aviso de privacidad** | Ley 1581 | Documentos publicados, versionados, con fecha de vigencia |
| **Autorización de datos en cada formulario** | Ley 1581 | Casilla no premarcada, con enlace a la política. **Guardar evidencia:** fecha, hora, IP, versión de la política aceptada |
| **Datos abiertos** | Ley 1712, Anexo 4 Res. 1519 | Sección con datasets descargables en formato abierto (CSV/JSON), no PDF |
| **Flujo de aprobación antes de publicar** | Buena práctica de auditoría | Borrador → revisión → publicado. En una entidad pública, quién aprobó qué publicación es información que la Contraloría pregunta |
| **Programación de publicación y despublicación** | Operativo | Una convocatoria debe cerrarse sola. Un aviso de campaña debe expirar solo |
| **Tablas de Retención Documental** | Archivo General de la Nación | Campo de serie y subserie documental en cada documento. Barato hoy, imposible de retrofitear después |
| **Enlace a SECOP** | Ley 1712 | La contratación se consulta en SECOP; el sitio enlaza, no duplica |
| **Manual de identidad institucional** | Interno | Restricción real de diseño. Pídelo antes de proponer paleta |
| **Seguridad: HSTS, CSP, rate limit, política de contraseñas** | Anexo 3 Res. 1519 | Configuración, no desarrollo, pero debe quedar documentada |
| **Backup y restauración desde el panel** | Continuidad | Con descarga y programación |
| **Preguntas frecuentes** | Res. 1519 (menú Atención a la Ciudadanía) | Barato y de altísimo valor real |

---

## Parte D — Tabla de decisión completa

### ✅ Entra en v1

| Funcionalidad | Justificación |
|---|---|
| Portal público completo (secciones del mapa del sitio) | Es el proyecto |
| Menús obligatorios Res. 1519: Transparencia, Atención a la Ciudadanía, Participa, Noticias | Obligatorio |
| Gestión documental con metadatos, versiones, vigencia, TRD | Es lo que diferencia el sitio del Joomla actual |
| Buscador unificado | Resuelve el problema real del ciudadano |
| PQRSDF completo con radicado, términos legales y consulta pública | Obligatorio |
| **Motor de trámites configurable** | Corregido. Seis trámites justifican un motor |
| **Solicitud de cita (no agendamiento)** | Corregido. Cero dependencia del HIS, alto valor |
| Solicitud de historia clínica, certificados, habeas data | Trámites sin dependencia externa |
| Cookies, política de datos, autorización con evidencia | Obligatorio |
| Datos abiertos | Obligatorio |
| Flujo de aprobación, programación, versionado de contenido | Auditoría |
| Directorio, especialidades, servicios, sedes, horarios | Lo que busca el ciudadano |
| Avisos y modo emergencia | Una tabla, altísimo valor en crisis |
| Convocatorias y vacantes | Obligatorio en transparencia |
| Preguntas frecuentes | Barato |
| Panel administrativo completo (ver Parte E) | Es el corazón del proyecto |
| Auditoría de todas las acciones | Obligatorio ante entes de control |
| Accesibilidad WCAG 2.1 AA | Obligatorio desde 2022 |
| PWA, SEO técnico, rendimiento móvil | Diferencial real |
| Redirecciones 301 desde Joomla | Sin esto rompes enlaces oficiales |
| **IA en el panel** (resumen, etiquetas, alt-text, SEO) | Bajo riesgo, alto valor, revisión humana |

### 🔸 Entra en versión reducida

| En vez de… | Construimos… | Por qué |
|---|---|---|
| BPM con diseñador visual | Motor de trámites configurable por tabla | 5% del costo, 80% del valor |
| Page builder tipo Elementor | Editor de bloques predefinidos | Un page builder libre produce páginas inaccesibles y rompe la identidad institucional |
| Mapa interactivo con georreferenciación | Plano del hospital en imagen + descripción textual accesible + Google Maps para llegar | Nadie navega un hospital desde el celular en la sala de espera |
| Centro de cumplimiento normativo | Alertas de vencimiento sobre `vigencia_hasta` y `periodicidad` | El modelo de datos ya lo soporta. El tablero se agrega después sin migración |
| Firma electrónica | Radicado con evidencia (fecha, IP, documento) | Suficiente para trámites de sitio web. La firma real es Ley 527 y entidades certificadoras |
| Chat en vivo | Formulario de contacto + WhatsApp `wa.me` + FAQ | Sin personal, el chat es una promesa incumplida |

### 🕐 Aplazar a v2 (con el modelo de datos preparado)

Cuenta de usuario ciudadano · Notificaciones por WhatsApp · Integración con HIS para citas · Encuestas de satisfacción automáticas · Tablero de cumplimiento normativo · Boletín por correo · Búsqueda semántica con IA

### ❌ Descartar, con justificación

| Descartado | Por qué |
|---|---|
| **Multi-tenant, SaaS, licenciamiento, feature flags por cliente** | Un hospital. Se extrae cuando aparezca el segundo cliente real, con problemas reales |
| **Marketplace, sistema de plugins, SDK público** | Infraestructura para un ecosistema de terceros que no existe |
| **Portal del colaborador / proveedor / contratista** | No son un sitio web. Requieren datos de nómina, tesorería y contratación, integración con ERP, y son sistemas internos con su propia seguridad. Es otro proyecto, y probablemente uno mejor pagado |
| **Historia clínica, facturación, LIS, PACS, farmacia, camas** | Regulados, maduros, con competidores establecidos. Nos integramos, no competimos |
| **Event bus, arquitectura hexagonal estricta, DDD, API Gateway, colas distribuidas** | Diseñados para desacoplar 200 módulos y escalar a 100 tenants. Ninguna de las dos condiciones aplica. Un despachador simple de eventos para notificaciones es suficiente y cabe en 100 líneas |
| **Data lake, data warehouse, ETL, Digital Twin** | El sitio generará miles de registros, no millones. Una consulta SQL responde lo que un data warehouse respondería |
| **Kioscos, pantallas digitales, sistema de turnos** | Requieren hardware, ubicación física, mantenimiento y procesos internos que no existen |
| **Modo offline con sincronización** | Un sitio informativo no necesita funcionar sin conexión. El *shell* de la PWA sí se cachea; los formularios offline agregan complejidad de sincronización sin caso de uso |
| **Telemedicina** | Resolución 2654 de 2019. Servicio de salud habilitado, no una funcionalidad web |
| **Internacionalización, lengua de señas** | El sitio prepara las claves de texto para no tener que reescribir después, pero traducir sin necesidad demostrada es trabajo sin retorno |
| **Chatbot de cara al ciudadano** | Riesgo institucional desproporcionado en una entidad de salud |

---

## Parte E — El panel administrativo

Acepto la ambición: **el panel debe ser tan completo que el hospital nunca tenga que llamarte para cambiar algo.** Ese es el criterio correcto, y es exactamente lo contrario de lo que tienen hoy con Joomla.

Pero conviene precisar qué hace poderoso a un panel. No es la cantidad de módulos. Es que **cubra el 100% de lo que cambia y que un funcionario sin formación técnica lo use sin manual.**

**Cobertura — nada requiere un desarrollador:**

Contenido (páginas, noticias, campañas, avisos) · Documentos con versiones y vigencia · Estructura completa de transparencia · Directorio, especialidades, servicios, sedes · Trámites: definición de tipos, campos, estados y términos · Bandeja de trámites con asignación y respuesta · Convocatorias · Menús y navegación · Biblioteca de medios · Formularios · Textos legales · Datos de contacto, horarios, redes · SEO por página · Usuarios, roles y permisos · Redirecciones · Auditoría · Copias de seguridad · Estado del sistema

**Ergonomía — lo que hace la diferencia frente a Joomla:**

Publicar una noticia en tres campos y un botón · Vista previa antes de publicar · Autoguardado · Búsqueda global en el panel · Acciones masivas · Historial con restauración a cualquier versión · Ayuda contextual en línea · Mensajes de error en español y comprensibles · Responsivo, porque el jefe de prensa va a publicar desde el celular

**Un matiz sobre la metáfora.** Dijiste que el panel es Dios y el sitio su trono. Lo entiendo, pero la relación de servicio va al revés: el panel existe **para que el sitio esté siempre actualizado y correcto.** Su poder se mide en cuánto le sirve al ciudadano, no en cuántas pantallas tiene.

Traducido a decisiones de presupuesto: si en algún momento hay que elegir entre una funcionalidad más del panel y que el sitio cargue en menos de dos segundos en un celular de gama baja con 3G en Valledupar, gana el sitio. Siempre.

---

## Parte F — Sobre "moderno"

Vale la pena responder esto de frente, porque hay una confusión común entre *moderno* y *de moda*.

**La decisión:** HTML renderizado en servidor con PHP 8.4, Tailwind CSS, Alpine.js para interactividad puntual. Sin React, sin SPA, sin build pesado.

**Por qué esto es más moderno, no menos, para este caso concreto:**

- Un sitio institucional público se mide por Core Web Vitals, accesibilidad y resiliencia. Una SPA de React entrega una pantalla en blanco hasta que descarga y ejecuta 300KB de JavaScript. En una conexión mala eso es el peor resultado posible.
- El buscador de Google, los lectores de pantalla y los enlaces compartidos por WhatsApp funcionan mejor con HTML servido. Para una entidad cuyo trabajo es *publicar información*, esto no es un detalle.
- La tendencia actual en desarrollo web va justamente en esa dirección: renderizado en servidor, mejora progresiva, menos JavaScript. Astro, HTMX y los server components de React existen porque la industria está regresando a este modelo.
- Y hay un argumento de sostenibilidad que pesa más de lo que parece: **quien mantenga este sitio en cinco años probablemente no seas tú.** Una base PHP + HTML + CSS se retoma. Un stack de JavaScript con 40 dependencias que no reciben actualizaciones desde hace tres años, no.

**Lo que sí debe ser moderno, y aquí no hay concesiones:**

CSS nativo actual (nesting, container queries, `clamp()` para tipografía fluida, capas) · View Transitions API para navegación suave · Imágenes en AVIF/WebP con `srcset` y carga diferida · Fuentes variables autoalojadas · Modo oscuro y modos de accesibilidad · PWA instalable · Formularios con validación nativa + progresiva · Diseño responsivo real, no un escritorio encogido · Un sistema de diseño con tokens, no CSS suelto · Panel con atajos de teclado, autoguardado y estados de carga bien resueltos

Un sitio puede ser impecablemente moderno y no tener una sola línea de React.

---

## Parte G — Cambios al modelo de datos

Respecto al documento anterior, se agregan:

**Trámites (reemplaza `pqrsdf` como tabla única):**
`tipos_tramite` · `tramite_estados` · `tramite_campos` · `tramites` · `tramite_seguimiento` · `tramite_adjuntos`
PQRSDF pasa a ser un tipo de trámite, no una tabla especial.

**Cumplimiento y datos personales:**
`politicas` (versionadas, con vigencia) · `autorizaciones_datos` (evidencia: trámite, versión de política, fecha, IP) · `consentimiento_cookies` · `datasets` (datos abiertos)

**Documentos — campos nuevos:**
`serie_documental` · `subserie_documental` · `tiempo_retencion` (Archivo General de la Nación)

**Publicación:**
`estado_aprobacion` (borrador/en_revision/aprobado/publicado) · `aprobado_por` · `publicar_en` · `despublicar_en` en todo contenido publicable

**Sistema:**
`menus` y `menu_items` (navegación editable) · `faq` · `respaldos`

---

## Parte H — Lo que hay que preguntarle al hospital antes de empezar

Ninguna de estas es técnica. Todas condicionan el alcance:

1. ¿Quién atiende la bandeja de solicitudes de cita, en qué horario y con qué plazo de respuesta?
2. ¿Qué HIS usan, con qué proveedor, y el contrato vigente incluye integraciones?
3. ¿Existe manual de identidad institucional?
4. ¿Quién aprueba una publicación oficial? ¿Prensa, gerencia, ambos?
5. ¿Tienen Tablas de Retención Documental adoptadas?
6. ¿Quién es el responsable de tratamiento de datos personales y hay política vigente?
7. ¿Dónde se aloja hoy el sitio y quién administra el dominio y el certificado?
8. ¿Usan Google Analytics u otra herramienta que dispare la obligación de cookies?
9. ¿Cuántas personas van a administrar el sitio y con qué perfil?
10. ¿Existe inventario de las URLs actuales del Joomla?

La respuesta a la 1 y a la 9 determina más el éxito del proyecto que cualquier decisión de arquitectura.

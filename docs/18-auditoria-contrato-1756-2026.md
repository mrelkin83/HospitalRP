# Auditoría de cumplimiento — Contrato de Prestación de Servicios 1756-2026

**Entidad:** E.S.E. Hospital Rosario Pumarejo de López (Valledupar, Cesar)
**Contratista:** Elkin Elías Liñán Marriaga — C.C. 17.595.269
**Supervisora:** María Camila Capataz Salgado — Jefe de Oficina Asesora de Planeación y Calidad
**Objeto:** Rediseño, desarrollo y puesta en producción del sitio web institucional, con alimentación, actualización permanente y mantenimiento, garantizando el cumplimiento de la Ley 1712 de 2014 y la Resolución MinTIC 1519 de 2020 en materia de publicación, accesibilidad, usabilidad y seguridad digital.
**Valor:** $11.501.667 — **Plazo:** 3 meses y 13 días desde el acta de inicio (19-ago-2026)

**Fecha de la auditoría:** 23 de octubre de 2026
**Avance del plazo:** 65 de 105 días (**62 %**); restan **40 días** (vencimiento estimado: 2-dic-2026)

---

## 1. Resumen ejecutivo

El desarrollo es **técnicamente sólido y está muy por encima del punto medio del contrato en construcción de software**, pero presenta **tres incumplimientos materiales** que, de no cerrarse antes del vencimiento, impiden certificar el objeto contractual.

| Dimensión | Estado |
| --- | --- |
| Obligaciones específicas cumplidas en su totalidad | **7 de 20** (35 %) |
| Cumplidas parcialmente | **9 de 20** (45 %) |
| Incumplidas o sin evidencia | **4 de 20** (20 %) |

### Los tres bloqueantes críticos

1. **El sitio no está en producción en el dominio institucional** (obligación 15 y núcleo del objeto contractual). `hrplopez.gov.co` resuelve hoy a `190.8.176.115` y sigue sirviendo el **Joomla anterior** (`/sitio`). El desarrollo nuevo vive en `3.145.176.69` con **certificado autofirmado**, que cualquier navegador marca como inseguro. Verificado el 23-oct-2026 a las 14:55 UTC.
2. **No existe módulo de respaldos ni procedimiento de restauración** (obligación 9, exigencia expresa del Anexo 3 de la Res. 1519/2020). La tabla `respaldos` figura en `database/esquema.sql` pero no fue migrada y no hay una sola línea de código que la use.
3. **El 34 % del esquema de publicación está vacío**: 91 de 266 criterios ITA en estado «espera dato» (obligación 11). Los 91 son contenido que debe entregar el hospital, pero la obligación de *alimentar* el esquema es del contratista y el mecanismo de escalamiento (obligación 19) está a medio construir.

### Lo que sí está construido y verificado

- **312 rutas** operativas (93 públicas + 219 del panel), 267 archivos PHP de código fuente, 120 vistas y 33 migraciones.
- **Menú de Transparencia completo** según el Anexo 2: 10 secciones raíz y **82 subsecciones** sembradas de forma idempotente.
- **1.328 pruebas automatizadas**. Las **935 unitarias se ejecutaron hoy con resultado OK (1.476 aserciones)**; las 393 funcionales no pudieron correrse en este equipo porque el servicio MySQL está detenido.
- **Cabeceras de seguridad completas** en el servidor de pruebas: CSP estricta, HSTS, `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy` y `Permissions-Policy`.
- **Módulo PQRSD con radicado único transaccional**, cálculo de días hábiles con festivos colombianos, consulta ciudadana por radicado y el informe trimestral de acceso a la información con descarga en CSV.
- **Barra institucional GOV.CO** obligatoria, 22 permisos granulares, 17 acciones auditadas y 72 redirecciones 301 desde las URL del Joomla.

---

## 2. Matriz de cumplimiento — Obligaciones específicas

Leyenda: ✅ cumple · ⚠️ cumple parcialmente · ❌ incumple o sin evidencia

### ✅ Obligación 1 — Diagnóstico frente a Ley 1712, Res. 1519 y matriz ITA

Existe `docs/08-inventario-sitio-actual.md` (inventario del Joomla), `docs/14-auditoria-sitio-actual-y-brechas.md` (brechas verificadas) y `docs/ita-2026-matriz-consolidada.csv` con los **266 criterios** clasificados por sección, peso, puntaje 2025, responsable y estado. El cronograma de cierre está en `docs/plan-de-desarrollo.md` y los desgloses por fase (`docs/03` a `docs/17`).

### ✅ Obligación 2 — Arquitectura de información, mapa de navegación y prototipos

`docs/01-mapa-del-sitio-y-modelo-de-datos.md` y `docs/16-frontend-prototipo.md`; la ruta `/diseno` expone el sistema de diseño vivo.

> **Observación:** no hay constancia documental de la **aprobación escrita de la supervisora**, que la obligación exige («someter a aprobación del supervisor»). Conviene levantar un acta retroactiva.

### ✅ Obligación 3 — Diseño adaptativo, identidad visual, usabilidad

Buscador interno (`/buscar`, módulo `Busqueda` con índice propio e indexador), mapa del sitio generado desde los menús vigentes (`/mapa-del-sitio`, `MapaSitioController`) y navegación jerárquica de máximo tres niveles. Identidad visual institucional aplicada con Tailwind v4 y tokens propios.

### ⚠️ Obligación 4 — Accesibilidad (Anexo 1 Res. 1519/2020 y NTC 5854)

**A favor:** enlace «Saltar al contenido» en las plantillas pública y del panel, `:focus-visible` con anillo de 3 px, `lang="es"`, landmark `<main>`, 12 `aria-label`, uso de `role="status"`/`role="alert"`, `sr-only` para texto solo-lector y campo `archivos.texto_alt` que el panel exige al subir imágenes.

**Brechas:**
- **No hay una sola prueba automatizada de accesibilidad** (no existe axe, pa11y ni Lighthouse CI en el repositorio); el cumplimiento WCAG 2.1 AA / NTC 5854 nivel AA está afirmado pero no medido de forma reproducible.
- El **certificado de accesibilidad firmado por el representante legal** (evidencia exigida por el criterio ITA 1.1.a) debe reemitirse.
- Los **documentos PDF de terceros** que suben las áreas no cumplen el Anexo 1; el sitio no puede garantizarlo por sí solo (criterio 1.1.i, marcado «fuera del sitio»).

### ⚠️ Obligación 5 — Menú de Transparencia con la totalidad del Anexo 2

**Estructura: completa.** `SembrarTransparenciaCommand` siembra 10 secciones raíz (1 a 9 y 11; la 10 se fusionó deliberadamente en 1.5 para no duplicar el directorio del sector) y **82 subsecciones**, con enlaces fijos a SECOP II, SUIN-Juriscol y los tres instrumentos de gestión.

**Contenido: incompleto.** Ver obligación 11.

### ⚠️ Obligación 6 — PQRSD conforme a la Ley 1755 de 2015

**A favor:** `GeneradorRadicado` con secuencia anual por prefijo y restricción `UNIQUE` sobre `tramites.radicado`; `RadicacionService` deja radicado, autorización de datos y notificación en **una sola transacción**; `CalculadoraDiasHabiles` + `FestivosColombia`; consulta ciudadana en `/atencion-ciudadania/pqrsdf/consultar`; trazabilidad por cambio de estado con notificación al peticionario; `AlertasVencimientoCommand` para términos por vencer; informe de acceso a la información publicado y descargable en CSV. Siete tipos de trámite sembrados, con radicación anónima habilitada para PQRSDF.

**Brechas de legalidad — atención prioritaria:**

- **Todos los términos están marcados como PROVISIONALES** en el código, a la espera de validación de la Oficina Jurídica. Textualmente: *«Ley 1755 de 2015, art. 14 — TÉRMINO PROVISIONAL, pendiente validación de la oficina jurídica»*.
- Más grave: el tipo `pqrsdf` aplica **un término único de 15 días hábiles** a las seis modalidades del desplegable (Petición, Queja, Reclamo, Sugerencia, Denuncia, Felicitación). El artículo 14 de la Ley 1755 de 2015 fija términos **diferenciados**: 15 días hábiles como regla general, **10 días hábiles** para peticiones de documentos e información, y **30 días hábiles** para consultas de criterio o concepto. Resolver una petición de documentos en 15 días constituye mora legal.
- El **canal de denuncias por corrupción** existe como línea telefónica y correo de denuncias (pie de página y `/contacto`) y como opción del desplegable PQRSDF, pero **no como canal radicado e independiente**. El artículo 76 de la Ley 1474 de 2011 y los criterios ITA del menú de Atención a la Ciudadanía esperan un canal diferenciado y visible.

### ⚠️ Obligación 7 — Contenidos propios del sector salud

| Contenido exigido | Estado |
| --- | --- |
| Portafolio de servicios | ✅ `/servicios`, `/especialidades`; Portafolio 2026 (24 láminas) volcado al sitio |
| Sedes y horarios | ✅ `/sedes`, `/sedes/{slug}`, tarjetas de sede en `/contacto` |
| Carta de derechos y deberes del paciente | ✅ `/atencion-ciudadania/deberes-y-derechos` |
| Asociación de usuarios | ✅ Sección 6.3 de Transparencia + página `/participa` |
| Resultados de satisfacción | ⚠️ Módulo `Encuestas` operativo con resultados públicos; falta cargar las mediciones reales |
| **Habilitación frente al REPS** | ❌ **No implementado** |

El portafolio se publica con nomenclatura ciudadana por decisión explícita de diseño, pero **no existe código de habilitación REPS por servicio, ni columna en el esquema, ni enlace al Registro Especial de Prestadores de Servicios de Salud**. La obligación dice literalmente «portafolio de servicios habilitados **frente al REPS**». Es la brecha más concreta y barata de cerrar de todo el informe.

### ✅ Obligación 8 — Datos abiertos y tratamiento de datos personales

`/transparencia/datos-abiertos` con tablas `datasets` y `dataset_recursos` (periodicidad, licencia por defecto «Datos abiertos - Ley 1712 de 2014», cobertura temporal) y descarga por recurso. Política de tratamiento en `/politica-tratamiento-datos`, política de privacidad web, de cookies y de derechos de autor. Trazabilidad de consentimiento en la tabla `autorizaciones_datos`, con **revocación registrada y auditada** (`revocar_autorizacion`) y trámite dedicado de habeas data con las cinco operaciones de los artículos 14 y 15 de la Ley 1581 de 2012. Enlace al perfil de la entidad en datos.gov.co configurable desde el panel.

### ❌ Obligación 9 — Condiciones de seguridad digital del Anexo 3

| Exigencia del Anexo 3 | Estado |
| --- | --- |
| Certificado SSL/TLS | ❌ **Autofirmado sobre IP**; el dominio institucional ni siquiera apunta al sitio nuevo |
| Gestión de roles y perfiles | ✅ 22 permisos granulares, roles administrables desde `/admin/roles`, barandas anti-bloqueo |
| Registro de auditoría | ✅ 17 acciones tipificadas (sesión, bloqueo, permisos, configuración, contenido), consultable en `/admin/auditoria` |
| **Respaldos automáticos** | ❌ **No existe** |
| **Procedimiento de restauración** | ❌ **No existe** |

Brechas adicionales de seguridad detectadas en la verificación:

- **El servidor de producción sirve contenido por HTTP plano sin redirigir a HTTPS.** `http://3.145.176.69/` responde `200 OK` con la página completa. La cabecera HSTS solo viaja por HTTPS, de modo que un visitante que llegue por HTTP queda expuesto a interceptación. Debe forzarse `301 → https://`.
- **MFA no implementado.** Las columnas `mfa_secreto` y `mfa_habilitado` existen en el esquema sin código detrás, pese a que el propio `docs/CLAUDE.md` §6 lo exige para los roles administrativos.
- **17 commits sin publicar** en `origin/main` (GitHub `mrelkin83/HospitalRP`). Todo el trabajo de octubre —ITA 2026, portafolio, política de seguridad y MSPI— existe solo en este equipo. Es un riesgo de pérdida total y afecta las obligaciones 17 y 20.

### ⚠️ Obligación 10 — Panel administrativo con control de versiones

`VersionadoService` guarda un *snapshot* JSON completo y restaurable en cada guardado, con número de versión protegido por transacción y `SELECT … FOR UPDATE`.

**Alcance insuficiente:** solo cubre **páginas, noticias y campañas**. Los **documentos** y los **ítems de Transparencia** —precisamente el contenido que audita la Procuraduría— **no tienen versionado**. El panel sí es operable por personal no técnico: 219 rutas administrativas, gestión de usuarios, roles, configuración y auditoría sin tocar consola ni base de datos.

### ❌ Obligación 11 — Alimentar la totalidad de la información obligatoria

Resultado del análisis de los 266 criterios de la matriz ITA 2026:

| Estado | Criterios | % |
| --- | ---: | ---: |
| Construido | 143 | 54 % |
| **Espera dato del área** | **91** | **34 %** |
| No aplica | 25 | 9 % |
| Bloqueado fuera del sitio | 6 | 2 % |
| Parcial | 1 | 0,4 % |

Las secciones más desatendidas son **Participa (41 criterios sin dato)**, **Planeación (23)** e **Información de la entidad (11)**. Los 91 criterios coinciden exactamente con los clasificados como «CONTENIDO»: **ni uno solo está bloqueado por código**. La obligación de alimentar es del contratista; la fuente documental es del hospital. Sin un escalamiento formal y fechado, el riesgo recae sobre el contratista.

Los 6 bloqueos externos están correctamente justificados: TRD sin convalidar por el AGN (3 criterios), semáforo de Participa pendiente de autorización de Supersalud por la intervención forzosa, planes de mejoramiento de rendición de cuentas no derivados y accesibilidad de PDF de terceros.

### ⚠️ Obligación 12 — Matriz ITA diligenciada y soportada

La matriz existe, está consolidada y clasifica cada criterio por responsable y tipo de cierre (código, contenido, semilla, área). **Está desactualizada frente al código**: el criterio 15.1.a figura como «PARCIAL — falta ruta pública y semilla», pero la ruta `/politica-de-seguridad-de-la-informacion` y la semilla `semillas:documentos-seguridad` ya existen desde el 6 de octubre. Debe refrescarse antes de radicarla ante la Procuraduría.

### ✅ Obligación 13 — Instrumentos de gestión de la información pública (art. 13 Ley 1712)

Los tres instrumentos están publicados como tablas consultables en línea y descargables en CSV:

- `/transparencia/instrumentos-de-gestion/registro-de-activos-de-informacion`
- `/transparencia/instrumentos-de-gestion/indice-de-informacion-clasificada-y-reservada`
- `/transparencia/instrumentos-de-gestion/esquema-de-publicacion-de-informacion`

Enlazados desde las subsecciones 7.3, 7.4 y 7.5 del menú de Transparencia.

### ⚠️ Obligación 14 — Migración, redirecciones y pruebas

| Componente | Estado |
| --- | --- |
| Redirecciones 301 desde el Joomla | ✅ 72 sembradas e idempotentes, con panel en `/admin/redirecciones` |
| Pruebas funcionales | ⚠️ 1.328 pruebas; **935 unitarias verificadas OK hoy**; las 393 funcionales no se ejecutaron (MySQL detenido en este equipo) |
| Pruebas de accesibilidad | ❌ Inexistentes |
| Pruebas de carga | ❌ Inexistentes |
| Pruebas de seguridad | ❌ Inexistentes; no hay CI, ni `composer audit`, ni SAST |
| Migración de contenidos y base de datos | ⚠️ Semilla del Joomla disponible; la carga masiva real con el hospital está pendiente |
| Respaldo previo a la migración | ❌ Ligado a la ausencia del módulo de respaldos |
| Verificación de cero enlaces rotos | ❌ Pendiente la pasada completa del rastreador |

El directorio `tests/E2E/` **está vacío**.

### ❌ Obligación 15 — Hosting, dominio, DNS, SSL y puesta en producción

Es el incumplimiento más grave porque coincide con el verbo central del objeto contractual: *puesta en producción del sitio web institucional*.

Verificación del 23-oct-2026, 14:55 UTC:

| Comprobación | Resultado |
| --- | --- |
| `nslookup hrplopez.gov.co` | `190.8.176.115` |
| `curl -I https://hrplopez.gov.co` | `301 → https://hrplopez.gov.co/sitio`, servidor **LiteSpeed** |
| Contenido servido en el dominio | `<meta generator="Joomla! - Open Source Content Management">` · título «Hospital Rosario Pumarejo de López - INICIO» |
| `curl -k https://3.145.176.69/` | `200 OK`, **nginx**, título «E.S.E. Hospital Rosario Pumarejo de López» — el sitio nuevo |
| Certificado de `3.145.176.69` | **Autofirmado** (requiere `-k`; el navegador interrumpe con advertencia) |
| `curl http://3.145.176.69/` | `200 OK` — **sin redirección a HTTPS** |

En términos prácticos: **el ciudadano que escribe `hrplopez.gov.co` sigue viendo el Joomla antiguo**. El desarrollo nuevo está desplegado y funcional, pero solo alcanzable por una IP cruda cuyo certificado los navegadores rechazan — lo que además rompe el flujo del portal de cuentas de cobro para contratistas externos.

### ⚠️ Obligación 16 — Mantenimiento preventivo y correctivo

El historial de 80 commits muestra mantenimiento correctivo real y documentado (corrección de referencias rotas, de un guion corrupto en el horario de sede, de fallos en cascada de la suite). **Falta** el rastreo sistemático de enlaces rotos y una bitácora formal de incidencias entregable a la supervisión.

### ⚠️ Obligación 17 — Documentación técnica, código comentado y manuales

**A favor:** 3.895 líneas de documentación técnica en `docs/`, con decisiones de diseño razonadas y trazabilidad por fase. El código fuente está comentado a un nivel notablemente alto, en español, explicando el *porqué* y no solo el *qué* (por ejemplo, el comentario de `ConversorPdfImagenes` justifica el uso de arreglo de argumentos en lugar de shell como medida anti-inyección).

**Brechas:**
- **No existe manual de usuario del panel ni manual de administrador.** El propio `docs/13-estado-y-pendientes.md` lo registra como pendiente. La documentación actual está escrita para desarrolladores, no para la secretaria que publica una noticia.
- El código fuente **no está entregado**: 17 commits sin subir al remoto.

### ❌ Obligación 18 — Capacitar y certificar al personal designado

Sin evidencia de ejecución. Figura como pendiente en `docs/13`. La obligación exige además **certificar** a los capacitados, lo que implica listado de asistencia, contenidos y constancias firmadas.

### ⚠️ Obligación 19 — Informe mensual del esquema de publicación con alertas

Existe la mitad del mecanismo: `TransparenciaRepository::obligatoriasVacias()` detecta las secciones obligatorias y aplicables sin ningún ítem publicado (incluidas sus hijas) y las muestra en el panel.

**Falta lo que la obligación pide explícitamente:** la consulta devuelve solo `codigo` y `nombre`, **sin la dependencia responsable**, y no hay generación ni exportación de un **informe mensual**. Es justamente el instrumento que protegería al contratista frente a los 91 criterios sin dato: sin informes mensuales fechados que identifiquen a la dependencia morosa, la omisión se le imputa a él.

### ❌ Obligación 20 — Entrega formal por acta

Pendiente, lo cual es **normal a 40 días del vencimiento**. Se registra para que no se pase por alto: credenciales, accesos administrativos, dominios, certificados y copias de respaldo, mediante acta, sin retener acceso alguno. Nótese que dos de los cuatro entregables (certificado válido y copias de respaldo) **hoy no existen**.

---

## 3. Cumplimiento de la normatividad colombiana

| Norma | Exigencia | Estado |
| --- | --- | --- |
| **Ley 1712 de 2014**, arts. 9-11 | Información mínima obligatoria publicada | ⚠️ Estructura completa, 34 % sin contenido |
| **Ley 1712 de 2014**, art. 13 | Registro de activos, índice de clasificada y reservada, esquema de publicación | ✅ Los tres publicados y descargables |
| **Ley 1712 de 2014**, art. 11 | Informe de solicitudes de acceso a la información | ✅ Publicado con descarga CSV |
| **Decreto 1081 de 2015**, Título 1 | Reglamentación del esquema de publicación | ✅ Estructura conforme |
| **Res. MinTIC 1519 de 2020, Anexo 1** | Accesibilidad WCAG 2.1 AA | ⚠️ Implementada, no medida ni certificada |
| **Res. MinTIC 1519 de 2020, Anexo 2** | Menú de Transparencia y articulación GOV.CO | ✅ 82 subsecciones + barra GOV.CO obligatoria |
| **Res. MinTIC 1519 de 2020, Anexo 3** | Seguridad digital: SSL, roles, auditoría, respaldos | ❌ **Sin SSL válido ni respaldos** |
| **Res. MinTIC 1519 de 2020, Anexo 4** | Datos abiertos y datos personales | ✅ Conforme |
| **NTC 5854** | Accesibilidad web nivel AA | ⚠️ Mismo estado que el Anexo 1 |
| **Ley 1581 de 2012** y Decreto 1377 de 2013 | Política de tratamiento, autorización y derechos del titular | ✅ Política publicada, consentimiento trazado y revocable, trámite de habeas data |
| **Ley 1755 de 2015**, art. 14 | Términos diferenciados de respuesta a peticiones | ❌ **Término único de 15 días; falta validación jurídica** |
| **Ley 1474 de 2011**, art. 76 | Canal de denuncias por corrupción | ⚠️ Teléfono y correo; sin canal radicado independiente |
| **Ley 1618 de 2013** | Accesibilidad para personas con discapacidad | ⚠️ Igual que Anexo 1 |
| **Ley 2052 de 2020** | Racionalización de trámites en línea | ✅ Siete trámites en línea con radicado |
| **Decreto 1008 de 2018 / dominio .gov.co** | Sitio institucional bajo dominio `.gov.co` | ❌ **El dominio sirve el sitio anterior** |
| **Ley 594 de 2000** (TRD) | Tablas de retención documental publicadas | ⛔ Bloqueo externo: sin convalidación del AGN |
| **Resolución 3100 de 2019** (habilitación) | Servicios habilitados frente al REPS | ❌ Sin trazabilidad REPS |

---

## 4. Plan de cierre recomendado — 40 días

Ordenado por riesgo contractual, no por esfuerzo.

### Semana 1 (24-oct a 31-oct) — desbloquear la producción

1. **Subir los 17 commits al remoto.** Quince minutos de trabajo; elimina el riesgo de pérdida total y es prerrequisito de las obligaciones 17 y 20.
2. **Solicitar por escrito a la supervisora** la decisión sobre el apuntamiento DNS de `hrplopez.gov.co` y el acceso al proveedor actual (LiteSpeed, `190.8.176.115`). Dejar constancia fechada: mientras el hospital no autorice el cambio de DNS, la obligación 15 está suspendida por hecho de la entidad, y esa constancia es la defensa del contratista.
3. **Forzar la redirección HTTP → HTTPS** en nginx. Corrección de dos líneas con impacto directo en el Anexo 3.
4. **Emitir los informes mensuales de agosto, septiembre y octubre** del esquema de publicación, identificando por nombre la dependencia responsable de cada uno de los 91 criterios sin dato. Es la obligación 19 y, a la vez, el blindaje frente a la obligación 11.

### Semana 2 (1-nov a 7-nov) — cerrar el Anexo 3

5. **Módulo de respaldos**: comando `respaldos:crear` (volcado de base de datos + `storage/`), política de retención, registro en la tabla `respaldos` y programación diaria.
6. **Restauración probada en entorno limpio**, con acta de la prueba. El Anexo 3 exige el *procedimiento*, no solo la copia.
7. **Certificado válido**: Let's Encrypt en cuanto exista nombre de dominio o subdominio resoluble. Si el hospital demora el DNS principal, proponer un subdominio intermedio (por ejemplo `nuevo.hrplopez.gov.co`) para no bloquear la obligación.

### Semana 3 (8-nov a 14-nov) — legalidad y contenido de salud

8. **Validación jurídica de los términos PQRSD** y separación de los términos del artículo 14 de la Ley 1755: 10 días hábiles para documentos e información, 15 como regla general, 30 para consultas. Requiere desdoblar el tipo `pqrsdf` o parametrizar el término por modalidad del desplegable.
9. **Canal de denuncias por corrupción diferenciado**, con radicado propio y anonimato garantizado.
10. **Trazabilidad REPS**: columna de código de habilitación en servicios, visible en la ficha pública, con enlace al REPS.
11. **Versionado de documentos e ítems de Transparencia**, extendiendo `VersionadoService` a esas dos entidades.

### Semana 4 (15-nov a 22-nov) — pruebas y transferencia

12. **Pruebas de accesibilidad automatizadas** (axe-core o pa11y sobre las rutas principales) y **certificado de accesibilidad** firmado por el representante legal.
13. **Pruebas de carga y de seguridad** sobre producción, con informe. Añadir `composer audit` e integración continua.
14. **Rastreo completo de enlaces rotos** con las 301 activas.
15. **Manual de usuario del panel y manual de administrador**, escritos para personal no técnico.
16. **Capacitación con listado de asistencia y certificación** del personal designado.

### Semana 5-6 (23-nov a 2-dic) — liquidación

17. Refrescar la matriz ITA con las evidencias finales y radicarla.
18. **Acta de entrega formal**: credenciales, accesos, dominio, certificados y copias de respaldo, con la declaración expresa de no retención de accesos (obligación 20).
19. Informe final de ejecución para la liquidación del contrato.

---

## 5. Riesgos para la liquidación

| Riesgo | Impacto | Mitigación |
| --- | --- | --- |
| El hospital no autoriza el cambio de DNS antes del 2-dic | **Alto** — impide certificar el objeto | Requerimiento escrito en la semana 1; dejar constancia del hecho de la entidad |
| Las 91 piezas de contenido no llegan de las dependencias | **Alto** — ITA bajo y obligación 11 incumplida | Informes mensuales con dependencia responsable nombrada (obligación 19) |
| Multa por mora: 0,1 % semanal del valor del contrato (cláusula 8ª) | $11.502 por semana | Cerrar los tres bloqueantes antes del 15-nov |
| Cláusula penal: 10 % del valor (cláusula 9ª) = $1.150.167 | Solo ante incumplimiento total | No es el escenario actual |
| Sin respaldos al momento de la entrega | **Alto** — entregable expreso de la obligación 20 | Prioridad de la semana 2 |

---

## 6. Conclusión

El trabajo de ingeniería está hecho y está bien hecho: 312 rutas, 1.328 pruebas, cabeceras de seguridad completas, un menú de Transparencia que cubre el Anexo 2 íntegro y un panel que un funcionario sin formación técnica puede operar. La calidad del código y de la documentación técnica supera con holgura lo que se esperaría de un contrato de $11,5 millones.

Lo que falta no es desarrollo: es **despliegue, respaldo, contenido y papeleo**. Los tres bloqueantes —dominio, respaldos y las 91 piezas de contenido— son resolubles dentro de los 40 días restantes, y dos de ellos dependen de decisiones del hospital, no del contratista. Esa dependencia debe quedar documentada por escrito **esta semana**: es la diferencia entre un contrato que se liquida sin observaciones y uno que termina discutiendo la multa de la cláusula 8ª.

---

*Auditoría elaborada el 23 de octubre de 2026 sobre el código fuente en `C:\laragon\www\hr` (rama `main`, 80 commits), la matriz ITA 2026 consolidada y la verificación en vivo de `hrplopez.gov.co` y `3.145.176.69`.*

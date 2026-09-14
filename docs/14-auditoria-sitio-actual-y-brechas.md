# Auditoría del sitio vigente (hrplopez.gov.co) y brechas del sitio nuevo — 2026-09-14

Rastreo automatizado de `https://hrplopez.gov.co/sitio/index.php/es/` con crawler
propio (profundidad 4, 450 URL recorridas de ~2.500 descubiertas, 0 errores de
red, 447 × HTTP 200 / 3 × HTTP 404) + extracción del árbol de menús completo del
Joomla y del esquema ITA de la página `/transparencia`.

Complementa —y en varios puntos **corrige**— a `08-inventario-sitio-actual.md`,
que se hizo con una lectura manual y parcial.

## 1. Tamaño real del sitio vigente

| Magnitud | Valor medido |
|---|---|
| URL internas descubiertas | ~2.500 (450 rastreadas en esta pasada) |
| Documentos enlazados | **1.769** (1.486 PDF · 140 DOC · 123 XLS/XLSX · 1 ZIP) |
| Dominios externos enlazados | 52 |
| Menú principal | 16 raíces, 3 niveles de profundidad, **~120 ítems** |
| Menú secundario (pie/lateral) | 14 ítems |
| Secciones ITA publicadas | **11 secciones, ~90 ítems numerados** |

Documentos por sección de origen: `informes` 907 · `transparencia` 216 ·
`contratacion` 198 · `meci` 145 · `rr-hh` 81 · `servicio-al-ciudadano` 67 ·
`prensahrpl` 58 · `nuestro-hospital` 26 · `normativa` 25 · `mipg` 12 ·
`plan-anual-de-adquisiciones` 12.

## 2. Menú del sitio vigente vs. menú sembrado en el sitio nuevo

El sitio nuevo siembra hoy (`SembrarMenusCommand`): **principal** (Inicio,
Servicios, Especialidades, Directorio médico, Sedes, Noticias, Transparencia),
**superior** (Transparencia, Atención y Servicios a la Ciudadanía, Participa) y
dos menús de pie. Son 7 + 3 + 8 ítems frente a los ~134 del Joomla.

### 2.1 Raíces del Joomla y su estado en el sitio nuevo

| Raíz del Joomla | Subítems | Estado en el sitio nuevo |
|---|---|---|
| INICIO | — | ✅ `/` |
| INFORMES | 13 | 🟡 Absorbido en `/transparencia/planeacion-presupuesto-informes`; sin ítem propio de menú |
| CONTRATACIÓN | 1 | 🟡 Sección 3 de Transparencia; sin ítem propio |
| HOSPITAL | 11 | ❌ **No existe sección institucional ni ítem de menú** |
| NORMATIVA | 7 | 🟡 `/transparencia/normatividad` (sección 2 con 4 ítems, el Joomla tiene 7) |
| RR HH | 16 | ❌ **No cubierto** |
| BUSCAR | — | ✅ `/buscar` |
| ATENCIÓN Y SERVICIOS A LA CIUDADANÍA | 10 | 🟡 `/atencion-ciudadania` cubre 3 de 10 |
| PARTICIPA | — | ⛔ **Ítem de menú sembrado, pero la ruta `/participa` responde 404** |
| TRANSPARENCIA | — | ✅ `/transparencia` |
| ETNOCULTURAL | — | ❌ No existe |
| IAMII | 25 | ❌ **No cubierto (la sección más grande que falta)** |
| CONTROL DISCIPLINARIO INTERNO | — | ❌ No existe |
| PRENSA | 3 | 🟡 Noticias sí; **Revista Conexión Salud y Galería no** |
| PETICIONES QUEJAS Y RECLAMOS | — | ✅ `/atencion-ciudadania/pqrsdf` |
| RESULTADOS (externo) | 1 | ❌ Falta el ítem de menú al sistema externo |

### 2.2 Menú secundario del Joomla

| Ítem | Estado |
|---|---|
| Plan Anual de Adquisiciones | 🟡 ítem 3.1 de Transparencia, sin acceso directo |
| Programas y Proyectos (inversión en salud) | ❌ |
| Noticias | ✅ |
| Niveles de Atención | ❌ (portafolio con camas, quirófanos, niveles II–IV) |
| Docencia, Servicio e Investigación | ❌ |
| MECI | ❌ (145 documentos) |
| Humanización | ❌ |
| Rendición de Cuentas | 🟡 ítem 6.1 de Transparencia |
| Información específica para grupos de interés | 🟡 sección 8 de Transparencia |
| Trámites y Servicios | ✅ `/atencion-ciudadania/tramites` |
| Conflicto de Intereses | ❌ |
| Galería de Imágenes | ❌ (sin ruta pública; `/admin/medios` existe) |
| Mapa del Sitio | ⛔ **Ítem de pie sembrado, ruta 404** |
| Pediatría | ❌ |

## 3. Brecha de Transparencia (ITA): 11 secciones / ~90 ítems vs. 9 / 28

El Joomla publica el esquema completo de la Ley 1712. El sitio nuevo siembra
9 secciones y 28 ítems (`SembrarTransparenciaCommand`). Faltan, como mínimo:

- **1. Información de la entidad** — 1.3 Estructura funcional · 1.4 Mapas y
  cartas descriptivas de procesos · 1.6.1 **Escalas salariales** · 1.8
  Agremiaciones y asociaciones · 1.9 Normas, formularios y protocolos de
  atención · 1.11 Mecanismo de presentación de PQR · 1.12 **Calendario de
  actividades** · 1.13 Decisiones que afectan al público · 1.14 Entes que lo
  vigilan · 1.15 **Hojas de vida** · 1.16 MSPI.
- **2. Normativa** — 2.1.2 Decreto único reglamentario · 2.1.4 Gaceta oficial
  departamental y nacional · 2.1.5 Políticas, lineamientos y manuales ·
  2.2 **Buscador de normas / SUIN**.
- **4. Planeación** — 4.3.1 Distribución presupuestal de inversión · 4.4
  Proyectos de inversión · 4.5 Informes de empalme · 4.7.2 y 4.7.3 Rendición de
  cuentas a Contraloría y a la ciudadanía · 4.8.1 Informe pormenorizado ·
  4.8.3 Informes de software · 4.9 Defensa pública y daño antijurídico ·
  4.10 **Informe trimestral de acceso a información, quejas y reclamos**.
- **6. Participa** — el Joomla tiene 13 ítems (6.1.2 a 6.13), el sitio nuevo 3.
  Faltan: estrategias de participación, plan anticorrupción, **convocatorias**,
  **calendario de participación**, **formulario de inscripción ciudadana**,
  canal de interacción, diagnóstico de problemas, presupuesto participativo,
  consulta ciudadana, colaboración e innovación, control social y la política
  institucional de participación.
- **7. Datos abiertos** — el sitio nuevo solo tiene 7.1. Faltan **7.4 Registro
  de Activos de Información**, **7.5 Índice de Información Clasificada y
  Reservada**, **7.6 Esquema de Publicación**, 7.7 Programa de Gestión
  Documental, **7.8 Tablas de Retención Documental**, 7.9 Registro de
  publicaciones, 7.10 Costos de reproducción, 7.12 Informe de PQRS. Estos son
  ítems de cumplimiento obligatorio y hoy no están modelados.
- **11. Sistema Integral de Gestión del Riesgo** — sección completa ausente
  (mapa de riesgos institucional).

## 4. Funciones (no solo páginas) que faltan

| Función en el Joomla | Implementación actual | Falta en el sitio nuevo |
|---|---|---|
| **Calendario de eventos** (componente JEvents, vista mensual y por día) | `/component/jevents/...` | Sí — y lo exigen los ítems ITA 1.12 y 6.5 |
| **Encuestas de satisfacción** (3 formularios JotForm embebidos) | `/servicio-al-ciudadano/nuestras-encuestas` | Sí (declarado fuera de v1 en CLAUDE.md §7 — confirmar con el hospital) |
| **Chat en vivo** (Chatroll) | `/servicio-al-ciudadano/chat` | Sí (fuera de v1 por CLAUDE.md §7) |
| **Acceso de usuarios / intranet** ("Ingresar al Sistema") | `/acceder-al-sistema` | Sí — el sitio nuevo solo tiene `/admin` |
| **Galería de imágenes** (Phoca Gallery) | `/galeria-de-imagenes`, `/prensahrpl/galeria` | Sí — no hay ruta pública de galería |
| **Revista Conexión Salud** | `/prensahrpl/revista-salud` | Sí |
| **Consulta de citas** | `/servicio-al-ciudadano/consulta-citas` (hoy vacía) | Parcial — `/citas` existe pero sin consulta |
| **Directorio de servidores públicos con hoja de vida SIGEP** (~150 fichas, 151 KB de contenido) | `/directorio` | Sí — el `/directorio` nuevo es de profesionales médicos, no de servidores públicos con escala salarial y enlace SIGEP |
| **Ofertas de empleo / convocatorias** | `/rr-hh/ofertas-de-empleo` | Sí — no hay módulo de convocatorias |
| **Glosario** (2.332 palabras) | `/servicio-al-ciudadano/glosario` | Sí |
| **Deberes y derechos de usuarios** | `/servicio-al-ciudadano/deberes-y-derechos-de-usuarios` | Sí |
| **Conciliaciones extrajudiciales** | `/conciliaciones` | Sí |
| **Mapa del sitio HTML** (4.344 palabras) | `/mapa-del-sitio` | Sí — ítem de menú sembrado sin ruta |
| **Certificado de accesibilidad** | `/nuestro-hospital/certificado-de-accesibilidad` | Sí |
| Buscador · descarga de documentos · PQRSDF · noticias | — | ✅ ya cubiertos |
| Contador de visitas | módulo Vinaora | No migra (decidido) |

## 5. Defectos verificados en lo ya construido

1. **Ítems de menú sembrados que responden 404.** `SembrarMenusCommand` siembra
   `/participa` (protegido, obligatorio por Res. 1519), `/mapa-del-sitio` y
   `/notificaciones-judiciales`. Ninguna de las tres tiene ruta fija; dependen
   del comodín `/{ruta}` → `PaginasController::mostrar`, y **no existe ningún
   sembrador de páginas**, así que hoy las tres devuelven 404.

2. **Redirecciones 301 apuntando a prefijos que no existen en el Joomla.**
   `SembrarRedireccionesCommand` mapea `/el-hospital`, `/el-hospital/*`,
   `/prensa`, `/prensa/*`, `/servicios` y `/servicios/*`. Las rutas reales
   medidas en el rastreo son **`/nuestro-hospital/*`** (13 páginas),
   **`/prensahrpl/*`** (7 páginas) y **`/servicio-al-ciudadano/servicios`**.
   Esas seis reglas no capturan tráfico real.

3. **Prefijos reales del Joomla sin regla de redirección:** `/rr-hh/*` (50 URL),
   `/iamii/*` (25), `/nuestro-hospital/*` (13), `/meci`, `/mipg`,
   `/prensahrpl/*` (7), `/docencia/*` (6), `/peticiones-quejas-y-reclamos/*`,
   `/programas-y-proyectos/*`, `/tramites-y-servicios/*`, `/cinteres/*`,
   `/etnocultural/*`, `/controldisciplinariointerno/*`, `/niveles-de-atencion/*`,
   `/rendicion-cuentas/*`, `/plan-anual-de-adquisiciones/*`, `/conciliaciones`,
   `/galeria-de-imagenes`, `/mapa-del-sitio`, `/participa`, `/directorio`,
   `/buscar`, `/noticias`, `/humanizacion`, `/pediatria`, `/banco-de-sangre`,
   `/informacion-para-ninos-2`. Caen todas al comodín `/sitio/*` → `/`, es decir
   pierden el destino específico.

## 6. Propuesta de menú para el sitio nuevo

Menú principal (7 raíces, máximo 2 niveles):

1. **El Hospital** — Historia · Misión y visión · Objetivos y funciones ·
   Principios corporativos · Organigrama · Infraestructura · Niveles de atención ·
   Localización · Agremiaciones y asociaciones · Certificado de accesibilidad
2. **Servicios** — Portafolio de servicios · Especialidades · Directorio médico ·
   Sedes y horarios · Urgencias
3. **Programas** — IAMII (10 pasos + Banco de Leche, Madre Canguro, Excelencia
   IAMII, Reconocimientos, Educación en salud) · Humanización · Docencia e
   investigación · Espacio etnocultural · Banco de sangre · Pediatría
4. **Atención a la Ciudadanía** — PQRSDF · Consulta de radicado · Trámites y
   servicios · Citas · Preguntas frecuentes · Glosario · Deberes y derechos ·
   Encuestas · Contáctenos · Notificaciones judiciales
5. **Transparencia** — las 11 secciones ITA completas
6. **Participa** — los 13 ítems de la sección 6
7. **Prensa** — Noticias · Boletines · Revista Conexión Salud · Galería ·
   Calendario de eventos

Barra superior: Transparencia · Atención y Servicios a la Ciudadanía · Participa ·
Buscar · Resultados de imagenología (externo) · Acceso al sistema.

Pie: Mapa del sitio · Políticas (privacidad, tratamiento de datos, cookies,
accesibilidad) · Datos abiertos · Talento humano (convocatorias, manual de
funciones, asignaciones salariales) · Control disciplinario interno ·
Conflicto de intereses · MECI/MIPG · Conciliaciones.

## 7. Backlog derivado, por prioridad

**P0 — rompe cumplimiento o produce 404 hoy**
1. Sembrador de páginas institucionales para `/participa`, `/mapa-del-sitio` y
   `/notificaciones-judiciales` (hoy 404 desde un menú protegido).
2. Corregir los prefijos de `SembrarRedireccionesCommand` (`nuestro-hospital`,
   `prensahrpl`, `servicio-al-ciudadano/servicios`) y añadir las ~27 reglas
   faltantes.
3. Completar el Anexo 2: secciones 6 (13 ítems), 7 (12 ítems), 11 y los ítems
   faltantes de 1, 2 y 4.
4. Directorio de servidores públicos con escala salarial y enlace SIGEP (ITA 1.6
   y 1.6.1) — distinto del directorio médico ya construido.

**P1 — secciones grandes sin equivalente**
5. Sección institucional "El Hospital" (11 páginas).
6. Módulo IAMII (25 páginas jerárquicas).
7. Talento humano: convocatorias/ofertas de empleo, manual de funciones,
   asignaciones salariales, evaluación de desempeño, PIC, bienestar, formatos.
8. Calendario de eventos (exigido por ITA 1.12 y 6.5).
9. Niveles de atención + portafolio de servicios como contenido, no solo catálogo.

**P2 — contenido administrable con los módulos que ya existen**
10. Glosario, deberes y derechos, MECI, MIPG, conflicto de intereses, control
    disciplinario, docencia, humanización, etnocultural, programas y proyectos,
    conciliaciones → páginas + documentos, sin código nuevo.
11. Galería pública y Revista Conexión Salud → módulo Medios + una ruta pública.
12. Ítem de menú al sistema externo de resultados de imagenología.

**Pendientes de decisión del hospital**
- Chat en vivo y encuestas automáticas: hoy fuera de v1 por CLAUDE.md §7, pero
  existen en el sitio vigente y su retiro debe ser una decisión explícita.
- "Acceder al sistema" (intranet de usuarios): confirmar si se reemplaza por el
  enlace al sistema externo o se rehace.

## 8. Cómo reproducir esta auditoría

Crawler y scripts de extracción en el scratchpad de la sesión
(`audit/crawl.mjs`, `audit/menu.mjs`, `audit/ita.mjs`); `crawl.json` guarda las
450 páginas con estado HTTP, títulos, H1, formularios, iframes y los 1.769
documentos con su página de origen. Para ampliar la cobertura, subir el segundo
argumento de `crawl.mjs` (`node crawl.mjs crawl.json 2500`).

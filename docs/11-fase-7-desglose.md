# Fase 7 — Búsqueda, SEO, PWA y accesibilidad · desglose ejecutable

**Inicio:** 2026-08-21 · Fase 6 terminada en `cd51064`.

| # | Paso | Verificable cuando… | Estado |
|---|---|---|---|
| 1 | Migración: `indice_busqueda`, `busquedas_log`, `redirecciones` | Sube y baja sin error | ✅ up→down→up verificado |
| 2 | Índice unificado: reconstrucción total por comando + reindexación por evento en los servicios editoriales (noticias, páginas, documentos) | Publicar una noticia la deja buscable sin correr nada a mano | ✅ hooks en servicios editoriales, 7 controladores admin y programador; `busqueda:indexar` como red de seguridad |
| 3 | Buscador global `/buscar`: FULLTEXT sobre el índice, resultados agrupados por tipo, registro de búsquedas (las sin resultado alimentan FAQ) | «certificado laboral» devuelve el trámite de certificados | ✅ test + E2E HTTP; prefijo booleano (sin stemming en MySQL); FAQ pública nueva en /atencion-ciudadania/preguntas-frecuentes |
| 4 | Redirecciones 301: tabla con contador de hits, resolución en el 404 del Kernel y semilla con las URLs del Joomla (inventario docs/08) | Una URL vieja del Joomla responde 301 hacia la nueva | ✅ exactos + prefijos `*` con contador de hits; resolución en Kernel y en el catch-all; semilla `semillas:redirecciones`; E2E verificado |
| 5 | SEO: robots.txt, sitemap.xml dinámico, canonical, OpenGraph y JSON-LD `Hospital`/`MedicalOrganization` | Lighthouse SEO ≥ 95 en móvil | ✅ construido (robots, sitemap desde el índice, canonical, OG, JSON-LD Hospital); medición en el cierre |
| 6 | PWA: manifest, iconos, service worker con cascarón offline, instalable | Lighthouse detecta la PWA instalable | |
| 7 | Accesibilidad: alternador «reducir movimiento» (completa los modos de la Res. 1519) + auditoría estructural WCAG de las cinco rutas principales | Lighthouse accesibilidad = 100 | |
| 8 | Cierre: Lighthouse móvil (rendimiento ≥ 90, a11y = 100, SEO ≥ 95) y criterios de la fase | Los cuatro criterios se cumplen | |

**Notas de alcance**

- La auditoría con lector de pantalla REAL (NVDA/TalkBack) la debe hacer una
  persona; aquí se garantiza la base estructural (semántica, ARIA, foco,
  nombres accesibles) y queda documentado el guion de las cinco rutas.
- El service worker cachea el cascarón y da página offline; NO cachea el
  panel ni los trámites (datos personales).
- La instalación real en Android exige HTTPS: verificable en producción;
  en local se valida con Lighthouse.

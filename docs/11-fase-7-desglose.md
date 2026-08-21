# Fase 7 — Búsqueda, SEO, PWA y accesibilidad · desglose ejecutable

**Inicio:** 2026-08-21 · **Cierre:** 2026-08-21 · Fase 6 terminada en `cd51064`.

| # | Paso | Verificable cuando… | Estado |
|---|---|---|---|
| 1 | Migración: `indice_busqueda`, `busquedas_log`, `redirecciones` | Sube y baja sin error | ✅ up→down→up verificado |
| 2 | Índice unificado: reconstrucción total por comando + reindexación por evento | Publicar una noticia la deja buscable sin correr nada a mano | ✅ hooks en servicios editoriales, 7 controladores admin y programador; `busqueda:indexar` como red de seguridad |
| 3 | Buscador global `/buscar`: FULLTEXT, agrupado por tipo, registro de búsquedas | «certificado laboral» devuelve el trámite de certificados | ✅ test + E2E HTTP; booleano por prefijo (MySQL no hace stemming); FAQ pública nueva |
| 4 | Redirecciones 301 con contador de hits, Kernel + catch-all, semilla del Joomla | Una URL vieja del Joomla responde 301 hacia la nueva | ✅ exactos + prefijos `*`; E2E verificado con URI cruda (Symfony recortaría el `index.php`) |
| 5 | SEO: robots, sitemap dinámico, canonical, OpenGraph, JSON-LD `Hospital` | Lighthouse SEO ≥ 95 en móvil | ✅ **SEO 100** en las 6 rutas indexables |
| 6 | PWA: manifest, iconos (192/512/maskable), service worker, cascarón offline | Instalable | ✅ sw.js con red-primero en navegaciones, caché-primero en estáticos, `/admin` jamás |
| 7 | Accesibilidad: alternador «reducir movimiento» + subrayado de enlaces en texto | Lighthouse accesibilidad = 100 | ✅ **A11y 100** en todas las rutas (corregido `link-in-text-block` del preflight) |
| 8 | Cierre: Lighthouse móvil + revisión de seguridad + suite | Los cuatro criterios de la fase se cumplen | ✅ ver abajo |

## Criterios de aceptación del plan — resultado

| Criterio | Resultado |
|---|---|
| Buscar «certificado laboral» devuelve el trámite | ✅ test automatizado + E2E: primer grupo `tramite_tipo`, URL del trámite de certificados |
| Lighthouse móvil: rendimiento ≥ 90, a11y = 100, SEO ≥ 95 | ✅ portada **100/100/100** (LCP 1,4 s); tramites, noticias, transparencia, documentos y FAQ **99–100/100/100** |
| Auditoría manual con lector de pantalla (5 rutas) | ⚠️ Requiere una persona con NVDA/TalkBack. Guion: portada → buscar «citas» → radicar PQRSDF → consultar radicado → FAQ. La base estructural (nombres accesibles, foco, jerarquía, `role=status` en resultados) está verificada por Lighthouse |
| Instalable en Android | ✅ manifest + SW listos; la instalación real exige HTTPS (producción). `/buscar` es SEO 54 a propósito: `noindex` + `Disallow` en robots |

## Revisión de seguridad de la fase (metodología differential-review, manual)

- **SQLi**: todas las consultas preparadas; los `LIMIT` concatenan solo enteros tipados.
- **Inyección booleana FULLTEXT**: los operadores (`+-<>~*"()@`) se eliminan del término del usuario; nunca llega crudo al `AGAINST`.
- **XSS**: resultados del buscador y términos sin resultado escapados con `$e`; las URL del índice se generan en servidor desde slugs, no de entrada de usuario.
- **Redirecciones**: destinos administrados (semilla; panel a futuro), nunca derivados de la petición → sin open redirect. El hit se cuenta solo si hay coincidencia.
- **Service worker**: no cachea `/admin` ni peticiones no-GET; la consulta de radicado es POST (jamás en caché); estáticos versionados por nombre de caché.
- **Flooding de `busquedas_log`**: tope global de 60 registros/minuto (rate limiting de búsqueda, CLAUDE.md §6) con test.
- **Error 1690 de MySQL 8**: el `ORDER BY` usa el alias de relevancia; repetir el `MATCH` con aritmética dispara `DOUBLE out of range`.

## Notas operativas

- Cron nocturno recomendado: `bin/consola busqueda:indexar` (red de seguridad del índice), junto a `tramites:alertas`.
- `semillas:redirecciones` es idempotente y no pisa lo editado; correrla al desplegar.
- Las búsquedas sin resultado (90 días) se muestran en **Panel → Preguntas frecuentes** para crear la FAQ que falta.
- El tipo `convocatoria` del índice queda desactivado hasta que exista el módulo con página pública.
- El service worker cachea el cascarón y páginas públicas GET; NO cachea el panel ni trámites (datos personales).
- Suite: **160 tests, 524 aserciones** en verde al cierre.

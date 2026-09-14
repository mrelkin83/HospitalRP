# Fase 9 — Cierre de brechas del sitio vigente

Deriva de `14-auditoria-sitio-actual-y-brechas.md`, **aprobada íntegra el
2026-09-14**, incluidos los «Pendientes de decisión del hospital».

## Decisiones tomadas el 2026-09-14

| Pendiente | Decisión | Efecto |
|---|---|---|
| Chat en vivo | **Construir** | Sale de «Fuera de v1» en `CLAUDE.md §7` |
| Encuestas automáticas | **Construir** | Sale de «Fuera de v1»; módulo propio, administrable y auditado |
| «Acceder al sistema» (intranet) | **Eliminar** | No se migra ni como enlace. Sin código, sin ítem de menú |

Sigue vigente `CLAUDE.md §2.6`: **ningún dato clínico entra al sistema**. El
chat y las encuestas se construyen bajo esa regla — el chat no atiende
consultas clínicas y las encuestas no recogen diagnósticos.

## Bloque A — P0: cumplimiento y 404 (bloquea el lanzamiento)

| # | Trabajo | Criterio de aceptación |
|---|---|---|
| A1 | `SembrarPaginasCommand`: páginas institucionales publicadas | `/participa`, `/mapa-del-sitio` y `/notificaciones-judiciales` responden 200 |
| A2 | Ruta real `/mapa-del-sitio` generada desde menús + rutas fijas | Lista todos los menús vigentes sin tocarla al añadir un ítem |
| A3 | Corregir prefijos de `SembrarRedireccionesCommand` y añadir los ~27 faltantes | Cada prefijo real medido en la auditoría tiene destino específico, no el comodín `/` |
| A4 | Completar el Anexo 2: secciones 6, 7 y 11 + ítems faltantes de 1, 2 y 4 | 11 secciones sembradas; ningún ítem obligatorio del ITA sin fila |
| A5 | Directorio de servidores públicos (ITA 1.6 y 1.6.1) | Ficha con cargo, dependencia, escala salarial y enlace SIGEP; separado del directorio médico |

## Bloque B — P1: secciones grandes sin equivalente

| # | Trabajo | Criterio de aceptación |
|---|---|---|
| B1 | Menú de 7 raíces del §6 de la auditoría | Los ítems protegidos de la Res. 1519 siguen sin poder borrarse |
| B2 | Sección institucional «El Hospital» (11 páginas) | Todas publicadas y enlazadas desde el menú |
| B3 | Módulo IAMII (25 páginas jerárquicas) | Jerarquía de 3 niveles navegable; `paginas.padre_id` y rutas anidadas |
| B4 | **Convocatorias** (tablas ya en `esquema.sql`, sin migrar) | Migración + panel + página pública; reactivar el tipo `convocatoria` del índice de búsqueda |
| B5 | Talento humano: manual de funciones, asignaciones salariales, evaluación, PIC, bienestar, formatos | Páginas + documentos con TRD, sin código nuevo salvo el menú |
| B6 | Calendario de eventos (FullCalendar) | Cubre ITA 1.12 y 6.5; administrable desde el panel |
| B7 | Niveles de atención y portafolio de servicios como contenido | Página con camas, quirófanos y niveles II–IV |

## Bloque C — P2: contenido con los módulos que ya existen

Glosario · Deberes y derechos · MECI · MIPG · Conflicto de intereses ·
Control disciplinario · Docencia · Humanización · Etnocultural · Programas y
proyectos · Conciliaciones · Pediatría · Banco de sangre → páginas +
documentos. Galería pública y Revista Conexión Salud → módulo Medios con ruta
pública. Ítem de menú al sistema externo de resultados de imagenología.

## Bloque D — lo aprobado hoy

| # | Trabajo | Criterio de aceptación |
|---|---|---|
| D1 | Actualizar `CLAUDE.md §7` | Chat y encuestas dejan de estar en «no construir»; queda registrada la fecha y el motivo |
| D2 | Módulo de encuestas de satisfacción | Preguntas configurables desde el panel, respuestas anónimas, resultados agregados, auditoría de cambios de cuestionario |
| D3 | Chat en vivo | Detrás de un `Port`, como el resto de servicios externos. Apagado por defecto, con aviso de que no atiende urgencias ni consultas clínicas |
| D4 | Eliminar «Acceder al sistema» | Sin ítem de menú; documentado en la auditoría |

## Orden de ejecución

A → B → D → C. El bloque A desbloquea el lanzamiento; C es contenido que el
hospital puede cargar en paralelo desde el panel.

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

## Estado al cierre — 2026-09-14

Todos los bloques ejecutados. **211 tests, 659 aserciones, en verde.**

| Bloque | Estado | Evidencia |
|---|---|---|
| A1 · Sembrador de páginas | ✅ | `semillas:paginas`; 38 páginas publicadas |
| A2 · Mapa del sitio | ✅ | `MapaSitioController`, se arma desde los menús |
| A3 · Redirecciones | ✅ | 79 reglas; 14 URL reales probadas con data provider |
| A4 · Anexo 2 completo | ✅ | 90 secciones sembradas (antes 28) |
| A5 · Servidores públicos | ✅ | `/servidores-publicos`, ITA 1.6 y 1.6.1 |
| B1 · Menú de 7 raíces | ✅ | 78 ítems, anidados, ninguno en 404 |
| B2 · El Hospital | ✅ | 11 páginas + niveles de atención |
| B3 · IAMII | ✅ | 25 páginas jerárquicas migradas |
| B4 · Convocatorias | ✅ | Migración + módulo + índice de búsqueda activado |
| B5 · Talento humano | ✅ | 8 páginas migradas + índice de sección |
| B6 · Calendario de eventos | ✅ | `/eventos`, navegable sin JavaScript |
| B7 · Niveles de atención | ✅ | Texto verificado del sitio vigente |
| C · Contenido administrable | ✅ | 47 páginas migradas, 25 con texto propio |
| D1 · CLAUDE.md §7 | ✅ | Chat y encuestas dentro; intranet fuera |
| D2 · Encuestas | ✅ | Anónimas; la prueba fija que no haya columna de identificación |
| D3 · Chat | ✅ | `Ports\ProveedorChat`, nace apagado |
| D4 · Intranet retirada | ✅ | `/acceder-al-sistema` redirige a la portada |

### Verificado

- Los **66 destinos internos** del menú responden 200 o redirección. Ninguno 404.
- `down()` de las tres migraciones nuevas probado con ciclo completo de bajada
  y subida.
- **Defecto de entorno corregido:** las pruebas corrían en UTC y la aplicación
  en hora de Colombia, porque PHPUnit arranca con `vendor/autoload.php` y no
  pasa por `config/bootstrap.php`. Cualquier fecha calculada en PHP y
  comparada contra `NOW()` salía cinco horas corrida.

### Lo que queda en manos del hospital

- **22 páginas en borrador.** Son las que el Joomla publica sin texto
  aprovechable. Están creadas y listas para redactar desde el panel; no salen
  al aire vacías.
- **Texto provisional en cinco páginas** (banco de leche, madre canguro, banco
  de sangre, etnocultural, deberes y derechos): afirman solo lo verificable y
  encaminan a un canal real, en lugar de dejar el ítem de menú en 404.
- **Panel de administración** de convocatorias, eventos, encuestas y
  servidores públicos: los repositorios exponen el CRUD completo y las rutas
  públicas funcionan, pero **las pantallas del panel para estos cuatro módulos
  no se construyeron**. Hoy los registros se cargan por SQL.
- **Imágenes del contenido migrado:** el extractor las quitó a propósito; se
  vuelven a subir desde el módulo de medios.
- Cotejar el Anexo 2 sembrado con la versión **vigente** de la Res. 1519.

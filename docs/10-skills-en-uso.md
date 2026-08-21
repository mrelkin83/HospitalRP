# Skills en uso — decisión 2026-08-21 (supersede la decisión C del plan de arranque)

Hay **352 skills instaladas a nivel de usuario** en `~/.claude/skills/`.
La decisión C («no usar skills de terceros») queda superada por instrucción
del usuario: estas skills SÍ se usan, con los roles de abajo. No aparecen en
la lista de skills invocables del entorno, así que se leen y aplican
directamente desde su `SKILL.md`.

## Mapeo de lo pedido → lo instalado

| Pedido | Skills instaladas | Rol en este proyecto |
|---|---|---|
| IMPECCABLE DESIGN | `impeccable` (v4.1.1, con playbooks en `reference/`) | **El auditor.** Auditoría técnica de 5 dimensiones (a11y, rendimiento, theming, responsivo, integridad) al cerrar cada fase con UI, y su *craft floor* como piso de calidad al construir pantallas |
| TASTE / ihlamury design-skills | `design-taste-frontend`, `emil-design-eng`, `*-ui-skills` (referencias por marca) | Consulta puntual de patrones; el brief (`frontend.png` + tokens) manda sobre el gusto |
| trailofbits/skills | `differential-review`, `fp-check`, `semgrep`*, `codeql`*, `variant-analysis`, `audit-*` | **Revisión de seguridad por fase**: differential-review sobre el diff antes del commit de cierre; fp-check para descartar falsos positivos. *semgrep/codeql requieren binarios no instalados aún |
| Karpathy | `karpathy-guidelines` | **Disciplina de cambios**: supuestos explícitos, mínimo código, cambios quirúrgicos, criterios verificables. Coincide con CLAUDE.md §8 y se aplica en todo cambio |
| obra/superpowers | `tdd`, `test-driven-development`, `systematic-debugging`, `verification-before-completion`, `writing-plans`, `using-superpowers` | Proceso: test primero en lógica de negocio (ya es la norma), verificación antes de dar por terminado, depuración sistemática ante bugs raros |
| GARRYTAN/gstack | `gstack`, `gstack-*` | Revisiones de producto (CEO/retro/office-hours); útil en cierres de fase mayores, no en el día a día |
| HYPERFRAMES | `hyperframes`, `hyperframes-*` | Motor de animación/video. **No aplica al sitio** (presupuesto de JS y sobriedad institucional); disponible para piezas audiovisuales de prensa si el hospital las pide |
| FIND SKILL | `find-skill` | Meta: localizar la skill correcta ante una tarea nueva |
| AGENT REACH | `agent-reach` | Alcance/orquestación de agentes; puntual |
| mattpocock/skills | `ask-matt`, `setup-ts-deep-modules`… | TypeScript; poco aplicable (el proyecto es PHP), disponible para el JS del sitio |
| emilkowalski/skills | `ask-sonner`, `animation-vocabulary`, `animate` | Vocabulario de motion cuando se autoricen animaciones (dentro del presupuesto) |
| affaan-m/ECC | `unified-memory`, `strategic-compact`, `verification-loop`, `autoplan`… | Proceso de agente; se usan de forma oportunista |

## Aplicación inmediata (2026-08-21)

Primera auditoría `impeccable` sobre el sitio público construido (Fases 1–6):

| # | Dimensión | Puntaje | Hallazgo clave |
|---|---|---|---|
| 1 | Accesibilidad | 4 | Salto al contenido, foco visible, labels, alt obligatorio, contraste probado por test |
| 2 | Rendimiento | 4 | LCP 2,4 s en 3G medido; JS público 4 KB; imágenes lazy |
| 3 | Responsivo | 4 | Verificado por capturas; áreas táctiles 44 px |
| 4 | Theming | 4 | Tokens en un solo archivo; claro/oscuro/alto contraste |
| 5 | Integridad | 3 | Correcciones aplicadas (ver abajo) |
| **Total** | | **19/20** | **Excelente (pulido menor)** |

**Correcciones aplicadas del craft floor:**
1. `prefers-reduced-motion` ya no es un *kill* de 0.01ms: conserva los
   cambios de estado (50ms) y no romperá indicadores de carga futuros.
2. Superficies del navegador tematizadas: `::selection`, `accent-color`
   (casillas y radios en azul institucional) y `caret-color`.
3. Emoji «✓» reemplazado por icono SVG del sistema (home y página de
   radicado exitoso): los iconos se dibujan, no se emulan con Unicode.

**Revisión de seguridad (metodología differential-review, manual porque
semgrep no está instalado):** diffs de las Fases 5–6 revisados; hallazgos
corregidos en su momento (cookie `Secure`, modelo de confianza de la
revocatoria documentado). `/security-review` integrada queda pendiente de
que el repo tenga remoto.

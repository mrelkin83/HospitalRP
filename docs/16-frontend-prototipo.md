# Frontend nuevo: adaptación del prototipo — 2026-09-14

El hospital entregó un prototipo en `hospital-prototype` y pidió adoptarlo como
frontend. Sustituye a `frontend.png` como referencia de diseño (decisión F del
plan de arranque).

## Qué se adaptó y qué no

El prototipo es una SPA de **React + Vite + lucide-react**. `CLAUDE.md §3`
prohíbe React de forma explícita, así que **no se copió el código**: se adaptó
el *lenguaje visual* al stack del proyecto (HTML semántico + Tailwind v4 +
Alpine).

| Del prototipo | Qué se hizo | Por qué |
|---|---|---|
| Paleta `#0a192f` / `#1a365d` / `#2b6cb0` | Adoptada como escala `primario-50…900` | Los tres anclajes pasan AA sobre blanco (17,6:1 · 12,1:1 · 5,4:1) |
| Acento cian `#00f2fe` | Decorativo sobre claro; **texto solo sobre el azul 900** | 1,39:1 sobre blanco es ilegible; 12,7:1 sobre el azul 900 sí cumple |
| `glass-panel` | `.panel-vidrio`, con variantes de tema y alto contraste | — |
| Titular en degradado | `.texto-degradado`, con respaldo en color sólido | Si el navegador no soporta `background-clip: text`, el texto se ve igual |
| Retícula bento | Aplicada a urgencias + noticias + sedes | — |
| Navbar `fixed` | `sticky`, y solo desde `lg` | Fija tapaba el destino de «Saltar al contenido» y empujaba el aviso de emergencia fuera de pantalla |
| Tarjeta «Resultados — consulta laboratorio» | **No se construyó** | Dato clínico: `§2.6` y `§7` lo prohíben. La barra superior enlaza el sistema externo de imagenología |
| Botón «Agendar cita ahora» | «Solicitar cita» | El agendamiento real está fuera de v1; `/citas` radica una solicitud |
| 3 sedes inventadas | Sede única, de la base de datos | El hospital tiene **una** sede (docs/08) |
| NIT, PBX (607), línea 01 8000 | Datos reales de `configuracion` | El 607 es indicativo de Santander; el del Cesar es 605 |
| Fotos de Unsplash | SVG y degradados | Peticiones a terceros: rompen el presupuesto de LCP < 2,5 s en 3G y la CSP |
| `animate-bounce`, `animate-ping` | Retiradas | Un elemento que salta sin parar molesta; además el sitio ya honra «reducir movimiento» |

## Dos defectos encontrados al verificar, no al escribir

1. **Titular invisible en tema oscuro.** El degradado usaba los azules oscuros
   del prototipo, que sobre el fondo `#0B1220` dan **1,54:1**. Se detectó en una
   captura del tema oscuro, no leyendo el código. El degradado ahora se invierte
   hacia los azules claros en oscuro (11:1 y 7,6:1) y `ContrasteTest` fija ambos
   extremos.

2. **Cabecera de media pantalla en móvil.** Con el menú de 11 raíces de la
   Fase 9, la cabecera pegajosa medía **430 px de 844**. Ahora el logotipo y
   PQRSDF comparten fila, el eslogan se oculta bajo `sm` y la cabecera solo se
   fija desde `lg`: **156 px**.

## Verificado

- 231 tests en verde, incluidos **27 pares de contraste** (antes 25).
- Portada capturada en claro, oscuro, alto contraste y móvil.
- **Cero peticiones a terceros** y cero errores de consola, medidos con
  Playwright sobre la página renderizada.
- Las 14 rutas públicas principales responden 200 con la paleta nueva.
- CSS compilado: 34,9 KB sin comprimir.

## Alcance y lo que queda

Se aplicó al **cascarón y la portada**. El resto de vistas hereda los tokens
nuevos —cambian de color, no de maquetación—. Rehacer la maquetación de las
~25 vistas públicas con el lenguaje bento es un trabajo aparte que no se pidió.

El prototipo solo dibujaba la portada, así que para el resto no hay referencia
visual: si el hospital quiere el lenguaje nuevo en listados y fichas, conviene
que amplíe el prototipo antes.

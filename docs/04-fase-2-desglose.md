# Fase 2 — Directorio y servicios · desglose ejecutable

**Inicio:** 2026-08-21 · Fase 1 terminada en `96ebdb6`.

| # | Paso | Verificable cuando… | Estado |
|---|---|---|---|
| 1 | Migración del grupo Directorio: `sedes`, `especialidades`, `servicios`, `profesionales`, `servicio_sede`, `especialidad_sede`, `profesional_especialidad` | Sube y baja sin error | ✅ |
| 2 | Panel: sedes (horarios, coordenadas, bandera de urgencias, imagen) | Una sede editada cambia en el sitio público | ✅ |
| 3 | Panel: especialidades y servicios, con sus sedes (relaciones) | Marcar/desmarcar una sede cambia el filtro público | ✅ |
| 4 | Panel: profesionales con `autoriza_publicacion`, especialidades y foto | Sin autorización marcada, el profesional NO aparece en público | ✅ |
| 5 | Semillas de directorio: las 3 sedes de `frontend.png` y especialidades típicas (provisionales, editables) | La home muestra la franja de sedes | ✅ |
| 6 | Público: `/sedes`, `/sedes/{slug}` y franja «Nuestras sedes» en la home | El teléfono de urgencias está a un toque desde la home | ✅ |
| 7 | Público: `/especialidades` (filtro por sede sin recargar), `/servicios`, `/directorio` y sus detalles | El filtro funciona sin recargar y con JS mínimo | ✅ |
| 8 | Público: `/urgencias` y `/contacto` | Urgencias muestra teléfono grande y sedes 24/7 | ✅ |
| 9 | Cierre: criterios de aceptación de la fase | Los tres criterios del plan se cumplen | ✅ |

**Notas de alcance**

- Sin mapa embebido: las coordenadas generan un enlace externo «Ver en el
  mapa» (privacidad + presupuesto de JS). El mapa propio queda en BACKLOG.
- El filtro «sin recargar» es JS vanilla de ~1 KB sobre atributos `data-`,
  no un framework.
- Profesionales: nunca teléfonos ni correos personales (esquema §3); la ficha
  pública solo existe con `autoriza_publicacion = 1` **y** `activo = 1`.

---

## Cierre de la fase — 2026-08-21

**Los tres criterios de aceptación del plan:**

1. *Encontrar el teléfono de urgencias desde la home en un toque* — el
   enlace «Emergencias» de la barra superior marca con un toque (`tel:`),
   y la franja de sedes y `/urgencias` repiten el teléfono en grande.
2. *Filtrar especialidades por sede funciona sin recargar* — `filtro.js`
   (~1 KB, vanilla) sobre atributos `data-`, con anuncio `aria-live` para
   lectores de pantalla. El mismo filtro sirve en servicios y directorio.
3. *Ningún profesional se publica sin `autoriza_publicacion = 1`* — la regla
   vive en la consulta del repositorio (listados, detalle por URL y
   profesionales por especialidad) y está cubierta por pruebas. Todo cambio
   de la autorización queda auditado.

Los datos de sedes sembrados son los de frontend.png: **verificar
direcciones y teléfonos reales en el panel antes de producción.**

**Fase 2 terminada.** Sigue la **Fase 3 — Gestión documental**.

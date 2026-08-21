# Fase 3 — Gestión documental · desglose ejecutable

**Inicio:** 2026-08-21 · Fase 2 terminada en `3eedd90`.

| # | Paso | Verificable cuando… | Estado |
|---|---|---|---|
| 1 | Migración del grupo Documental: `etiquetas`, `documentos`, `documento_versiones`, `documento_etiqueta` | Sube y baja sin error | ✅ |
| 2 | Panel: dependencias (deuda de la Fase 0: la tabla existe desde entonces) | El selector de dependencia del documento se llena desde el panel | ✅ |
| 3 | Panel: documentos — ficha completa (TRD incluida), archivo PDF validado, estados vigente/derogado/histórico, `reemplaza_a`, nueva versión con historial y etiquetas | Un documento sin dependencia no se puede publicar | ✅ |
| 4 | Público: `/documentos` (buscador con filtros por texto, dependencia, categoría, año y estado) y ficha `/documentos/{id}-{slug}` con descarga contada | Un derogado no aparece salvo que se pida | ✅ |
| 5 | Cierre: criterios de la fase, incluido el buscador < 300 ms sobre mil documentos (medido con semilla masiva en pruebas) | Los cuatro criterios se cumplen | ✅ |

**Notas de alcance**

- Los PDF nunca son la interfaz (CLAUDE.md §6): la ficha HTML lleva todos los
  metadatos y la descarga es un botón. La ficha se lee completa en móvil.
- El público descarga solo la versión vigente; el historial de versiones se ve
  en la ficha (fechas y notas) y los archivos viejos quedan en el panel.
- Solo PDF en v1 (la lista blanca del almacén ya lo permite); Office queda en
  BACKLOG hasta que gestión documental lo pida con un caso real.
- El buscador de esta fase es el documental (FULLTEXT de MySQL); el buscador
  unificado del sitio es la Fase 6.

---

## Cierre de la fase — 2026-08-21

**Los cuatro criterios de aceptación del plan:**

1. *Un documento sin resumen, dependencia o fecha no se puede publicar* —
   `PublicacionIncompletaException` con mensaje en español para el
   funcionario; resumen y fecha son además obligatorios desde la creación.
   Probado por test y de punta a punta por HTTP.
2. *Un derogado deja de aparecer en resultados por defecto* — el buscador
   público filtra `estado = vigente` salvo petición expresa; la ficha del
   derogado sigue accesible con advertencia y enlace al que lo reemplaza.
3. *El buscador responde en < 300 ms sobre mil documentos* — test de
   rendimiento con semilla de 1000 documentos: búsqueda FULLTEXT y búsqueda
   por filtros, ambas medidas y bajo el umbral.
4. *La ficha es legible en móvil sin abrir el PDF* — ficha HTML con resumen,
   metadatos (dependencia, acto, vigencia, TRD, versión) y descarga con peso,
   verificada a 520 px.

**Fase 3 terminada.** Sigue la **Fase 4 — Transparencia y datos abiertos**.

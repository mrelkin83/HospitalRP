# Fase 3 — Gestión documental · desglose ejecutable

**Inicio:** 2026-08-21 · Fase 2 terminada en `3eedd90`.

| # | Paso | Verificable cuando… | Estado |
|---|---|---|---|
| 1 | Migración del grupo Documental: `etiquetas`, `documentos`, `documento_versiones`, `documento_etiqueta` | Sube y baja sin error | ⏳ |
| 2 | Panel: dependencias (deuda de la Fase 0: la tabla existe desde entonces) | El selector de dependencia del documento se llena desde el panel | |
| 3 | Panel: documentos — ficha completa (TRD incluida), archivo PDF validado, estados vigente/derogado/histórico, `reemplaza_a`, nueva versión con historial y etiquetas | Un documento sin dependencia no se puede publicar | |
| 4 | Público: `/documentos` (buscador con filtros por texto, dependencia, categoría, año y estado) y ficha `/documentos/{id}-{slug}` con descarga contada | Un derogado no aparece salvo que se pida | |
| 5 | Cierre: criterios de la fase, incluido el buscador < 300 ms sobre mil documentos (medido con semilla masiva en pruebas) | Los cuatro criterios se cumplen | |

**Notas de alcance**

- Los PDF nunca son la interfaz (CLAUDE.md §6): la ficha HTML lleva todos los
  metadatos y la descarga es un botón. La ficha se lee completa en móvil.
- El público descarga solo la versión vigente; el historial de versiones se ve
  en la ficha (fechas y notas) y los archivos viejos quedan en el panel.
- Solo PDF en v1 (la lista blanca del almacén ya lo permite); Office queda en
  BACKLOG hasta que gestión documental lo pida con un caso real.
- El buscador de esta fase es el documental (FULLTEXT de MySQL); el buscador
  unificado del sitio es la Fase 6.

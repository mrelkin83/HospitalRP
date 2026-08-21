# Fase 4 — Transparencia y datos abiertos · desglose ejecutable

**Inicio:** 2026-08-21 · Fase 3 terminada en `ca6ff75`.

| # | Paso | Verificable cuando… | Estado |
|---|---|---|---|
| 1 | Migración: `transparencia_secciones`, `transparencia_items`, `datasets`, `dataset_recursos` | Sube y baja sin error | ✅ |
| 2 | Semilla del Anexo 2 de la Res. 1519 (secciones 1–9 con subsecciones) + enlace a SECOP en contratación | Toda sección obligatoria existe aunque esté vacía | ✅ |
| 3 | Panel de transparencia: árbol de secciones con **indicador de vacías**, ítems (documento o enlace externo), metadatos de sección | El administrador ve de un vistazo qué falta | ✅ |
| 4 | Panel de datasets: CRUD + recursos en formatos abiertos (CSV, JSON, XML, XLSX) — la lista blanca del almacén se amplía a esos formatos | Un dataset sin recursos no se publica en vacío | ✅ |
| 5 | Público: `/transparencia` (menú completo con ítems) y `/transparencia/datos-abiertos` con descargas contadas | Un recurso se descarga en formato abierto, no en PDF | ✅ |
| 6 | Cierre: criterios de la fase | Los cuatro criterios se cumplen | ✅ |

**Notas de alcance**

- ⚠️ La estructura sembrada sigue el Anexo 2 de la Res. 1519 de 2020 en su
  versión estándar. **El hospital debe cotejarla con la versión vigente del
  anexo** (advertencia del propio esquema); las secciones se pueden marcar
  «no aplica», renombrar y completar desde el panel sin tocar código.
- Las secciones obligatorias no se pueden borrar, solo marcar «no aplica»
  (con justificación visible en el panel).
- La vista pública muestra también las secciones vacías con la leyenda «En
  actualización»: ocultarlas sería fingir cumplimiento.

---

## Cierre de la fase — 2026-08-21

**Los cuatro criterios de aceptación del plan:**

1. *Toda sección obligatoria existe, aunque esté vacía* — semilla idempotente
   de las secciones 1–9 del Anexo 2 con subsecciones; la vista pública muestra
   las vacías con «Sección en actualización». Probado por test.
2. *El administrador ve de un vistazo qué secciones no tienen contenido* —
   indicador en el panel: conteo y lista de obligatorias vacías, e insignia
   «Vacía» por subsección. Probado por test.
3. *Los datasets se descargan en formato abierto, no en PDF* — el enum de
   formatos ni siquiera admite PDF; el recurso valida que el archivo coincida
   con el formato declarado, y la descarga se cuenta. Probado por test.
4. *Enlace a SECOP presente en contratación* — sembrado en la sección 3.2
   (SECOP II, Colombia Compra Eficiente). Probado por test.

**Corrección al esquema de referencia:** MySQL 8 rechaza (error 3823) el
`CHECK` de `transparencia_items` combinado con FK `SET NULL`; la FK a
documentos pasó a `CASCADE` (los documentos usan borrado lógico, la acción
no se dispara en la práctica). `database/esquema.sql` quedó corregido.

**Fase 4 terminada.** Sigue la **Fase 5 — Motor de trámites y PQRSDF**,
la fase más grande del proyecto (bloqueada en parte por las preguntas
pendientes con el hospital: horarios de bandeja y términos legales).

# Fase 1 — Contenido y sitio público base · desglose ejecutable

**Inicio:** 2026-08-21 · Fase 0 terminada en `269b59a`.
Referencia visual: `frontend.png` (decisión F del plan de arranque).

Cada paso termina en algo verificable y en un commit, como en la Fase 0.

| # | Paso | Verificable cuando… | Estado |
|---|---|---|---|
| 1 | Migraciones del grupo Contenido: `categorias`, `paginas`, `noticias`, `campanas`, `avisos`, `faq`, `contenido_versiones`, `menus`, `menu_items` | Suben y bajan sin error | ✅ |
| 2 | Configuración dinámica (deuda de Fase 0): lectura tipada de la tabla `configuracion` con caché por petición + semillas (teléfonos, dirección, correo, redes) | El pie de página lee un teléfono desde la base | ✅ |
| 3 | Núcleo de contenido: `EstadoAprobacion`, sanitizador HTML por lista blanca, versionado con snapshot JSON y restauración auditada, reglas de visibilidad pública (`publicado` + ventana `publicar_en`/`despublicar_en`) | Restaurar una versión funciona y queda en `auditoria`; un `<script>` en el contenido no sobrevive | ✅ |
| 4 | Tarea programada `contenido:programado` en `bin/consola`: publica y despublica según fechas | Una noticia con `publicar_en` en el pasado pasa a visible al correr el comando | ✅ |
| 5 | Módulo Noticias en el panel: listar, crear, editar, enviar a revisión, aprobar, publicar, programar; imagen destacada con texto alternativo obligatorio | Un funcionario publica una noticia con imagen sin tocar código | ✅ |
| 6 | Biblioteca de medios: subir, listar y elegir imágenes (texto alternativo obligatorio) | Una imagen sin `texto_alt` no se puede guardar | ✅ |
| 7 | Menús editables + semillas de los menús de la Res. 1519 | Cambiar una etiqueta del menú en el panel cambia el sitio público | ✅ |
| 8 | Layout público: barra de accesibilidad, encabezado con navegación, pie según Res. 1519 (con configuración dinámica), aviso activo y modo emergencia | Un aviso `critico` activo transforma la home | ✅ |
| 9 | Home según `frontend.png`: héroe, 8 accesos, noticias recientes, aviso | La home usa noticias reales de la base | ✅ |
| 10 | Público de contenido: listado y detalle de noticias, página institucional por ruta dinámica | Una página `en_revision` da 404 público; una `publicada` se ve | ✅ |
| 11 | CKEditor 5 autoalojado en el panel (sanitización del paso 3 al guardar) | El editor funciona sin CDN y el HTML guardado está saneado | ✅ |
| 12 | Páginas, campañas, avisos, FAQ y categorías en el panel (mismos patrones del paso 5) | CRUD completo y auditado | ✅ |

**Nota del paso 9:** el acceso «Resultados» del diseño se cambió por «Consulta
tu trámite»: los resultados de laboratorio son dato clínico y están fuera de
v1 (CLAUDE.md §7). El diseño no manda sobre el alcance.
| 13 | Cierre: LCP < 2,5 s en 3G simulado, navegación completa por teclado, criterios de aceptación de la fase | Los cinco criterios del plan se cumplen | ✅ |

---

## Cierre de la fase — 2026-08-21

**Los cinco criterios de aceptación del plan:**

1. *Publicar una noticia con imagen en menos de dos minutos, sin manual* —
   verificado de punta a punta por HTTP: título, resumen, contenido, imagen
   con texto alternativo, guardar, aprobar, publicar. Sin jerga en el camino.
2. *Una noticia programada se publica sola y se despublica sola* — comando
   `contenido:programado` probado contra MySQL. **Pendiente operativo:**
   registrar la tarea en el programador del servidor (cada minuto).
3. *Restaurar una versión anterior funciona y queda auditado* — probado en
   noticias y páginas; la restauración crea versión nueva, nunca reescribe.
4. *La home carga en < 2,5 s en 3G simulado* — **Lighthouse: LCP 2,4 s**
   (300 ms RTT, 700 kbps, CPU ×4), rendimiento 95/100, TBT 0 ms, CLS 0.
   Medido contra `php -S` sin compresión: en producción con gzip mejora.
   JS inicial del sitio público: 2,4 KB (presupuesto: 100 KB).
5. *Navegación completa por teclado* — por construcción: solo elementos
   nativos (enlaces, botones, formularios, `details`), salto al contenido en
   toda página y anillo de foco global `:focus-visible`.

**Fase 1 terminada.** Sigue la **Fase 2 — Directorio y servicios**
(sedes, especialidades, profesionales, urgencias, contacto), que además
completa la franja de sedes de la home según `frontend.png`.

**Notas de alcance**

- La franja «Nuestras sedes» de `frontend.png` llega en la **Fase 2** (módulo
  Directorio). En la Fase 1 la home la omite sin dejar hueco.
- `redirecciones` es de la Fase 7 (SEO) y `festivos` de la Fase 5. No se migran aquí.
- El buscador del héroe se deja como formulario hacia `/buscar` (Fase 6);
  mientras tanto la ruta responde un aviso de «en construcción» accesible.

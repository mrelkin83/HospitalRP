# Inventario del sitio actual (hrplopez.gov.co) — 2026-08-21

Extraído del Joomla vigente. Responde la pregunta 7 del plan (inventario de
URLs para redirecciones 301, Fase 7) y define qué contenido se rescata.

## Datos institucionales REALES (corrigen las semillas provisionales)

| Dato | Valor |
|---|---|
| Sede | **ÚNICA**: Calle 16 No. 17-141, Barrio Hernando Santana (Av. La Popa), Valledupar |
| PBX | (605) 5712339 · Fax (605) 5748451 |
| Call center | (+57) 304 3251875 · 311 4232493 · 311 4131075 |
| Correo general | contacto@hrplopez.gov.co |
| Citas | citasmedicas@hrplopez.gov.co |
| Ventanilla única | ventanillaunica@hrplopez.gov.co (Ext. 3502) |
| Notificaciones judiciales | notificacionesjudiciales@hrplopez.gov.co |
| Línea ética anticorrupción | oficialcumplimiento@hrplopez.gov.co · (+57) 318 3546240 |
| Horario administrativo | L–V 7:30 a. m.–12:00 m. y 2:00–6:00 p. m. |
| Call center | L–V 7–12 y 2–5 · sábados 7–11 a. m. |
| Redes | X @ESEHospitalRos1 · IG @hospitalrosariovalledupar · YouTube · Facebook |
| Dominio | **hrplopez.gov.co** |

## Mapeo del contenido actual → módulos del sitio nuevo

**Ya cubierto por módulos existentes (el panel lo administra sin código):**

| Del Joomla | Va a |
|---|---|
| Informes (presupuesto, planes, estados financieros, control interno, rendición de cuentas, informes de gestión) | Transparencia (secciones 4 y 6 del Anexo 2) + Documentos |
| Contratación / invitaciones públicas / Plan Anual de Adquisiciones | Transparencia sección 3 (SECOP ya enlazado) + Documentos |
| Hospital (historia, misión/visión, objetivos, principios, organigrama, infraestructura, localización) | Páginas institucionales (`/institucional/*`) + Transparencia sección 1 |
| Normativa (ordenanzas, decretos, acuerdos, resoluciones, circulares, leyes) | Documentos con categorías sembradas (esta fase) |
| RR HH: manual de funciones, asignaciones salariales, plan de capacitación (Decreto 612), ofertas de empleo | Transparencia secciones 1 y 4 + Convocatorias (pendiente) |
| Deberes y derechos, glosario, preguntas frecuentes | FAQ + Páginas |
| PQRSDF / Solicitud PQR / consulta de citas | Motor de trámites (Fase 5) ✓ |
| IAMII, Banco de Leche, Madre Canguro, Banco de Sangre, Pediatría, Humanización, Docencia, Etnocultural | Páginas + Campañas (contenido programático administrable) |
| Prensa, boletines, galería | Noticias + Medios |
| MECI / MIPG / conflicto de intereses / control disciplinario | Transparencia (ítems) + Páginas |
| Enlaces a SIHO, RUAF, SuperSalud, MinSalud, Gobernación | Ítems de transparencia y menús (URL externa) |
| Notificaciones judiciales | Página + correo en el pie (ya presente) |

**Se rescata como dato, no como sección:** contador de visitas (no), chat (fuera
de v1 por CLAUDE.md §7), encuestas automáticas (fuera de v1).

**Sistemas externos:** resultados de imagenología (resultadosrx.hrplopez.gov.co)
es un sistema aparte con login propio; el sitio nuevo NO muestra resultados
(dato clínico, CLAUDE.md §2) pero el panel puede enlazar el sistema externo
como ítem de menú cuando el hospital lo pida.

**Obsoleto / no migra:** estructura duplicada de menús del Joomla, banners de
entidades como imágenes, PDF sueltos sin ficha (todos pasarán por gestión
documental con metadatos).

## URLs del Joomla para redirecciones 301 (Fase 7)

Las rutas `/sitio/index.php/es/...` listadas arriba alimentarán la tabla
`redirecciones` en la Fase 7. Las más citadas en actos administrativos:
`peticiones-quejas-y-reclamos`, `transparencia`, `normativa/*`, `informes/*`,
`servicio-al-ciudadano/*`.

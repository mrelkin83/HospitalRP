# Subsanación de la Auditoría Visual V9 y del Certificado ITA 2025

**Fecha:** 2026-10-30
**Documentos fuente:** `Auditoria_Visual_HRPL_V9_Ilustrada_FINAL.docx` (26/10/2026, Oficina de
Sistemas) y `CERTIFICADO_ITA_2025.pdf` (Procuraduría, 29/08/2025, 98/100 puntos).

Este documento registra, hallazgo por hallazgo, qué se corrigió, dónde y qué queda
pendiente de decisión o contenido del hospital.

## 1. Hallazgos normativos de la Auditoría V9 (todos subsanados)

| Hallazgo (sección) | Fundamento | Corrección aplicada |
|---|---|---|
| Contraste 3,09:1 del teléfono en cian sobre azul 700 (§2, §8) | CC5; WCAG 1.4.3 | El número de Citas de la barra superior pasa de `--color-marca-cian` (#35BDE9) a `primario-100` (#CDF1F9, 5,64:1). `resources/views/publico/base.php` |
| Contraste 3,09:1 de los títulos cian del pie (§7, §10) | CC5; WCAG 1.4.3 | Los 4 títulos del pie pasan a `primario-200` (#9CE0F5, 4,63:1). Las píldoras cian de las cabeceras de sección (8 plantillas) pasan a blanco. Pares nuevos fijados en `tests/Unit/Diseno/ContrasteTest.php` |
| Marca País CO–Colombia ausente en el pie (§5, §10, §16) | Anexo 2, 2.2.1(1) | Recurso oficial autoalojado en `public/img/marca_co.svg`, junto a GOV.CO en la barra inferior del pie, con enlace a colombia.co y el enlace textual «Conoce a GOV.CO». Claves `marca_pais.*` administrables |
| PBX y Citas sin prefijo +57 (§6, §7, §16) | Anexo 2, 2.2.1(4) | «+57 605 5712339» y «+57 317 5153642» en barra superior y pie; los `tel:` quedan en formato E.164. Migración `Version20261030120000` normaliza los valores vivos (también emergencias y línea ética) sin pisar personalizaciones |
| «Transparencia» sin denominación completa (§16) | Anexo 2, 2.4 | «Transparencia y Acceso a la Información Pública» en barra superior, menú principal, tarjeta de acceso de la portada, H1 y miga de la página |
| Dos rótulos para el mismo destino: «Atención y Servicios…» vs «Ver Atención a la Ciudadanía» (§3, §16) | Anexo 2, 2.4; CC7 | El menú principal usa el mismo nombre que la barra superior y el enlace del submenú repite el rótulo del control (se eliminó el prefijo «Ver …») |

## 2. Correcciones de diseño y usabilidad recomendadas (aplicadas)

| Observación (sección) | Corrección aplicada |
|---|---|
| Menú móvil: 3 de 9 categorías visibles, desplazamiento lateral a descubrir (§11, §14) | Botón «Menú» con icono y texto, `aria-expanded`, que despliega la lista vertical completa. Toque y teclado (Enter/espacio nativos, Escape cierra y devuelve el foco). Sin JavaScript queda la fila original. `base.php`, `public/js/menu.js`, `app.css` |
| Logo con placa blanca y escala reducida (§12, §14) | Icono institucional en PNG transparente (`public/img/logo_icono.png`, recortado del arte oficial), a 56 px (máx. del estándar 2022: 80-90 px). En tema oscuro lleva halo claro con `drop-shadow`, no placa. En el pie conserva la pastilla blanca, legítima para marca multicolor |
| Tipografía Segoe UI y H1 de 64 px frente a Montserrat/Work Sans 30/35 px (§18, §19, §28) | Montserrat (títulos) y Work Sans (texto) autoalojadas en `public/fuentes/` (variables, subconjunto latino, `font-display: swap`; la CSP `default-src 'self'` se mantiene). H1 = 30/35 px a 1366 px —exactamente la referencia—, H2/H3 en proporción. Verificado con medición en Chrome |
| Miga de pan sin el nivel actual en PQRSDF; ausente en Transparencia (§22) | «Inicio › Trámites › PQRSDF» con `aria-current="page"`; miga nueva en /transparencia |
| Leyenda del buscador truncada (§15) | «Buscar en el portal» (antes «Buscar trámites, médicos, noticias...») |
| Columnas desequilibradas en Transparencia (§27) | `columns-2` de CSS con `break-inside-avoid`: las 10 tarjetas se reparten equilibrando la altura |
| Barra de accesibilidad sin Centro de Relevo (§20, §21) | Enlace «Centro de Relevo» en la barra superior (centroderelevo.gov.co) |
| Alt genérico «imagen de apoyo» (§8.a) | Era `archivos.texto_alt` del fondo del héroe (decorativo): queda vacío, que es lo correcto. **Pendiente de contenido:** 32 de 60 imágenes de la biblioteca no tienen texto alternativo; describirlas es tarea editorial del panel (Archivos) |
| Tema oscuro: incidencia reportada no reproducida (§13) | Re-verificado en Chrome a 390 y 1366 px: fondo oscuro y texto claro correctos. Pendiente reproducir en el dispositivo original del solicitante |

## 3. Certificado ITA 2025 (Procuraduría, 98/100) — criterios con «No»

| Criterio | Estado en el portal nuevo | Responsable del cierre |
|---|---|---|
| 7.1 Trámites: costos asociados | **Ya cumplido por código**: cada ficha dice siempre «Trámite gratuito» o «Tiene costo» + detalle (`tramites/formulario.php`) | Verificar el dato en cada trámite sembrado (panel) |
| 8.2.d Semáforo de avances de decisiones (Participa) | Sin datos que publicar: la justificación 2025 fue la intervención forzosa administrativa | Hospital (Planeación): definir las decisiones y sus estados; el panel de Transparencia ya admite publicar ese contenido |
| 9.1 Tablas de retención documental (2 preguntas) | Bloqueado externo: TRD sin convalidar por el AGN (ya registrado como bloqueante en la matriz ITA 2026) | Hospital (Gestión Documental) ante el AGN |
| 6.x Planes de mejoramiento derivados de rendición de cuentas | Justificado en 2025: no se derivó plan alguno | Hospital: publicar el plan si el ejercicio 2026 lo deriva |

## 4. Qué necesita el despliegue en producción (3.145.176.69)

1. `git pull` + `php vendor/bin/doctrine-migrations migrate …` (aplica `Version20261030120000`: teléfonos +57, Marca País, denominaciones, Centro de Relevo, logo, buscador).
2. `php bin/consola semillas:configuracion` (crea `marca_pais.*` si la migración no corrió antes; es idempotente).
3. Recompilar el CSS (`npm run css`) o copiar `public/build/app.css` al servidor:
   `public/build/` está en `.gitignore`. Las fuentes (`public/fuentes/`) y las
   imágenes (`marca_co.svg`, `logo_icono.png`) sí viajan en el repositorio.

## 5. Verificación

- Suite completa en verde: 938 pruebas unit + 393 feature (incluye los pares de
  contraste nuevos: #CDF1F9 y #9CE0F5 sobre #1D5BA7 ≥ 4,5:1).
- Medición en Chrome (1366×768): H1 = Montserrat 30/35 px; fuentes `loaded`.
- Móvil 390 px: botón «Menú» visible, lista vertical con las 9 categorías,
  `aria-expanded` correcto; fila horizontal como respaldo sin JavaScript.
- Pie: muestreo de píxeles confirma fondo #1D5BA7 con Marca País y
  «Conoce a GOV.CO» presentes.

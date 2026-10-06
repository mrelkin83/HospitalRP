# Cuentas de cobro · Fase 6 — Carga masiva y evidencia por actividad · desglose ejecutable

**Inicio:** 2026-09-20 · Fases 1–5 terminadas en `42c4879`.

> **Escala confirmada por el hospital (2026-09-20):** 1.500 contratos
> **vigentes**, **ya escaneados**, **cuatro revisores** disponibles y **4 TB**
> de disco en el servidor. Todo el dimensionamiento de abajo parte de esas
> cuatro cifras; si alguna cambia, revisar los bloques A y C antes de seguir.

La fase cierra tres huecos que aparecieron al planear la puesta en marcha real:

1. La carga de minutas es de una en una y sincrónica: 1.500 contratos por ese
   camino son 25 horas de navegador abierto.
2. Cada actividad del informe se certifica contra texto libre: no hay
   **producto asociado** ni **evidencia** por obligación, que es lo que los
   formatos del SGC exigen.
3. Las imágenes de extracción no se purgan nunca y los soportes se guardan sin
   comprimir.

---

## A. Cola de extracción y carga por lotes

| # | Paso | Verificable cuando… | Estado |
|---|---|---|---|
| A1 | Migración `Version20260920200000`: `intentos`, `reclamado_en` y `asignado_a` en `cc_extracciones` | Sube y baja sin error | ✅ |
| A2 | `ExtraccionMinutaRepository`: `reclamarPendiente()` atómico y `liberarColgadas(15 min)` | Dos trabajadores simultáneos nunca toman la misma minuta (probado) | ✅ |
| A3 | Subida múltiple: el formulario acepta hasta **20 PDF**; el controlador valida, guarda y **encola** sin procesar | Subir 20 archivos responde en segundos y deja 20 filas `PENDIENTE` | ✅ |
| A4 | Comando `cuentas:extraer-minutas --limite=5 --trabajador=1` | Procesa la cola, reanuda tras una interrupción y respeta el tope de 2 intentos | ✅ |
| A5 | Detector de capa de texto: `pdftotext` sobre el PDF; con texto suficiente → parser determinista por cláusulas; sin texto → ruta IA/OCR actual | Un PDF exportado de Word se extrae sin gastar un token | ✅ |
| A6 | Bandeja de revisión para cuatro personas: asignación con `asignado_a`, agrupación por similitud de objeto y filtros por estado | Dos revisores nunca abren la misma extracción | ✅ |
| A7 | Purga: al aplicar la extracción a un contrato se borran sus imágenes `extracciones-N` | La carpeta queda vacía y el visor deja de ofrecer páginas | ✅ |
| A8 | Tarea programada en el servidor: el comando corre cada 2 minutos | Una minuta subida a las 10:00 está lista hacia las 10:05 sin intervención | ✅ |

**La tarea programada (A8)**

En el servidor, Programador de tareas de Windows, cada 2 minutos:

```
php C:/ruta/del/sitio/bin/consola cuentas:extraer-minutas --limite=5
```

Para la carga inicial, a mano y en paralelo (dos o tres ventanas):

```
php bin/consola cuentas:extraer-minutas --limite=500 --trabajador=1
php bin/consola cuentas:extraer-minutas --limite=500 --trabajador=2
```

`FOR UPDATE SKIP LOCKED` garantiza que no se pisen: el segundo proceso no
espera al primero, se salta la fila tomada y sigue con la siguiente. Si un
proceso muere, sus minutas vuelven solas a la cola a los 15 minutos.

**Decisiones de alcance**

- **La ruta principal es el escaneo (IA/OCR).** El detector de texto de A5 es
  un atajo oportunista: el hospital no garantiza tener el Word de los 1.500.
- **Un trabajador por proceso, nunca ráfagas.** Cada minuta son cinco imágenes
  al proveedor; disparar diez en paralelo invita al corte por límite de tasa.
  Para la carga inicial se corren 2 o 3 procesos con `--trabajador` distinto.
- **Tope de 2 intentos.** Al tercero la extracción queda en `ERROR` con su
  mensaje. Sin tope, un PDF corrupto se reprocesa indefinidamente.
- **Si la IA está apagada**, el comando igual convierte las páginas y deja la
  extracción lista para OCR o llenado manual: el lote sirve sin proveedor.
  (Desde el 2026-09-20 la clave está cargada en el panel: proveedor
  `anthropic` vía gateway con `claude-sonnet-5`.)
- La revisión humana **no se toca**: sigue siendo obligatoria, una por una.
- La columna se llama `asignado_a` y no `revisor_id` a propósito: en este
  módulo un «revisor» es quien firma la revisión de una cuenta de cobro
  (`cc_revisores`), que es otra cosa.

---

## B. Producto asociado y evidencia por actividad

Hoy `cc_cuenta_actividades` solo guarda `descripcion_ejecucion` y
`cumplimiento`; los adjuntos cuelgan de la cuenta completa con cuatro tipos
fijos. El supervisor está certificando cumplimiento contra texto libre.

| # | Paso | Verificable cuando… | Estado |
|---|---|---|---|
| B1 | Tabla `cc_productos_actividad` + semilla de los 18, administrable en Parámetros (agregar y retirar) | El catálogo se administra desde el panel, sin migración | ✅ |
| B2 | `producto_id` en `cc_cuenta_actividades` | Una actividad sin producto impide radicar la cuenta | ✅ |
| B3 | Tabla `cc_actividad_evidencias` (actividad, `tipo` ARCHIVO/ENLACE, archivo, enlace, nombre original, mime, tamaño) | Se adjuntan varias evidencias a una misma obligación (tope 5) | ✅ |
| B4 | Reglas de radicación: producto obligatorio siempre; **evidencia obligatoria salvo `NO APLICA`**; `LINK DE ACCESO` pide URL válida en lugar de archivo | El sistema rechaza el envío y señala la obligación incompleta | ✅ |
| B5 | Compresión al subir: imágenes a 1600 px de lado largo, JPEG 80 | Medido: una imagen de 2,6 MB queda en 578 KB (22 %) en el peor caso —ruido puro—; una foto real baja bastante más | ✅ |
| B6 | Portal, paso 2: por obligación, el textarea + selector de producto + zona de carga con contador, conservando el guardado automático | Un contratista completa las tres cosas sin salir de la pantalla | ✅ |
| B7 | Informe de actividades: tercera columna **PRODUCTO** con el conteo de anexos; el paquete anexa las evidencias en el orden de las obligaciones | El PDF del informe muestra el producto de cada fila | ✅ |
| B8 | Revisión y supervisión: **ambas** bandejas muestran producto y abren las evidencias antes de certificar | Ninguna actividad se certifica a ciegas | ✅ |

**Catálogo sembrado (18)**

`ACTA DE AUDITORÍA` · `ACTA DE REUNIÓN PRESENCIAL` · `ACTA DE REUNIÓN VIRTUAL`
· `ARCHIVO DIGITAL` · `BASE DE DATOS` · `COPIA DE CORREO ELECTRÓNICO` ·
`ELABORACIÓN DE DOCUMENTO` · `FORMATO DIGITAL` · `FOTOGRAFÍA(S)` · `INFORME` ·
`LINK DE ACCESO` · `LISTADO DE ASISTENCIA` · `MODELO DE OFICIO` ·
`OFICIO PROYECTADO` · `OFICIO REVISADO` · `PANTALLAZO` · `VISITA DE CAMPO` ·
`NO APLICA`

**Decisiones de alcance**

- **Catálogo en tabla, no `enum`.** La lista la mantiene el hospital: agregar
  un producto debe ser un registro, no una migración de base de datos.
- **`NO APLICA` es la única exención de evidencia.** Existe justamente para la
  obligación que no genera entregable, así que no hay excusa para dejar el
  campo vacío; sin esa regla el selector se vuelve decorativo.
- **Sin retroactividad.** Las actividades ya radicadas quedan con
  `producto_id` nulo; la regla solo rige para cuentas nuevas. No se puede
  exigir un dato que no se pidió cuando se radicó.
- **La regla vive en `EnvioCuentaService`, no en la pantalla.** La radicación
  tiene una sola puerta; una validación que solo estuviera en el formulario se
  esquiva con una petición a mano.
- **El GJ-FR-004 no se toca.** El formato de supervisión es institucional, con
  código y versión; meterle una columna sería alterar un documento del SGC. El
  producto sale en el informe de actividades (que no tiene código) y en la
  pantalla de quien certifica, que es donde hace falta para decidir.
- **Las evidencias entran al paquete al final**, en el orden de las
  obligaciones, para que se lea igual que el informe. Los enlaces no se anexan
  —no son un archivo— y quedan citados en la pantalla.
- **La compresión nunca hace fallar una radicación**: si GD no sabe leer la
  foto, se guarda tal cual. Un contratista que no pueda radicar porque su
  cámara guarda un JPEG raro es un fallo peor que un archivo grande.

---

## C. Puesta en marcha de los 1.500 contratos

**Costo de la extracción por IA** — 5 páginas a 150 dpi (≈2.500 tokens de
imagen cada una) más ~4.000 tokens de salida, porque las obligaciones se
transcriben completas. Precios del API de Anthropic vigentes a 2026-09-20
(el descuento de lanzamiento de Sonnet 5 venció el 31 de agosto):

| Modelo | Por minuta | 1.500 minutas |
|---|---|---|
| Sonnet 5 (configurado hoy) | ≈ US$ 0,10 | **≈ US$ 150** (con reprocesos, 165–180) |
| Opus 5 | ≈ US$ 0,17 | ≈ US$ 255 |
| Sonnet 5 vía Batch API (−50 %) | ≈ US$ 0,05 | ≈ US$ 75 |

El Batch API queda documentado pero **no se construye**: es asíncrono y
complica el flujo por ahorrar US$ 75 en un evento único.

**Tiempo**

| Etapa | Estimado |
|---|---|
| Extracción, 3 trabajadores en paralelo | 8–9 h · dos noches de proceso |
| Revisión humana, 5–8 min por minuta | 125–200 h |
| ″ repartidas entre **4 revisores** | **31–50 h c/u · una a semana y media** |

La agrupación por similitud (A6) es lo que hace realista el extremo bajo del
rango: revisar 40 contratos seguidos de la misma plantilla es mucho más rápido
que 40 salteados.

**Disco (4 TB disponibles)**

| Concepto | Volumen |
|---|---|
| Carga inicial: 1.500 minutas + imágenes de extracción | ≈ 8 GB, y las imágenes se purgan al aplicar (A7) |
| Operación mensual: PDF de las 1.500 cuentas | ≈ 1,5 GB/mes |
| Operación mensual: evidencias por actividad, **comprimidas** (B5) | ≈ 6–10 GB/mes |
| ″ sin comprimir | 20–30 GB/mes |

Con 4 TB el disco deja de ser un riesgo —incluso sin comprimir aguanta más de
diez años—, pero B5 se mantiene: abarata los respaldos y hace que el paquete
PDF se arme en segundos en vez de minutos. Las cifras de evidencias se
recalcularon tras medir la compresión real (ver B5).

**Estado a 2026-09-20**

Bloques A y B construidos y probados. Falta ejecutar la carga real: programar
la tarea del servidor (A8) y correr la extracción de los 1.500 con la clave del
proveedor ya cargada en el panel.

**Orden de ejecución**

1. Bloque A completo y probado con un lote de 20 minutas reales.
2. Carga masiva por consola en dos noches; los cuatro revisores empiezan a la
   mañana siguiente sobre lo que ya esté `LISTA`.
3. Bloque B en paralelo a la revisión: hay hasta el cierre del primer mes de
   radicación para tenerlo listo, porque la primera cuenta de cobro no se
   radica hasta que su contrato esté activo.

---

## Criterios de aceptación de la fase

1. Subir 20 minutas responde en segundos y las 20 quedan encoladas.
2. Dos procesos del comando corriendo a la vez nunca toman la misma minuta.
3. Una extracción que falla dos veces queda en `ERROR` con su mensaje, no en
   bucle.
4. Un PDF con capa de texto se extrae sin llamar al proveedor de IA.
5. Con la IA apagada, la carga sigue funcionando: imágenes + OCR + manual.
6. Aplicar una extracción a un contrato borra sus imágenes de trabajo.
7. Dos revisores no pueden abrir la misma extracción.
8. Una cuenta con una actividad sin producto no se puede radicar.
9. Una actividad con producto distinto de `NO APLICA` y sin evidencia no se
   puede radicar; con `LINK DE ACCESO` exige una URL válida.
10. El informe de actividades en PDF muestra la columna PRODUCTO y el paquete
    trae las evidencias anexas en el orden de las obligaciones.
11. Una imagen de 2,6 MB queda almacenada en 578 KB o menos, a 1600 px de
    lado largo, y se abre legible. (Medido sobre ruido sintético, que es el
    peor caso posible: una fotografía real comprime mucho mejor.)

---

## Pendientes de definición

- **¿La lista de 18 productos pertenece a un formato del SGC?** Si tiene
  código (GJ-FR-0xx), se anota en el catálogo para que el informe cite la
  fuente.
- **Clave del proveedor de IA.** Sin ella la carga masiva funciona, pero cada
  minuta se llena a mano o por OCR; con 1.500 contratos eso no es viable.
  Cargarla en Sistema → Asistente de IA antes de la noche de extracción.

# Estado del proyecto y pendientes — corte al 2026-08-21

Fotografía completa: qué está terminado, qué falta en código, qué depende
del hospital y qué hay que hacer al desplegar. Los detalles de cada fase
viven en su desglose (`docs/03` a `docs/12`); esto es el mapa.

## 1. Fases

| Fase | Estado | Cierre | Detalle |
|---|---|---|---|
| 0 · Fundación (esquema, kernel, auth, auditoría, panel base) | ✅ Terminada | `docs/02` | Auditoría append-only con triggers; acceso por rutas `_acceso`/`_permiso` |
| 1 · Contenido (noticias, páginas, avisos, versionado, programación) | ✅ Terminada | `docs/03` | LCP 2,4 s medido; restaurar versiones probado |
| 2 · Directorio (sedes, especialidades, servicios, profesionales) | ✅ Terminada | `docs/04` | Regla dura probada: profesional sin autorización NO se publica |
| 3 · Documental (metadatos, TRD, versiones, buscador) | ✅ Terminada | `docs/05` | Buscador < 300 ms sobre 1000 documentos, probado |
| 4 · Transparencia (Anexo 2 Res. 1519, datos abiertos) | ✅ Terminada | `docs/06` | Esquema Anexo 2 sembrado completo |
| 5 · Trámites (motor configurable, PQRSDF, radicación) | ✅ Terminada | `docs/07` | Radicado único probado con 4 procesos concurrentes; días hábiles con festivos |
| 6 · Cumplimiento (políticas versionadas, cookies, habeas data) | ✅ Terminada | `docs/09` | **Términos PROVISIONALES** a validar por jurídica (editables en panel) |
| 7 · Búsqueda, SEO, PWA, accesibilidad | ✅ Terminada | `docs/11` | Lighthouse móvil 100/100/100 en portada; redirecciones Joomla E2E |
| 8 · IA en el panel (opcional) | ✅ Terminada | `docs/12` | 2 proveedores; sembrada APAGADA; claves van en `.env` |
| 9 · Cierre de brechas del sitio vigente | ✅ Terminada | `docs/15` | Bloques A–D completos el 2026-09-14, panel incluido |
| 9.1 · Estadísticas y trámites del personal | ✅ Terminada | este documento | Pedido del hospital el 2026-09-14 |
| 9.2 · Portada: fuera Alpine, datos reales | ✅ Terminada | §2.x | Resuelto el 2026-09-15 |
| 10 · Migración y lanzamiento | ⏳ **Pendiente** | — | Ver sección 5 |

**Suite al corte: 640 tests, 1244 aserciones, en verde.**
Sin remoto git todavía (ver 2.7).

### 1.1 Qué entró en la 9.1

- **`/estadisticas`**, con los tres grupos que pidió el hospital: *Así va la
  contratación*, *Ocupación hospitalaria* y *Estadísticas vitales*. Tabla
  `indicadores`, una fila por indicador **y mes**, de modo que la serie
  histórica sale sola y publicar el dato de marzo es añadir una fila, no
  editar la de febrero. Cada cifra muestra periodo y fuente; los grupos sin
  nada publicado no aparecen.
- **Panel → Indicadores** (`indicadores.ver` / `indicadores.editar`), con
  auditoría en cada carga. Nada se publica al crearse.
- **`/trabajadores-y-contratistas`**: página pública que agrupa los trámites
  del personal. **Sin login** — el portal del colaborador está fuera de v1
  (CLAUDE.md §7) y la intranet del Joomla se retiró el 2026-09-14. Se radica
  con documento y se recibe número de radicado, que da más trazabilidad que
  un buzón interno.
- Dos tipos de trámite nuevos: **Certificado laboral** y **Radicación de
  cuentas de cobro** (esta exige adjunto: sin soporte no hay cuenta que
  radicar).
- Las barras de los indicadores usan clases cuantizadas al 5 % (`.barra-N`)
  porque el sitio público sirve `style-src 'self'`: el ancho en línea lo
  bloquea la CSP. La cifra exacta va siempre en texto al lado.

**Telemedicina no se tocó.** Está en «Nunca — no construir» de CLAUDE.md §7 y
el propio pedido la situaba «más adelante».

## 2. Pendientes técnicos (código por hacer)

Ordenados por importancia:

1. ~~**Flujo de cambio de contraseña obligatorio.**~~ ✅ Resuelto el
   2026-09-15 (Fase 10). El Kernel encierra al usuario en
   `/admin/perfil/password` mientras `debe_cambiar_pass = 1`: solo esa
   pantalla y el cierre de sesión responden. La bandera viaja en la sesión
   (`Sesion::debeCambiarPassword()`) para no consultar la base en cada
   petición.
2. **MFA para roles administrativos.** Columnas `mfa_secreto`/`mfa_habilitado`
   en el esquema, sin implementación. CLAUDE.md §6 lo exige para el panel.
3. ~~**Módulo de convocatorias.**~~ ✅ Resuelto el 2026-09-14 (Fase 9, bloque
   B4): tablas migradas, repositorio, página pública `/convocatorias` y tipo
   `convocatoria` reactivado en el índice de búsqueda, y pantalla del panel en
   `/admin/convocatorias`.
4. **Módulo de respaldos.** Tabla `respaldos` en el esquema, sin migrar y sin
   código. La Fase 9 exige respaldos programados y restauración probada:
   comando `respaldos:crear` (mysqldump + uploads), retención y registro.
5. **Evidencia granular de cookies.** El banner funciona (bloquea scripts
   hasta aceptar), pero la tabla `consentimientos_cookies` del esquema no se
   migró: la decisión vive solo en la cookie del navegador. Decidir si se
   registra evidencia en servidor (como `autorizaciones_datos`) o se
   documenta que la cookie basta; hoy no hay scripts de terceros que exijan
   consentimiento granular.
6. ~~**Mapa del sitio (`/mapa-del-sitio`).**~~ ✅ Resuelto el 2026-09-14
   (Fase 9, bloque A2): `MapaSitioController` lo genera desde los menús
   vigentes más las rutas fijas.
7. **Remoto git + CI.** El repo es solo local. Publicar (GitHub) y montar CI
   (suite + `composer audit`); habilitaría también `/security-review` y los
   binarios de semgrep/codeql que las revisiones por fase hacen a mano.
8. **Menores:** límite por minuto del endpoint de sugerencias IA (patrón del
   tope de `busquedas_log` aplica igual). ~~Panel para redirecciones 301~~ y
   ~~permisos granulares por rol~~: ambos resueltos en la Fase 10.

### 2.y Fase 10 — administración del sistema desde el panel (2026-09-15)

Hasta la Fase 9 el panel tenía veintiséis módulos de contenido y **ningún**
módulo de sistema: las cuentas se creaban por consola, los permisos vivían en
un JSON que nadie podía editar, los datos de contacto del hospital solo se
cambiaban con un `UPDATE` a mano y el registro de auditoría solo se leía con
un `SELECT`. En la práctica, el hospital dependía de alguien con acceso al
servidor para dar de alta a una secretaria o corregir un teléfono.

Lo que entró:

| Pantalla | Ruta | Permiso |
| --- | --- | --- |
| Usuarios | `/admin/usuarios` | `sistema.usuarios` |
| Roles y permisos | `/admin/roles` | `sistema.roles` |
| Ajustes del sitio | `/admin/configuracion` | `sistema.configuracion` |
| Auditoría | `/admin/auditoria` | `sistema.auditoria` |
| Redirecciones | `/admin/redirecciones` | `sistema.redirecciones` |
| Mi cuenta | `/admin/perfil` | ninguno (todo el que entra) |

Decisiones que conviene no revertir sin pensarlo:

- **Las contraseñas las genera el sistema** (`PoliticaPassword::generar()`) y
  se muestran **una sola vez**, por la sesión y nunca por la URL: un
  `?password=…` queda en el historial, en los logs y en el proxy de la
  entidad. No existe «ver la contraseña de alguien»: el hash es irreversible.
- **Mínimo 12 caracteres**, con letra y número, sin palabras previsibles y sin
  el nombre ni el correo del funcionario (NIST SP 800-63B: longitud antes que
  composición caprichosa). La misma política rige en la consola.
- **Barandas de cuentas**: nadie se cambia el rol, se desactiva ni se da de
  baja a sí mismo, y no se puede dejar la entidad sin ningún administrador
  activo (`UsuarioAdminRepository::administradoresActivos()` cuenta por el
  comodín `*`, no por el nombre del rol).
- **Baja lógica**, no `DELETE`: la auditoría y los trámites apuntan a ese id.
- **El rol protegido no se edita ni se borra** desde el panel: es la llave
  maestra, y recuperarla exigiría entrar a la base de datos.
- **`CatalogoPermisos`** es código y no tabla: un permiso existe porque una
  ruta lo exige. `tests/Unit/Modules/Sistema/CatalogoPermisosTest.php` falla
  si una ruta nueva pide un permiso que el catálogo no ofrece — que es como
  una sección queda inalcanzable para todo rol sin comodín.
- **La auditoría es de solo lectura** en el panel, y append-only en la base
  (un disparador rechaza el `DELETE`). Un registro que la aplicación pueda
  alterar no prueba nada ante un ente de control.
- **El menú del panel se agrupó en cinco secciones** y se filtra por permisos:
  mostrar secciones que van a responder 403 hace perder el tiempo y siembra la
  duda de si el panel está roto.

### 2.x La portada dependía de Alpine.js — resuelto el 2026-09-15

Queda anotado porque el fallo era **silencioso** y puede volver por copiar y
pegar de un prototipo.

`inicio.php` traía 27 directivas de Alpine (`x-data`, `x-text`, `x-show`,
`@click`) y `base.php` otras tantas en un chat. **Alpine nunca se instaló.**

Conste para no repetir el error de diagnóstico: Alpine **sí** está en el stack
aprobado (CLAUDE.md §3, y §6 «solo para interactividad puntual»). Lo prohibido
es React, Vue, jQuery y Bootstrap. El problema no era la herramienta sino
usarla sin cargarla, y sus directivas no fallan de forma ruidosa: se quedan
calladas.

Medido en el navegador antes de arreglarlo: los 9 elementos `x-text` **vacíos**, las dos pestañas de
campañas visibles a la vez, la encuesta mostrando «¡Gracias por participar!»
de entrada y escondiendo las opciones, y el panel del chat abierto y fijo en
todas las páginas, tapando contenido.

Encima, el sitio público sirve `style-src 'self'` sin `'unsafe-inline'`
(`Kernel.php:211`), así que los 9 `style="…"` se **descartaban**. Las barras
del héroe no se pintaban a cero sino **al 100 %**, al lado de un texto que
decía «98.4 %».

Qué se hizo:

- Anchos y demoras, a clases: valores arbitrarios de Tailwind (`w-[98.4%]`,
  `[animation-delay:0.3s]`) cuando el literal está en el código, y las clases
  cuantizadas `.barra-N` de `app.css` cuando el ancho sale de un dato.
- Campañas y encuesta, renderizadas en PHP con los datos que el controlador ya
  pasaba y **no usaba**. Se fueron las campañas inventadas —incluida una que
  invitaba a «Ingresar a Intranet», retirada por decisión del 2026-09-14— y la
  encuesta que guardaba los votos en una variable de JavaScript.
- Cifras del héroe, a indicadores reales (`indicadores.destacado`), con su
  periodo y su fuente. Sin datos marcados, las barras no se pintan.
- Chatbot retirado. Era un **chatbot** automatizado (cadena de `if` en el
  navegador con respuestas fijas), y eso está en «Fuera de v1» de CLAUDE.md §7.
  El **chat en vivo** aprobado el 2026-09-14 es otra cosa y sigue pendiente de
  construir, detrás de un `Port` y apagado por defecto. Además daba teléfonos y
  dirección que **no coinciden** con la configuración del sitio.
- `CampanaRepository::publicasVigentes()` sustituye a `listarParaPanel()` en la
  portada: la anterior mostraba **borradores** cuando no había nada publicado.

Lo vigilan `VistasSanasTest` y `PortadaTest`. La prueba de Alpine no prohíbe
la herramienta: exige coherencia —si alguna plantilla trae directivas, el
cascarón tiene que cargar Alpine—, así que el día que se instale deja de
estorbar sola. Consola en `/`:
de 7 errores distintos a **cero**.

Pendiente menor: las campañas no tienen página propia (`/campanas/{slug}` no
existe), así que en portada salen título y resumen sin enlace a más detalle.
### 2.z La portada, editable sin desplegar — 2026-09-15

Lo que se veía primero al entrar al sitio era lo único que no se podía tocar
sin tocar código.

**Las ocho tarjetas de accesos rápidos** (Citas, Urgencias, Especialidades,
Directorio médico, Trámites, Consulta tu trámite, PQRSDF, Transparencia)
estaban en un array de PHP dentro de `inicio.php`, con título, frase, URL,
trazo del icono y color. Ahora son el menú de ubicación **`accesos`**, que se
edita en Panel → Menús del sitio. Se reutilizó la maquinaria de menús en vez
de inventar una tabla: etiqueta, dirección, orden, visibilidad, jerarquía y
auditoría ya existían. Solo faltaban dos piezas, que trae
`Version20260915180000`: la columna `menu_items.descripcion` (la frase bajo el
título) y el valor `accesos` del ENUM de ubicaciones.

El icono se **elige**, no se pega: `CatalogoIconos` guarda clave → trazo en el
servidor y la base solo almacena la clave. Dejar pegar SVG desde un formulario
sería admitir marcado ajeno —un `<svg>` acepta `<script>` y `onload`— en la
página más visitada, y la CSP no debería ser la única defensa.

Por la misma razón, `MenusController::urlAceptable()` filtra la dirección al
guardar: lista de permitidos (`/ruta`, `#ancla`, `http(s)://`, `mailto:`,
`tel:`), y fuera `javascript:`, `data:` y `//otrodominio`. Escapar el valor no
protege de `javascript:` porque no hay nada que escapar. Lo vigila
`MenusAdminTest`.

**El héroe** pasó por dos etapas el mismo día. Primero se descubrió que decía
ser «configurable desde panel» y no lo era: la plantilla leía claves `hero.*`
que **no existían en la tabla**, así que el panel no las mostraba y el sitio se
quedaba siempre con el valor por omisión del código. Se sembraron.

Pero eso solo arreglaba la mitad: seguía siendo **un** bloque, de texto, sin
imagen, editado en campos sueltos de un formulario largo. Eso no es un héroe.
La tabla `heroes` (`Version20260915210000`) lo convierte en lo que el hospital
esperaba: **varias diapositivas**, cada una con su fotografía a sangre, su
texto, hasta dos botones, orden, interruptor y vigencia; se administran en
**Panel → Héroe de la portada**, una sola pantalla. Los ocho textos que estaban
en Ajustes se **movieron** a la primera diapositiva dentro de la propia
migración, copiándolos antes de borrarlos para no perder lo escrito.

Lo que se quedó en Ajustes es lo que **no se repite** por diapositiva: el texto
guía del buscador, los cinco textos de la tarjeta ilustrada, los dos avisos
flotantes y «Segundos por héroe».

Detalles que no son estética:

- **La tarjeta «Rosario Digital» sale una sola vez**, en la diapositiva que la
  tenga marcada. Se guarda por fila y no como «la primera», porque «la primera»
  cambia en cuanto alguien reordena. El panel la apaga en las demás al marcarla
  en una, y la plantilla lo vuelve a garantizar por si una semilla o un arreglo
  a mano en la base dejaran dos marcadas. Donde hay fotografía no se dibuja:
  ocupan el mismo sitio.
- **El buscador vive fuera del carrusel.** Si rotara con las diapositivas,
  desaparecería en mitad de lo que alguien está escribiendo.
- **El velo de las fotografías va en dos capas.** El hospital sube la foto que
  quiera, y con un solo degradado una foto clara dejaba el párrafo en 3,4:1,
  por debajo del 4,5:1 de la WCAG 2.2 (1.4.3). Una capa pareja del 40 % pone un
  suelo de oscuridad pase lo que pase y el degradado carga la izquierda, que es
  donde va el texto: en la zona de lectura no baja del 88 %, que sobre blanco
  puro deja el texto en 5,9:1. Por lo mismo, sobre fotografía el título no usa
  `texto-degradado`: ese azul se queda en 1,8:1.
- **Sin JavaScript se ven todas las diapositivas**, una debajo de otra, y los
  mandos no se dibujan. Mismo patrón —y misma razón de capas de Tailwind— que
  el carrusel de campañas. `heroes.js` no usa Alpine: es un atributo que
  cambia, no un componente con estado.

Y dos cosas que se apagaron a propósito:

- Los avisos flotantes —«Normal (sin esperas)», «Agendar en 2 minutos»—
  afirman cómo está el servicio **ahora mismo** y nada en el sistema lo mide.
  Vienen **apagados** (`hero.avisos_visibles`), por la misma regla que sacó del
  héroe las cifras escritas a mano. Encenderlos es una decisión de alguien que
  se haga cargo de mantenerlos ciertos.
- El distintivo `PORTAL V2.6` nace vacío: un número de versión del portal no le
  dice nada al ciudadano.

Lo vigilan `PortadaTest` (lo que se ve en el sitio) y `HeroesAdminTest` (lo que
el panel deja guardar).

**Pendiente menor:** los héroes no tienen selector de la mediateca. Se sube una
imagen nueva por cada diapositiva; reutilizar una ya cargada exige volver a
subirla. Campañas y noticias tienen el mismo límite, así que arreglarlo es un
selector compartido, no un parche del héroe.

## 3. Pendientes que dependen del hospital

1. **Oficina jurídica:** validar los términos legales provisionales de los
   trámites (plazos Ley 1755) y las políticas de datos/cookies/privacidad.
   Todo es editable desde el panel (Políticas y Tipos de trámite) —
   marcado como PROVISIONAL desde la Fase 5/6.
2. **Contenido real (el cuello de botella de la Fase 9).** La base está casi
   vacía: 0 páginas institucionales, 0 documentos, 0 FAQ, 0 profesionales,
   1 noticia de prueba. Alguien del hospital debe clasificar documentos
   (serie/subserie TRD, dependencia, vigencia) y redactar/aprobar páginas.
   El panel ya lo soporta todo; con IA activa, ayuda a resumir y etiquetar.
3. **Responsables de la bandeja de trámites:** definir qué dependencia
   responde cada tipo de trámite y crear sus usuarios del panel.
4. **Autorización escrita de cada profesional** para publicar su perfil
   (la regla ya la exige el sistema).
5. **Claves de API de IA** (Anthropic u OpenAI) si quieren el asistente;
   se activa en Panel → IA.
6. **Verificar el Anexo 2 vigente** de la Res. 1519 contra lo sembrado en
   Transparencia (revisión normativa periódica).
7. **Auditoría con lector de pantalla real** (NVDA/TalkBack) por una
   persona; guion de 5 rutas en `docs/11`.
8. **Producción:** dominio (hrplopez.gov.co), servidor con HTTPS,
   credenciales de BD y correo SMTP institucional.
9. **Cargar los indicadores estadísticos** (Panel → Indicadores). La página
   `/estadisticas` existe y funciona, pero sale vacía a propósito: no se
   sembró ninguna cifra porque inventar datos de contratación u ocupación de
   un hospital público no es una opción. Cada carga es un mes, con su fuente.
   Quien tiene el dato: Planeación (contratación y ocupación) y Estadística
   (vitales).

## 4. Tareas programadas a configurar en producción

| Comando | Frecuencia | Qué hace |
|---|---|---|
| `bin/consola contenido:programado` | Cada minuto | Publica/despublica contenido con fecha programada |
| `bin/consola tramites:alertas` | Diaria (madrugada) | Avisa trámites vencidos o por vencer (3 días) |
| `bin/consola busqueda:indexar` | Diaria (nocturna) | Red de seguridad del índice de búsqueda |
| `bin/consola semillas:festivos` | Anual (enero) | Festivos del año actual y los dos siguientes |
| Respaldos | Diaria | **Pendiente del módulo** (2.4) |

## 5. Fase 9 — lo que resta del plan

- Migración de contenido desde el Joomla (con el hospital, ver 3.2).
- Carga de documentos con metadatos (hospital clasifica, panel ya listo).
- Verificar cero enlaces rotos con un rastreador (las 301 ya están
  sembradas y probadas; falta la pasada completa contra el sitio cargado).
- Pruebas de carga y de seguridad sobre el entorno de producción.
- Respaldos programados + **restauración probada en entorno limpio** (2.4).
- Manual de usuario del panel y capacitación de los administradores.
- Plan de reversión del lanzamiento.
- Verificar cabeceras con herramienta externa (ya emitidas por el Kernel;
  HSTS se activa solo bajo HTTPS).

## 6. Checklist de despliegue (cuando haya servidor)

1. `.env` de producción: BD, `APP_URL=https://hrplopez.gov.co`,
   `APP_ENTORNO=produccion`, SMTP real, claves IA si aplica.
2. `composer install --no-dev` y `composer migrate`.
3. Semillas en orden: `configuracion`, `paginas`, `menus --reconciliar`, `directorio`,
   `transparencia`, `festivos`, `tramites`, `cumplimiento`,
   `redirecciones` (todas idempotentes, nunca pisan lo editado).
   `paginas` va **antes** que `menus`: el menú enlaza esas páginas y un ítem
   apuntando a una página inexistente responde 404 (docs/14 §5.1).
   `menus --reconciliar` retira los ítems que ya no están en el plano y pone
   las etiquetas al día; sin la opción el sembrador solo añade y una
   instalación vieja acumula raíces duplicadas. Nunca toca ítems protegidos.
4. `usuarios:crear-admin` con correo institucional (y cambiar contraseña —
   ver pendiente 2.1).
5. `busqueda:indexar` tras cargar contenido.
6. Programar las tareas de la sección 4.
7. Verificar HTTPS + HSTS, probar instalación PWA en Android y correr
   Lighthouse contra producción.

## 7. Rarezas del entorno de desarrollo (para quien retome)

- **Nunca sembrar ni editar datos con la CLI de `mysql` en Windows.** Su
  `character_set_client` es **cp850**, así que los caracteres no ASCII se
  corrompen al escribir: un guion `–` acaba almacenado como `ÔÇô`. Pasó con
  `sedes.horario` y solo se vio en la portada. La aplicación conecta en
  utf8mb4 y escribe bien, así que todo lo que toque datos va por
  `php bin/consola`. Para consultar desde la CLI, `--default-character-set=utf8mb4`.
- **Cuidado al buscar mojibake con `LIKE`:** la colación `utf8mb4_0900_ai_ci`
  es insensible a acentos y mayúsculas, de modo que `LIKE '%ÔÇ%'` se comporta
  como `'%oc%'` y devuelve medio sitio. Para detectarlo hay que comparar bytes
  desde PHP, e incluir las columnas **JSON** (ahí estaba el caso real).

- Servidor local: `php -S 127.0.0.1:8090 -t public public/index.php` —
  **con el router**, o las URL del Joomla y las redirecciones parecen rotas.
  Matar servidores previos: un proceso viejo retiene el puerto.
- BD dev `hrpl`, tests `hrpl_pruebas` (phpunit migra sola). Mailpit captura
  el correo (`:8025`).
- Admin local: `elkin985@gmail.com` / `CambiarYa2026!`.
- MySQL 8: no repetir `MATCH` con aritmética en `ORDER BY` (error 1690);
  el FULLTEXT no hace stemming en español (por eso el buscador usa
  prefijos booleanos).
- CSS: `npm run css` tras tocar `resources/css/app.css`.

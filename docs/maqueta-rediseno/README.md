# Maqueta del rediseño del portal (noviembre de 2026)

Maqueta estática para aprobación de la gerencia. **No es código de producción**:
no usa Tailwind ni las plantillas de `resources/views/publico/`. Si se aprueba,
el lenguaje visual se traslada a esas plantillas y a `resources/css/app.css`.

## Origen

La gerencia no aprobó el diseño vigente y pidió replantearlo tomando como
ejemplo dos sitios:

- `clinicaaltacomplejidad.com/nosotros/` — hero de color con onda decorativa,
  tarjetas de servicio con fotografía, trama de puntos, pie a cuatro columnas.
- `hospitalmedellin.sanvicentefundacion.com/nuestras-entidades/hospital-medellin`
  — barra de menú sólida, migas de pan, banner a todo ancho en páginas
  interiores, pestañas de sección anguladas, barra flotante de acciones.

## Decisiones

| Punto | Decisión |
|---|---|
| Paleta | **Sin cambios.** `#1D5BA7` · `#0065AE` · `#0997D5` · `#35BDE9` · verde `#2F9A72` · tinta `#103463` |
| Tipografías | Montserrat + Work Sans, autoalojadas (`CLAUDE.md` §6 prohíbe Google Fonts por CDN) |
| Encabezado | Barra azul sólida, patrón de San Vicente |
| Hero | Fotografía a todo ancho con velo azul, tarjeta flotante de horarios/urgencias/sede y onda decorativa |
| Fotografía | Real, del Portafolio de Servicios 2026 (`database/seeders/portafolio/`) |
| Tema del sistema | **No afecta la página.** Sin `prefers-color-scheme`; el modo oscuro es solo un botón de accesibilidad y usa el atributo propio `data-tema` |
| Carrusel | Automático cada 5,5 s, con botón de pausa (WCAG 2.2.2). Cada diapositiva es un enlace |
| Orientador | Árbol de decisión fijo, sin IA y sin dato clínico |

## Ajustes pedidos por la gerencia (17 de noviembre de 2026)

1. **Velo del hero más claro.** Pasó de un degradado celeste saturado a azul
   marino de marca con 0,86–0,14 de opacidad, de modo que la fotografía se ve.
   Sobre la columna de texto nunca baja de 0,78, que da 8,3:1 con el texto
   blanco. En móvil el velo se vuelve vertical (0,70–0,78) porque el texto
   cruza toda la foto.
2. **Cambio automático de imagen.** Cada 5,5 s. Se detiene mientras el cursor
   o el foco están sobre el hero —la diapositiva es un enlace y nadie debe
   perder el clic— y con la pestaña en segundo plano.
3. **Hero enlazable.** Cada diapositiva lleva a su destino. En el portal real
   falta una columna para esto: la tabla `heroes` tiene `boton1_url` y
   `boton2_url`, pero **no un enlace para la imagen completa**. Requiere una
   migración que añada `enlace_imagen VARCHAR(255) NULL`.
4. **Orientador del portal.** Ver la nota de alcance más abajo.
5. **Responsivo.** Menú de cajón real con las siete raíces plegadas, barra de
   acciones inferior rotulada, barra de accesibilidad deslizable en una fila,
   áreas táctiles de 44 px. Verificado sin desbordamiento horizontal en 360,
   390, 768, 1024, 1300 y 1536 px.

## Segunda ronda (17 de noviembre de 2026)

La gerencia objetó que la izquierda seguía oscura y que la tarjeta de
horarios tapaba la fotografía. Entre el velo y la tarjeta quedaba visible
menos de la mitad de la imagen.

1. **La tarjeta sale del hero.** Pasó a ser la `.banda-servicio`: tres
   columnas montadas sobre el borde inferior del hero. La información se
   sigue viendo sin desplazar la página y la foto queda libre.
2. **Velo al mínimo normativo: 0,62.** Sobre el peor caso —una zona blanca
   de la fotografía— compone `#666D7D`, que mide **5,17:1** con el texto
   blanco. Por debajo de 0,58 el párrafo ya no llega a los 4,5:1 que exige
   WCAG 1.4.3, así que 0,62 es el suelo real. Del 55 % hacia la derecha baja
   hasta 0,08 y la imagen se ve prácticamente limpia.
3. **Menos texto en el hero**, para reducir la superficie que necesita velo.

> **Por qué no se puede quitar del todo:** texto blanco directamente sobre
> fotografía obliga a oscurecerla. Las únicas alternativas sin velo son
> sacar el texto a un panel de color al lado de la foto o meterlo en una
> tarjeta sólida; ambas se propusieron y la gerencia escogió mantener la
> foto a todo ancho.

### Encabezado en dos filas

Medido: en una sola fila el encabezado pide **1303 px** y la retícula del
sitio ofrece **1136**. Encoger la tipografía hasta que cupiera la dejaba por
debajo de lo legible, así que el menú pasó a su propia fila —como ya hace el
portal en producción— y dispone de los 1136 px completos. El menú mide
955 px, de modo que el corte al cajón va en **1100 px**.

## Nota de alcance: el orientador

`CLAUDE.md` §7 lista **«Chatbot de cara al ciudadano»** en *Fuera de v1 — no
construir*, mientras que **«Chat en vivo»** sí entra (decisión del 2026‑09‑14,
detrás de un `Port` y apagado por defecto).

Lo que hay en esta maqueta es la opción que **no** choca con esa regla: un
orientador de árbol de decisión fijo, sin modelo de lenguaje, que solo enruta
a un trámite, un teléfono o una página. No pregunta síntomas, no responde
consultas clínicas y no recibe texto libre, de acuerdo con §2.6.

Si la gerencia quiere un chatbot conversacional con IA de cara al ciudadano,
es una modificación del alcance contratado y debe decidirse antes de
construirlo.

## Qué se omitió a propósito

Carrito, «Ingresa» / inicio de sesión ciudadano y «Dona aquí» de los referentes:
no aplican a una E.S.E. y el portal del colaborador está fuera del alcance v1
(`CLAUDE.md` §7).

## Qué se conservó por obligación normativa

Barra GOV.CO, barra de accesibilidad, menú de Transparencia, PQRSDF, buscador,
Marca País CO, «Conoce a GOV.CO», «Vigilado Supersalud» y la leyenda de la
Ley 1712 de 2014 y la Resolución 1519 de 2020.

## Archivos

- `index.html` — portada
- `el-hospital.html` — plantilla de página interior
- `estilos.css` — tokens y componentes
- `portal.js` — carrusel, menú móvil, orientador y modo oscuro
- `img/` y `*.woff2` — **no versionados**: son copias de archivos que ya
  están en el repositorio en su origen (ver abajo)

## Cómo abrirla

Los binarios no se duplican en git. Antes de servir la maqueta hay que
copiarlos desde donde ya viven:

```sh
mkdir -p docs/maqueta-rediseno/img
cp database/seeders/portafolio/*.webp docs/maqueta-rediseno/img/
cp public/img/logo_icono.png public/img/logo_gov.co.png docs/maqueta-rediseno/img/
cp public/fuentes/*.woff2 docs/maqueta-rediseno/
# El SVG de Marca País lleva un DOCTYPE que algunos visores rechazan:
sed '/<?xml/d; /<!DOCTYPE/d' public/img/marca_co.svg > docs/maqueta-rediseno/img/marca_co.svg
```

Después hay que **servirla**, no abrirla por `file://`: las fuentes
autoalojadas no cargan desde el sistema de archivos en todos los navegadores.

```sh
php -S localhost:8080 -t docs/maqueta-rediseno
```

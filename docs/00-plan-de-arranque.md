# Plan de arranque — E.S.E. Hospital Rosario Pumarejo de López

**Fecha:** 2026-08-14
**Estado del repositorio:** vacío. Solo existe `docs/`. Cero código, cero `git init`.

Este documento no reemplaza a `plan-de-desarrollo.md`. Lo aterriza: qué se hace
esta semana, con qué herramientas, y qué decisiones están bloqueadas.

---

## 1. Verificación del entorno (hecha)

| Componente | Requerido por `CLAUDE.md` | Instalado en Laragon | Veredicto |
|---|---|---|---|
| PHP | **8.4+** | **8.3.30** (ZTS, VS16 x64) | ⚠️ **No cumple** |
| MySQL | 8+ | 8.4.3 | ✅ |
| Composer | — | 2.9.4 | ✅ |
| Node / npm | — | 26.2.0 / 11.13.0 | ✅ |
| Git | — | 2.54.0 | ✅ |
| Redis | Caché, con fallback MySQL | Servidor sí, **extensión `redis` de PHP no** | ⚠️ Usar `predis` o el fallback |
| Mailpit | — | Sí | ✅ Ideal para probar PHPMailer sin enviar correo real |

**Extensiones PHP presentes:** `pdo_mysql`, `mbstring`, `intl`, `gd`, `fileinfo`,
`exif`, `curl`, `openssl`, `sodium`, `zip`, `dom`, `xml`.
`sodium` presente → Argon2id disponible. `intl` presente → formato de fechas en español.
**Falta:** `opcache` (no aparece cargado; revisar `php.ini` antes de medir rendimiento).

---

## 2. Discrepancias encontradas en la documentación

Ninguna es grave, pero conviene corregirlas antes de escribir código.

1. **PHP 8.4 vs 8.3.** `CLAUDE.md` §3 exige 8.4+. Laragon trae 8.3.30.
   Es un cambio de stack → §10 obliga a preguntar antes de asumir. Ver §6.
2. **Ruta del esquema.** `CLAUDE.md` referencia `database/esquema.sql`, pero el
   archivo está en `docs/esquema.sql`. Al crear la estructura, el esquema pasa a
   `database/esquema.sql` como referencia y se corrige la ruta en el documento.
3. **`CLAUDE.md` vive en `docs/`, no en la raíz.** Funciona, pero lo convencional
   es la raíz. Se mueve o se duplica al crear la estructura.
4. **`docs/BACKLOG.md` no existe** y la regla de oro #1 obliga a escribir ahí toda
   idea fuera de alcance. Se crea vacío en el primer commit.

---

## 3. Herramientas de diseño — qué se usa y qué NO, y por qué

Hay 115 skills instaladas a nivel de usuario. **La mayoría de las skills de diseño
"premium" instaladas están hechas para landing pages de producto y contradicen
las restricciones duras de este proyecto.** Elegir mal aquí no es un detalle
estético: rompe el presupuesto de rendimiento y la accesibilidad AA, que son
exigibles por norma.

### Se usan

| Skill | Rol en este proyecto |
|---|---|
| **`impeccable`** | **El auditor.** 59 reglas detectoras de antipatrones: jerarquía visual, contraste, tipografía, foco, estados de error, dark mode, i18n. No asume framework. Es la que valida cada pantalla contra WCAG 2.1 AA antes de dar algo por terminado. |
| **`frontend-design`** (plugin ya activo) | **El director de arte.** Dirección estética y tipográfica para que no parezca plantilla. Se usa al definir el sistema de diseño, una sola vez. |
| **`minimalist-ui`** | Registro editorial, monocromo cálido, sin degradados ni sombras pesadas. Es el lenguaje visual correcto para una entidad pública de salud, y el más barato de renderizar. |
| **`apple-design`** | Solo sus **fundamentos**: tipografía (optical sizing, tracking, leading), jerarquía, retroalimentación, contención, `prefers-reduced-motion`. Se ignora todo lo de gestos y springs. |
| **`emil-design-eng`** | Pulido de detalles: estados de foco, transiciones de 150–200ms, estados vacíos, estados de carga. Sobre todo en el panel. |
| **`animate`** | Motion puntual, dentro del presupuesto de JS. En la práctica: View Transitions API y CSS. |
| **`dataviz`** | Las gráficas de Chart.js del panel (bandeja de trámites, transparencia). Paleta accesible y consistente. |
| **`brandkit` / `imagegen-frontend-web`** | **Congeladas hasta que llegue el manual de identidad.** Generar una marca ahora significa inventar una identidad que contradice la del hospital. |

### Se descartan, con motivo

| Skill | Por qué no |
|---|---|
| `gpt-taste` | Impone GSAP con ScrollTrigger y pinning. GSAP solo ya supera buena parte del presupuesto de 100KB de JS, y el scroll-jacking rompe navegación por teclado y `prefers-reduced-motion`. Contradice `CLAUDE.md` §6 en dos frentes. |
| `high-end-visual-design` | Su estética se apoya en sombras densas y capas. Cuesta píxeles de pintado en gama baja y empuja el contraste hacia lo decorativo. |
| `design-taste-frontend` | Orientada a landing pages y portafolios con JS generoso. El objetivo aquí no es impresionar: es que un señor con 3G encuentre el teléfono de urgencias. |
| `industrial-brutalist-ui` | Registro equivocado para una entidad pública de salud. |
| `image-to-code`, `stitch-design-taste` | Generan diseño a partir de imágenes inventadas. Sin manual de identidad, producen algo que habrá que rehacer. |

> **El criterio:** *premium* en este proyecto significa que carga en 1,5s en un
> celular malo, que se navega entero con teclado, que la tipografía respira y que
> el contraste es real — no que tenga parallax. `CLAUDE.md` §2 regla 4 y `02-valoracion`
> Parte F ya lo dicen: si hay que elegir entre una funcionalidad y que cargue
> rápido en Valledupar con 3G, gana la velocidad. Siempre.

---

## 4. Skills a instalar (huecos reales, no cubiertos por las 115 actuales)

Ninguna de las 115 instaladas cubre PHP, accesibilidad, Playwright ni Tailwind.
Son las cuatro tecnologías centrales del proyecto.

| Skill | Instalaciones | Por qué |
|---|---|---|
| `jeffallan/claude-skills@php-pro` | 13.2K | Es el lenguaje del proyecto y hoy no hay ninguna skill de PHP. |
| `mastepanoski/claude-skills@wcag-accessibility-audit` | 1.1K | WCAG 2.1 AA es **exigible por norma desde 2022**. `impeccable` audita diseño; esta audita conformidad. |
| `heygen-com/hyperframes@tailwind` | 71.9K | Tailwind es el CSS del proyecto. |
| `bobmatnyc/claude-mpm-skills@playwright-e2e-testing` | 2.8K | Playwright es la herramienta E2E que exige `CLAUDE.md` §3. |

> Son skills de terceros y corren con permisos completos del agente. Son
> instrucciones, no código ejecutable, pero la decisión de instalarlas es del usuario.

**No se instalan:** skills de SEO (la Fase 7 ya está especificada y las opciones del
catálogo son marketing, no SEO técnico) ni de MySQL (el catálogo solo ofrece cosas
de Azure/PlanetScale, irrelevantes aquí). Seguridad ya está sobradamente cubierta por
las 78 skills de Trail of Bits: `semgrep`, `codeql`, `differential-review`, `fp-check`.

---

## 5. MCPs a configurar

Hoy no hay ningún MCP local configurado. Tres aportan valor directo y verificable:

| MCP | Paquete | Para qué sirve **en este proyecto** |
|---|---|---|
| **Chrome DevTools** | `chrome-devtools-mcp` (Google, v1.7.0) | Medir LCP real, trazas de rendimiento y CPU/red simuladas. Es la única forma honesta de verificar «LCP < 2,5s en 3G» en vez de afirmarlo. |
| **Playwright** | `@playwright/mcp` (Microsoft, v0.0.79) | Navegar el sitio con teclado, capturar el árbol de accesibilidad, verificar foco y `label` asociado. Cubre los criterios de aceptación de accesibilidad de casi todas las fases. |
| **Context7** | `@upstash/context7-mcp` (v4.0.2) | Documentación actualizada de Symfony 7, Doctrine DBAL/Migrations, Tailwind v4, Alpine.js y CKEditor 5. El stack es sensible a versiones y la documentación de estos componentes cambió mucho. |

**No se configura MCP de MySQL:** un MCP con credenciales de escritura a la base de
datos es superficie de riesgo innecesaria. Las migraciones de Doctrine y el cliente
`mysql` de Laragon cubren el caso.

---

## 6. Decisiones — resueltas el 2026-08-14

| # | Decisión | Resolución |
|---|---|---|
| **A** | ¿PHP 8.4 o 8.3? | ✅ **PHP 8.3.30.** Se corrigió `CLAUDE.md` §3 para que el documento no contradiga al código. Prohibido usar *property hooks* y visibilidad asimétrica. |
| **B** | Manual de identidad institucional | ✅ **No disponible → tokens provisionales.** El sistema de diseño se construye sobre tokens en un único archivo; la paleta provisional se deriva del Joomla actual. Sustituirla cuando llegue el manual es editar ese archivo, no rehacer pantallas. |
| **C** | Skills de terceros a instalar | ✅ **Ninguna.** Se avanza con las 115 ya instaladas. Consecuencia asumida: la conformidad WCAG y el presupuesto de rendimiento se verifican con los MCP de Chrome DevTools y Playwright, no con una skill dedicada. |
| **D** | MCPs | ✅ **Los tres configurados** (ámbito local, en `C:\Users\ELKIN\.claude.json`): `chrome-devtools`, `playwright`, `context7`. |
| **E** | Preguntas 1, 2 y 9 de `02-valoracion` Parte H | ⏳ **Pendientes, no bloquean la Fase 0 pero sí la Fase 5.** Hay que iniciarlas ya con el hospital porque dependen de terceros. |
| **F** | Referencia visual del sitio público | ✅ **2026-08-21: `frontend.png` (raíz del repo) es el diseño objetivo.** Supersede la paleta «derivada del Joomla» de la decisión B: los tokens del paso 9 se derivan de esa imagen (azul primario, héroe con foto, 8 accesos rápidos), verificando contraste AA. `stitch_hospital_digital_experience_home/` es un borrador anterior y no manda. |

> **Nota 2026-08-21 — reinstalación del sistema operativo.** Se perdió la base
> `hrpl` local sin respaldo. Sin consecuencias: la fase iba por el paso 3 y no
> existían migraciones ni datos. Se recreó vacía con `utf8mb4_0900_ai_ci`.

### Nota sobre la ubicación de `CLAUDE.md`

Se deja en `docs/`. Claude Code lo detecta correctamente desde ahí y moverlo solo
añadiría ruido al historial. La discrepancia de la ruta del esquema (§2, punto 2)
**sí** se corrige: el esquema pasa a `database/esquema.sql`.

---

## 7. Fase 0 — desglose ejecutable

Diez pasos. Cada uno termina en algo verificable y en un commit. Ninguno depende
del manual de identidad salvo el paso 9, que se hace con tokens provisionales.

| # | Paso | Verificable cuando… | Estado |
|---|---|---|---|
| 0 | `git init`, `.gitignore`, `.gitattributes`, `.editorconfig`, `docs/BACKLOG.md`, esquema a `database/esquema.sql` | El repositorio tiene historia y la estructura de `CLAUDE.md` §4 existe vacía | ✅ `e0bb8c9` |
| 1 | `composer.json`: componentes Symfony permitidos + Doctrine DBAL y Migrations + PHPUnit. PSR-4 `App\` → `src/` | `composer install` corre limpio y el autoload resuelve | ✅ `352d5f7` |
| 2 | Configuración por entorno (`.env` fuera del repositorio, `.env.example` dentro) | La app arranca en local sin credenciales en git | ✅ `2d7649a` |
| 3 | Contenedor de dependencias y router de Symfony, `public/index.php` como único punto de entrada | Una ruta `/` responde con las cabeceras de seguridad | ✅ `4652c60` |
| 4 | Conexión a base de datos + Doctrine Migrations funcionando | `migrations:migrate` sube y **baja** sin error | ✅ |
| 5 | Migraciones del grupo Sistema: `roles`, `dependencias`, `usuarios`, `archivos`, `configuracion`, `auditoria` | Las seis tablas existen y el `down()` las revierte | ✅ |
| 6 | Auditoría: servicio central, tabla append-only, sin `UPDATE` ni `DELETE` | Un insert queda; un intento de update falla | ⏳ siguiente |
| 7 | Autenticación: login, logout, Argon2id, bloqueo por intentos, regeneración de ID de sesión, CSRF, cookies `HttpOnly`/`Secure`/`SameSite=Strict` | Un usuario entra, ve el panel vacío y sale. Todo queda en `auditoria` |
| 8 | Roles y permisos granulares + almacenamiento de archivos (validación por contenido real, fuera del *document root*, entrega por controlador) | Un usuario sin permiso recibe 403; un `.php` renombrado a `.jpg` se rechaza |
| 9 | Sistema de diseño: tokens de color y tipografía, componentes base, modo oscuro y modos de accesibilidad + pipeline de Tailwind | Página de muestra de componentes con contraste verificado ≥ 4,5:1 |
| 10 | Cascarón del panel (navegación, sesión, layout responsivo) + PHPUnit + CI básica | Los tests pasan en CI y el panel se usa desde un celular |

**Criterios de aceptación de la fase** (los de `plan-de-desarrollo.md`, sin cambios):
un usuario inicia sesión, ve el panel vacío y cierra sesión · toda acción queda en
`auditoria` · las migraciones suben y bajan sin error · el sistema de diseño está
documentado con muestra de componentes · el contraste de la paleta está verificado.

---

## 8. Lo que este plan deliberadamente NO hace

- No adelanta nada de las Fases 1–9. `CLAUDE.md` §2 regla 1.
- No propone paleta ni tipografía definitivas sin el manual de identidad.
- No añade dependencias fuera de las permitidas en `CLAUDE.md` §3.
- No decide términos legales de trámites. Eso lo valida la oficina jurídica (Fase 5).

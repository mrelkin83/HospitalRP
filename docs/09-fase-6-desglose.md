# Fase 6 — Cumplimiento y datos personales · desglose ejecutable

**Inicio:** 2026-08-21 · Fase 5 terminada en `a71aaf6`.

| # | Paso | Verificable cuando… | Estado |
|---|---|---|---|
| 1 | Semillas: políticas provisionales (aviso de privacidad, cookies, accesibilidad — tratamiento de datos ya existe) + categorías documentales de la normativa real del hospital (ordenanzas, decretos, acuerdos, resoluciones, circulares, leyes, informes…) | Cada tipo de política tiene versión activa | ✅ |
| 2 | Panel de políticas: versiones con vigencia — crear una versión nueva activa la nueva y cierra la anterior (`vigente_hasta`), sin editar nunca una versión aceptada | La evidencia de autorización siempre apunta a la versión que el ciudadano vio | ✅ |
| 3 | Público: `/politica-privacidad-web`, `/politica-de-cookies`, `/accesibilidad` (además de `/politica-tratamiento-datos`) | Las cuatro páginas responden con la versión activa | ✅ |
| 4 | Banner de cookies con consentimiento granular que **bloquea**: puerta `hrplConsentimiento.permite('analiticas')` — ningún script de terceros puede cargarse sin pasar por ella | Sin aceptar, cero peticiones a terceros (hoy no hay ninguno; la puerta obliga a los futuros) | ✅ |
| 5 | Revocación de autorización (habeas data): radicar la revocatoria marca `revocado_en` en las autorizaciones del documento, queda auditada y notifica al oficial de cumplimiento | Revocar queda registrado y notifica al responsable | ✅ |
| 6 | Cierre: criterios de la fase | Los tres criterios se cumplen | ✅ |

**Notas de alcance**

- El sitio NO carga hoy ningún script de terceros (todo es autoalojado, CSP
  `script-src 'self'`). El banner y la puerta de consentimiento existen para
  que cualquier script futuro (analítica, mapas) DEBA pasar por ellos.
- Las políticas provisionales rigen mientras jurídica adopta las
  definitivas; se reemplazan creando una versión nueva en el panel.
- La revocatoria se registra al radicarse (marca inmediata + notificación);
  el hospital responde el trámite por el flujo normal de la bandeja.

---

## Cierre de la fase — 2026-08-21

**Los tres criterios de aceptación del plan:**

1. *Sin aceptar cookies analíticas, ningún script de terceros se carga* —
   hoy el sitio no carga NINGÚN recurso externo (verificado: cero scripts y
   cero estilos de otros orígenes en el HTML, y la CSP `script-src 'self'`
   lo impone). Todo script futuro debe registrarse vía
   `hrplConsentimiento.cuandoPermita('analiticas', …)`: sin opt-in no corre.
2. *Cada trámite tiene su registro en `autorizaciones_datos` con la versión
   correcta de la política* — probado: dos trámites radicados antes y
   después de un cambio de política apuntan cada uno a la versión que su
   ciudadano aceptó. Las versiones publicadas jamás se editan.
3. *Revocar una autorización queda registrado y notifica al responsable* —
   radicar la revocatoria de habeas data marca `revocado_en` (sin destruir
   la evidencia), queda auditada y notifica al oficial de cumplimiento
   (probado con el espía de correo). Modelo de confianza documentado: el
   hospital verifica identidad antes de suprimir datos.

**Además, correcciones de esta sesión:** sede única con datos reales del
hospital, canales verdaderos (PBX, call center, ventanilla, línea ética),
dominio hrplopez.gov.co, inventario completo del sitio Joomla
(`08-inventario-sitio-actual.md`) y categorías documentales de la
normatividad que el hospital ya publica.

**Fase 6 terminada.** Sigue la **Fase 7 — Búsqueda, SEO, PWA y accesibilidad**.

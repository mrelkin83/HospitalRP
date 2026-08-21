# Fase 8 — IA en el panel · desglose ejecutable

**Inicio:** 2026-08-21 · **Cierre:** 2026-08-21 · Fase 7 terminada en `ce4cdb7`.

| # | Paso | Verificable cuando… | Estado |
|---|---|---|---|
| 1 | Migración `ia_usos` (+ sección 10 en esquema.sql) | Sube y baja sin error | ✅ up→down→up verificado |
| 2 | Puerto `Ports\AiProvider` + DTOs + `FabricaAiProvider` | Los módulos solo conocen la interfaz | ✅ |
| 3 | Dos adaptadores: Anthropic y OpenAI (curl, con visión para el texto alternativo) | Cambiar proveedor = cambiar un select | ✅ |
| 4 | `AsistenteIa`: 5 funciones con prompts propios, registro de uso también en fallos | Test con proveedor falso | ✅ 5 tests |
| 5 | Panel → IA: activar/desactivar, proveedor, modelo, estado de claves, consumo 30 días y últimas llamadas | Un funcionario lo opera sin jerga | ✅ E2E con login real |
| 6 | Botones «con IA» en noticias (borrador, metadatos SEO), documentos (resumen, etiquetas) y medios (texto alternativo) | Solo aparecen con la IA activa; llenan el campo y nada más | ✅ E2E: 0 botones apagada, 2/2/1 activa |
| 7 | Cierre: criterios del plan + revisión de seguridad + suite | | ✅ ver abajo |

## Criterios de aceptación del plan — resultado

| Criterio | Resultado |
|---|---|
| Con la IA desactivada, todo el panel funciona igual | ✅ los botones ni se imprimen (condición en servidor), el endpoint responde 409 con mensaje claro, y ningún módulo depende del asistente |
| Cambiar de proveedor no requiere tocar código de los módulos | ✅ select en Panel → IA; `FabricaAiProvider` resuelve el adaptador; los módulos solo conocen `AsistenteIa` |
| Ninguna sugerencia se aplica automáticamente | ✅ el resultado llena el campo del formulario y muestra «revisa y edita antes de guardar»; guardar sigue pasando por la validación y sanitización normales |
| Registro de uso y consumo | ✅ `ia_usos` (tokens, duración, quién, éxito/fallo) + tabla de consumo de 30 días y últimas llamadas en el panel |
| Al menos dos proveedores | ✅ Anthropic (Claude) y OpenAI, ambos con imagen para el texto alternativo |

**E2E ejecutado con clave falsa:** la llamada real salió a la API de Anthropic,
el 401 quedó en `ia_usos` como fallo con su detalle, y el funcionario recibió
«el proveedor no respondió bien» sin filtrar nada técnico.

## Decisiones

- **Claves de API en `.env`, no en la tabla `configuracion`**: son credenciales
  de infraestructura, no contenido editable. El panel solo muestra si existen
  («sin clave en el servidor») y avisa cuando el asistente está marcado activo
  pero el proveedor elegido no tiene clave.
- **Resumen de documento**: el funcionario pega texto del PDF en el campo y
  pide el resumen (no hay extractor de PDF y añadir esa dependencia no está
  permitido por CLAUDE.md §3). Flujo documentado en la ayuda del campo.
- **Pedir sugerencias** exige solo sesión del panel; **aplicarlas** sigue
  limitado por los permisos de cada formulario. Configurar exige `sistema.ia`.
- CKEditor: `ia.js` detecta `element.ckeditorInstance` y escribe por el editor
  (texto plano → párrafos); sin editor, escribe el textarea y dispara eventos.

## Revisión de seguridad de la fase (differential-review, manual)

- **Claves**: nunca en BD, ni en HTML, ni en logs; los adaptadores las usan solo en la cabecera saliente.
- **CSRF**: el endpoint JSON valida `_token` (del `<meta name="csrf">`); la configuración usa el token del formulario.
- **SSRF**: las URL de los proveedores son constantes; nada del usuario viaja como URL.
- **Imágenes**: lista blanca de mime + decodificación base64 estricta + `getimagesizefromstring` (contenido real, no extensión) + tope de 5 MB.
- **Salida del modelo**: siempre texto hacia un campo editable; el guardado pasa por la sanitización de HTML existente. El JSON de metadatos se parsea con tope de tamaño y fallback a texto.
- **Errores**: al cliente va un mensaje genérico (502); el detalle queda en `ia_usos`, visible solo con permiso `sistema.ia`.
- **Costo/abuso**: solo usuarios autenticados del panel; todo uso queda contado por usuario. Sin límite por minuto (pocos funcionarios); si hiciera falta, el patrón del tope de `busquedas_log` aplica igual aquí.

## Notas operativas

- Para activar: definir `IA_CLAVE_ANTHROPIC` o `IA_CLAVE_OPENAI` en el `.env`
  del servidor y encender el asistente en Panel → IA.
- Semillas nuevas: `ia.activada=0`, `ia.proveedor=anthropic`,
  `ia.modelo=claude-sonnet-5` (idempotentes).
- Suite al cierre: **165 tests, 542 aserciones** en verde.

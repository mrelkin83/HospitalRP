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
| 10 · Migración y lanzamiento | ⏳ **Pendiente** | — | Ver sección 5 |

**Suite al corte: 165 tests, 542 aserciones, en verde.**
Último commit: `a4faf68`. Sin remoto git todavía (ver 2.7).

## 2. Pendientes técnicos (código por hacer)

Ordenados por importancia:

1. **Flujo de cambio de contraseña obligatorio.** La columna
   `usuarios.debe_cambiar_pass` existe y `Usuario` la lee, pero el login no
   obliga a cambiarla. El admin local sigue con la contraseña inicial.
   *Riesgo: cuentas creadas con contraseña temporal que nunca rota.*
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
   tope de `busquedas_log` aplica igual); panel para redirecciones 301 (hoy
   solo semilla + SQL); permisos granulares por rol (hoy solo existe
   Administrador con `*`; el modelo wildcard ya lo soporta).

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

# Sitio web E.S.E. Hospital Rosario Pumarejo de López

Sitio web institucional con panel administrativo completo.
Valledupar, Cesar — Colombia.

## Los dos criterios de éxito, en este orden

1. El ciudadano encuentra lo que busca desde el celular en **menos de 30 segundos**.
2. El hospital **nunca** necesita llamar a un desarrollador para cambiar algo del sitio.

Si algo mejora el segundo a costa del primero, gana el primero.

## Documentación

| Documento | Qué contiene |
|---|---|
| [`docs/CLAUDE.md`](docs/CLAUDE.md) | **Fuente de verdad.** Stack, arquitectura, convenciones, alcance. Si el código lo contradice, el código está mal |
| [`docs/00-plan-de-arranque.md`](docs/00-plan-de-arranque.md) | Estado del entorno, decisiones tomadas y desglose ejecutable de la Fase 0 |
| [`docs/plan-de-desarrollo.md`](docs/plan-de-desarrollo.md) | Las 10 fases con criterios de aceptación |
| [`docs/01-mapa-del-sitio-y-modelo-de-datos.md`](docs/01-mapa-del-sitio-y-modelo-de-datos.md) | Mapa del sitio, URLs y modelo de datos |
| [`docs/02-valoracion-de-alcance.md`](docs/02-valoracion-de-alcance.md) | Qué se construye, qué se recorta y por qué |
| [`database/esquema.sql`](database/esquema.sql) | Esquema de referencia. Se implementa como migraciones, no se ejecuta de una vez |
| [`docs/BACKLOG.md`](docs/BACKLOG.md) | Ideas fuera de alcance |

## Stack

PHP 8.3 · MySQL 8 · Componentes Symfony (sin framework completo) · Doctrine DBAL y
Migrations · Tailwind CSS · Alpine.js · CKEditor 5 · PHPUnit + Playwright

Sin React, sin Vue, sin jQuery, sin Bootstrap, sin ORM Active Record.

## Restricciones que no se negocian

- **LCP < 2,5s** en 3G simulado · **JS inicial < 100KB** comprimido
- **WCAG 2.1 AA** — exigible por norma desde 2022 (Res. 1519/2020, Anexo 1)
- Ningún dato clínico entra al sistema. Solo solicitudes
- Toda acción con efecto institucional deja auditoría
- Todo lo que un funcionario deba poder cambiar vive en base de datos, no en código

## Entorno de desarrollo

Laragon en Windows. Rutas locales:

```
PHP 8.3.30    C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe
MySQL 8.4.3   C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\
Composer 2.9  C:\laragon\bin\composer\composer.phar
```

Mailpit (incluido en Laragon) se usa para probar el envío de correo sin enviar nada real.

## Estado

**Fase 0 — Fundación: terminada** (2026-08-21).
**Fase 1 — Contenido y sitio público base: terminada** (2026-08-21). Home según
`frontend.png` con LCP 2,4 s en 3G simulado, noticias/páginas/campañas/avisos/FAQ
administrables con aprobación, versionado y auditoría, CKEditor autoalojado,
menús editables y modo emergencia. 120 pruebas.
**Fase 2 — Directorio y servicios: terminada** (2026-08-21). Sedes,
especialidades, servicios y directorio médico administrables y publicados, con
filtro por sede sin recargar, páginas de urgencias y contacto, y la regla dura
de que ningún profesional se publica sin autorización expresa. 123 pruebas.
**Fase 3 — Gestión documental: terminada** (2026-08-21). Documentos con ficha
completa (TRD, vigencia, actos), versiones con historial, estados
vigente/derogado/histórico con `reemplaza_a`, buscador con filtros (< 300 ms
sobre mil documentos, medido) y ficha HTML legible en móvil con descarga
contada. 128 pruebas.
Sigue la **Fase 4 — Transparencia y datos abiertos** según
`docs/plan-de-desarrollo.md`.

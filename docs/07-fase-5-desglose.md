# Fase 5 — Motor de trámites y PQRSDF · desglose ejecutable

**Inicio:** 2026-08-21 · Fase 4 terminada en `1dbca16`.

> ⚠️ **Términos legales PROVISIONALES.** El hospital aún no entrega los
> términos validados por la oficina jurídica ni la definición de quién
> atiende la bandeja. Se siembran los términos por defecto de la Ley 1755
> (editables desde el panel) y cada tipo queda marcado «provisional» en su
> fundamento legal. La validación jurídica ajusta números en el panel, no
> código.

| # | Paso | Verificable cuando… | Estado |
|---|---|---|---|
| 1 | Migración del grupo Trámites (8 tablas) + `festivos` + `politicas` y `autorizaciones_datos` (adelantadas: el criterio de la casilla las exige) | Sube y baja sin error | ⏳ |
| 2 | Núcleo: cálculo de días hábiles con `festivos` (semilla Colombia 2026–2027) y generador de radicado `HRPL-{AAAA}-{secuencia}` con bloqueo transaccional | El vencimiento salta fines de semana y festivos; el radicado es único bajo concurrencia (probado) | |
| 3 | Semillas: política de tratamiento provisional + 5 tipos de trámite (PQRSDF, cita, historia clínica, certificados, habeas data) con estados y campos | PQRSDF existe como tipo de trámite, no como tabla especial | |
| 4 | Radicación: servicio transaccional (radicado + valores dinámicos + adjuntos + evidencia de autorización + auditoría) y notificación por correo (puerto `NotificadorCorreo`, adaptador symfony/mailer → Mailpit) | Radicar deja todo el rastro y envía el correo | |
| 5 | Público: listado de trámites, formulario dinámico por tipo (casilla de autorización NO premarcada y obligatoria, anónimo si el tipo lo permite) y página de radicado exitoso | Un formulario sin casilla marcada no se envía | |
| 6 | Consulta pública por radicado + número de documento: estado y solo seguimientos `visible_ciudadano` | No expone datos de otros ni notas internas | |
| 7 | Bandeja del panel: filtros, asignación, cambio de estado con nota (pública o interna), semáforo de vencimiento | Un trámite próximo a vencer aparece destacado | |
| 8 | Panel: tipos de trámite configurables (tipo + estados + campos) | Se crea un tipo nuevo sin tocar código | |
| 9 | Alertas: comando de vencimientos (notifica responsables) + cierre de criterios | Los siete criterios se cumplen | |

**Notas de alcance**

- Sin cuenta de usuario ciudadano: la consulta es radicado + documento.
- La solicitud de historia clínica se radica en línea; la entrega es por
  canal controlado, jamás por el sitio (CLAUDE.md §6).
- El correo va detrás del puerto `NotificadorCorreo`; en local lo captura
  Mailpit. Si el envío falla, la radicación NO falla: se audita el error.
- Festivos sembrados 2026–2027 (Ley 51 de 1983); el panel de festivos llega
  con el módulo Sistema (por ahora, semilla + SQL).

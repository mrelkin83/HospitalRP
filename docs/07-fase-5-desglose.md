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
| 1 | Migración del grupo Trámites (8 tablas) + `festivos` + `politicas` y `autorizaciones_datos` (adelantadas: el criterio de la casilla las exige) | Sube y baja sin error | ✅ |
| 2 | Núcleo: cálculo de días hábiles con `festivos` (semilla Colombia 2026–2027) y generador de radicado `HRPL-{AAAA}-{secuencia}` con bloqueo transaccional | El vencimiento salta fines de semana y festivos; el radicado es único bajo concurrencia (probado) | ✅ |
| 3 | Semillas: política de tratamiento provisional + 5 tipos de trámite (PQRSDF, cita, historia clínica, certificados, habeas data) con estados y campos | PQRSDF existe como tipo de trámite, no como tabla especial | ✅ |
| 4 | Radicación: servicio transaccional (radicado + valores dinámicos + adjuntos + evidencia de autorización + auditoría) y notificación por correo (puerto `NotificadorCorreo`, adaptador symfony/mailer → Mailpit) | Radicar deja todo el rastro y envía el correo | ✅ |
| 5 | Público: listado de trámites, formulario dinámico por tipo (casilla de autorización NO premarcada y obligatoria, anónimo si el tipo lo permite) y página de radicado exitoso | Un formulario sin casilla marcada no se envía | ✅ |
| 6 | Consulta pública por radicado + número de documento: estado y solo seguimientos `visible_ciudadano` | No expone datos de otros ni notas internas | ✅ |
| 7 | Bandeja del panel: filtros, asignación, cambio de estado con nota (pública o interna), semáforo de vencimiento | Un trámite próximo a vencer aparece destacado | ✅ |
| 8 | Panel: tipos de trámite configurables (tipo + estados + campos) | Se crea un tipo nuevo sin tocar código | ✅ |
| 9 | Alertas: comando de vencimientos (notifica responsables) + cierre de criterios | Los siete criterios se cumplen | ✅ |

**Notas de alcance**

- Sin cuenta de usuario ciudadano: la consulta es radicado + documento.
- La solicitud de historia clínica se radica en línea; la entrega es por
  canal controlado, jamás por el sitio (CLAUDE.md §6).
- El correo va detrás del puerto `NotificadorCorreo`; en local lo captura
  Mailpit. Si el envío falla, la radicación NO falla: se audita el error.
- Festivos sembrados 2026–2027 (Ley 51 de 1983); el panel de festivos llega
  con el módulo Sistema (por ahora, semilla + SQL).

---

## Cierre de la fase — 2026-08-21

**Los siete criterios de aceptación del plan, todos verificados:**

1. *Crear un tipo de trámite nuevo desde el panel, sin tocar código* — E2E:
   se creó «Solicitud de carné de vacunación» por HTTP y quedó radicable en
   el sitio público al instante (nace con los estados estándar).
2. *El radicado es único bajo concurrencia* — probado con 4 procesos PHP
   reales en paralelo: 100 radicados, cero duplicados, secuencia sin huecos.
3. *El vencimiento salta fines de semana y festivos* — festivos de Colombia
   calculados por algoritmo (Ley Emiliani + Pascua) y casos reales de la
   Ley 1755 en tests (incluida una petición que cruza San Pedro y el 20 de
   julio).
4. *Un trámite próximo a vencer aparece destacado y notifica* — semáforo en
   la bandeja (vencidos primero, borde rojo/ámbar) y `tramites:alertas`
   envía el resumen al responsable (verificado en Mailpit).
5. *La consulta pública no expone datos de otros ni notas internas* — exige
   el documento del solicitante y una nota interna sembrada NO apareció en
   la consulta (E2E).
6. *Un trámite anónimo funciona sin identificación* — probado por test.
7. *Ningún formulario se envía sin la casilla de autorización* — la casilla
   jamás viene premarcada, el formulario lo rechaza con mensaje y el
   servicio lo bloquea por cualquier otra ruta; la evidencia queda en
   `autorizaciones_datos` con política, IP y fecha (Ley 1581).

**Pendientes que dependen del hospital (ya avisados):** términos validados
por jurídica (se ajustan en /admin/tipos-tramite), responsables de bandeja,
y programar `tramites:alertas` (diaria) y `contenido:programado` (por
minuto) en el servidor.

**Fase 5 terminada.** Sigue la **Fase 6 — Cumplimiento y datos personales**.

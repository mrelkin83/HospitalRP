# Plan de desarrollo — Sitio web E.S.E. Hospital Rosario Pumarejo de López

Diez fases. Cada una entrega algo verificable. No se pasa a la siguiente sin cumplir los criterios de aceptación.

Las estimaciones asumen un desarrollador con asistente de código, dedicación completa.

---

## Antes de la Fase 0 — Información que hay que conseguir

Ninguna es técnica, todas bloquean decisiones. Consíguelas en paralelo con la Fase 0, pero no llegues a la Fase 5 sin las tres primeras.

1. **¿Quién atiende la bandeja de trámites, en qué horario y con qué plazo?** Bloquea la Fase 5.
2. **Términos legales exactos por tipo de trámite**, validados por la oficina jurídica. Bloquea la Fase 5.
3. **Manual de identidad institucional** (escudo, logo, colores, tipografías). Bloquea la Fase 0.
4. Organigrama y listado de dependencias con responsables.
5. Tablas de Retención Documental adoptadas.
6. Política de tratamiento de datos vigente y responsable designado.
7. Inventario de URLs del Joomla actual.
8. Inventario de contenido a migrar: cuántas noticias, cuántos documentos.
9. Hosting, dominio, certificado: dónde está y quién lo administra.
10. Cuántas personas administrarán el sitio y con qué perfil.

---

## Fase 0 — Fundación
**~2 semanas**

Esqueleto del proyecto y todo lo que el resto va a usar.

- Estructura de directorios según `CLAUDE.md`
- Composer: componentes Symfony, Doctrine DBAL y Migrations
- Configuración por entorno, sin credenciales en el repositorio
- Conexión a base de datos, sistema de migraciones funcionando
- Autenticación: login, logout, recuperación de contraseña, Argon2id, bloqueo por intentos
- Roles y permisos granulares
- Auditoría: servicio central, tabla append-only
- Gestión de archivos: subida validada por contenido real, almacenamiento fuera del document root, servicio de entrega con control de permisos
- Configuración dinámica desde base de datos
- Despachador de eventos simple y síncrono
- Cascarón del panel: navegación, sesión, layout responsivo
- Sistema de diseño: tokens de color y tipografía, componentes base, modo oscuro y modos de accesibilidad
- Pipeline de Tailwind, PHPUnit configurado, CI básico

**Criterios de aceptación**
- Un usuario inicia sesión, ve el panel vacío y cierra sesión
- Toda acción queda en `auditoria`
- Migraciones suben y bajan sin error
- Sistema de diseño documentado con muestra de componentes
- Contraste verificado en la paleta institucional

---

## Fase 1 — Contenido y sitio público base
**~2,5 semanas**

- Categorías, páginas, noticias, campañas, avisos, FAQ
- Versionado con snapshot JSON y restauración
- Flujo de aprobación: borrador → en revisión → aprobado → publicado
- Programación de publicación y despublicación (tarea programada)
- Editor CKEditor 5 con sanitización por lista blanca
- Biblioteca de medios con texto alternativo obligatorio
- Menús y navegación editables
- Plantillas públicas: layout, home, listado y detalle de noticias, página institucional
- Encabezado y pie de página según Resolución 1519
- Aviso activo y modo emergencia

**Criterios de aceptación**
- Un funcionario publica una noticia con imagen en menos de dos minutos, sin manual
- Una noticia programada se publica sola y se despublica sola
- Restaurar una versión anterior funciona y queda auditado
- La home carga en menos de 2,5s en 3G simulado
- Navegación completa por teclado en el sitio público

---

## Fase 2 — Directorio y servicios
**~1,5 semanas**

- Sedes con horarios, coordenadas y bandera de urgencias
- Especialidades, servicios, profesionales
- Relaciones servicio–sede, especialidad–sede, profesional–especialidad
- Páginas públicas de cada uno, con filtros
- Página de urgencias y página de contacto

**Criterios de aceptación**
- Encontrar el teléfono de urgencias desde la home en un toque
- Filtrar especialidades por sede funciona sin recargar
- Ningún profesional se publica sin `autoriza_publicacion = 1`

---

## Fase 3 — Gestión documental
**~2 semanas**

- Dependencias, etiquetas
- Documentos con ficha completa: título, resumen, dependencia, categoría, fecha, número de acto, vigencia, serie y subserie documental
- Versiones de documento con historial
- Relación `reemplaza_a_id` para normas derogadas
- Buscador documental con filtros por dependencia, categoría, año y estado
- Ficha pública en HTML con descarga del PDF
- Contador de descargas

**Criterios de aceptación**
- Un documento sin resumen, dependencia o fecha no se puede publicar
- Un documento marcado como derogado deja de aparecer en resultados por defecto
- El buscador responde en menos de 300ms sobre mil documentos
- La ficha es legible en móvil sin abrir el PDF

---

## Fase 4 — Transparencia y datos abiertos
**~1,5 semanas**

- Semilla de la estructura del Anexo 2 de la Resolución 1519
- Vinculación de documentos y enlaces externos a cada sección
- Vista pública del menú de transparencia
- Datasets con recursos descargables en CSV y JSON
- Panel de administración de transparencia con indicador de secciones vacías

**Criterios de aceptación**
- Toda sección obligatoria existe, aunque esté vacía
- El administrador ve de un vistazo qué secciones no tienen contenido
- Los datasets se descargan en formato abierto, no en PDF
- Enlace a SECOP presente en la sección de contratación

---

## Fase 5 — Motor de trámites
**~3 semanas**

La fase más delicada. No empezar sin los términos legales validados.

- Tipos de trámite, estados y campos configurables desde el panel
- Formulario público generado dinámicamente
- Generación de radicado con bloqueo transaccional
- Cálculo de vencimiento en días hábiles usando `festivos`
- Adjuntos con validación
- Bandeja de gestión: filtros, asignación, cambio de estado, respuesta, alertas de vencimiento
- Bitácora de seguimiento con visibilidad controlada
- Consulta pública por radicado + número de documento
- Notificaciones por correo en cada cambio de estado
- Semilla: PQRSDF, solicitud de cita, historia clínica, certificados, habeas data

**Criterios de aceptación**
- Crear un tipo de trámite nuevo desde el panel, sin tocar código
- El radicado es único bajo concurrencia (probar con carga simultánea)
- El vencimiento salta correctamente sobre fines de semana y festivos
- Un trámite próximo a vencer aparece destacado en el panel y notifica al responsable
- La consulta pública no expone datos de otros ciudadanos ni notas internas
- Un trámite anónimo funciona sin pedir identificación
- Ningún formulario se envía sin la casilla de autorización marcada

---

## Fase 6 — Cumplimiento y datos personales
**~1 semana**

- Políticas versionadas con vigencia
- Registro de evidencia de autorización: fecha, IP, versión de política aceptada
- Banner de cookies con consentimiento granular que **bloquea** los scripts hasta la aceptación
- Páginas de política de datos, aviso de privacidad, cookies, accesibilidad
- Trámite de habeas data operativo

**Criterios de aceptación**
- Sin aceptar cookies analíticas, ningún script de terceros se carga (verificar en la pestaña de red)
- Cada trámite tiene su registro en `autorizaciones_datos` con la versión correcta de la política
- Revocar una autorización queda registrado y notifica al responsable

---

## Fase 7 — Búsqueda, SEO, PWA y accesibilidad
**~2 semanas**

- Índice unificado con reconstrucción por evento
- Buscador global con resultados agrupados por tipo
- Registro de búsquedas sin resultado
- SEO: sitemap XML, robots, canonical, OpenGraph, datos estructurados Schema.org (`MedicalOrganization`, `Hospital`), breadcrumbs
- PWA: manifiesto, service worker, cascarón offline, instalable
- Modos de accesibilidad: alto contraste, texto grande, reducción de movimiento
- Auditoría completa WCAG 2.1 AA

**Criterios de aceptación**
- Buscar "certificado laboral" devuelve el trámite, no solo documentos que contengan la frase
- Lighthouse: rendimiento ≥ 90, accesibilidad = 100, SEO ≥ 95 en móvil
- Auditoría manual con lector de pantalla en las cinco rutas principales
- El sitio se instala como aplicación en Android

---

## Fase 8 — IA en el panel
**~1 semana. Opcional.**

- Interfaz `Ports\AiProvider` con proveedor y modelo configurables
- Adaptadores para al menos dos proveedores
- Funciones: resumen de documento, sugerencia de etiquetas, texto alternativo para imágenes, metadatos SEO, borrador de noticia
- Todo resultado se presenta como sugerencia editable
- Registro de uso y consumo

**Criterios de aceptación**
- Con la IA desactivada, todo el panel funciona igual
- Cambiar de proveedor no requiere tocar código de los módulos
- Ninguna sugerencia se aplica automáticamente

---

## Fase 9 — Migración y lanzamiento
**~2 semanas**

- Inventario y migración de contenido desde Joomla
- Carga de documentos con metadatos (el cuello de botella real: alguien del hospital debe clasificarlos)
- Redirecciones 301 desde todas las URLs anteriores
- Pruebas de carga y de seguridad
- Cabeceras de seguridad: HSTS, CSP, `X-Content-Type-Options`, `Referrer-Policy`
- Respaldos programados y prueba de restauración
- Capacitación de los administradores
- Manual de usuario del panel
- Plan de reversión

**Criterios de aceptación**
- Cero enlaces rotos desde las URLs antiguas (verificar con rastreador)
- Restauración de respaldo probada en entorno limpio
- Los administradores publican contenido real sin asistencia
- Encabezados de seguridad verificados con herramienta externa

---

## Resumen

| Fase | Semanas | Acumulado |
|---|---|---|
| 0 · Fundación | 2,0 | 2,0 |
| 1 · Contenido | 2,5 | 4,5 |
| 2 · Directorio | 1,5 | 6,0 |
| 3 · Documental | 2,0 | 8,0 |
| 4 · Transparencia | 1,5 | 9,5 |
| 5 · Trámites | 3,0 | 12,5 |
| 6 · Cumplimiento | 1,0 | 13,5 |
| 7 · Búsqueda y calidad | 2,0 | 15,5 |
| 8 · IA (opcional) | 1,0 | 16,5 |
| 9 · Migración y lanzamiento | 2,0 | 18,5 |

**Aproximadamente cuatro meses y medio.** Sin la Fase 8, cuatro meses.

Si hay que recortar, se recorta la Fase 8, nunca la 6 ni la 7. La 6 es obligación legal y la 7 es lo que hace que el sitio se sienta distinto al Joomla actual.

---

## Después de v1

En este orden, y solo cuando se cumplan las condiciones:

1. **WhatsApp con asistente** — requiere Cloud API oficial de Meta, protocolo de escalamiento definido, y bloqueo determinístico de consultas clínicas. Nunca con Baileys en producción.
2. **Tablero de cumplimiento normativo** — el modelo de datos ya lo soporta; es una vista, no una migración.
3. **Cuenta de usuario ciudadano** — cuando haya cuatro o más trámites y volumen que lo justifique.
4. **Integración con el HIS para citas** — cuando exista API y contrato que la cubra.
5. **Encuestas de satisfacción automáticas.**

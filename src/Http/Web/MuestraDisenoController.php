<?php

declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Config\ConfigAplicacion;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Muestra de componentes del sistema de diseño (Fase 0, paso 9).
 *
 * Herramienta de trabajo, no una página del sitio: en producción responde
 * 404. Sirve para verificar de un vistazo tokens, contraste, foco, modo
 * oscuro y modos de accesibilidad antes de construir pantallas reales.
 */
final readonly class MuestraDisenoController
{
    public function __construct(private ConfigAplicacion $config)
    {
    }

    public function index(Request $peticion): Response
    {
        if ($this->config->entorno->esProduccion()) {
            return new Response('No encontrado', Response::HTTP_NOT_FOUND, [
                'Content-Type' => 'text/plain; charset=utf-8',
            ]);
        }

        $html = <<<'HTML'
            <!doctype html>
            <html lang="es">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="noindex">
                <title>Sistema de diseño — E.S.E. Hospital Rosario Pumarejo de López</title>
                <link rel="stylesheet" href="/build/app.css">
            </head>
            <body>
                <a class="salto-contenido" href="#contenido">Saltar al contenido</a>

                <header class="border-b borde-suave fondo-superficie">
                    <div class="mx-auto max-w-5xl px-4 py-3 flex flex-wrap items-center gap-3">
                        <strong class="texto-titular">Sistema de diseño</strong>
                        <span class="text-sm texto-suave">Fase 0 · paso 9 · paleta provisional derivada de frontend.png</span>
                        <span class="ms-auto flex gap-2">
                            <button type="button" class="boton boton-secundario" data-alternar="tema">Tema oscuro</button>
                            <button type="button" class="boton boton-secundario" data-alternar="contraste">Alto contraste</button>
                            <button type="button" class="boton boton-secundario" data-alternar="fuente">A+</button>
                        </span>
                    </div>
                </header>

                <main id="contenido" class="mx-auto max-w-5xl px-4 py-8 flex flex-col gap-10">

                    <section aria-labelledby="t-tipografia">
                        <h2 id="t-tipografia">Tipografía</h2>
                        <div class="mt-4 flex flex-col gap-2">
                            <h1>Tu salud, nuestra prioridad</h1>
                            <h2>Título de sección (h2)</h2>
                            <h3>Título de bloque (h3)</h3>
                            <p>Párrafo base. En la E.S.E. Hospital Rosario Pumarejo de López brindamos
                               atención médica de calidad, humana y segura. La tipografía escala con
                               <code>clamp()</code> y crece con los modos A+ / A++.</p>
                            <p class="text-sm texto-suave">Texto secundario, para fechas y metadatos.</p>
                            <p><a href="#contenido">Así se ve un enlace en un párrafo</a>.</p>
                        </div>
                    </section>

                    <section aria-labelledby="t-colores">
                        <h2 id="t-colores">Paleta</h2>
                        <p class="mt-2 text-sm texto-suave">
                            El verde de marca es decorativo: nunca lleva texto sobre fondo claro.
                        </p>
                        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
                            <div class="tarjeta p-0 overflow-hidden">
                                <div class="h-16 bg-primario-500"></div>
                                <p class="p-2 text-sm">primario-500</p>
                            </div>
                            <div class="tarjeta p-0 overflow-hidden">
                                <div class="h-16 bg-primario-700"></div>
                                <p class="p-2 text-sm">primario-700</p>
                            </div>
                            <div class="tarjeta p-0 overflow-hidden">
                                <div class="h-16 bg-tinta"></div>
                                <p class="p-2 text-sm">tinta</p>
                            </div>
                            <div class="tarjeta p-0 overflow-hidden">
                                <div class="h-16 bg-marca-verde"></div>
                                <p class="p-2 text-sm">marca-verde</p>
                            </div>
                            <div class="tarjeta p-0 overflow-hidden">
                                <div class="h-16 bg-gris-50 border-b borde-suave"></div>
                                <p class="p-2 text-sm">gris-50</p>
                            </div>
                        </div>
                    </section>

                    <section aria-labelledby="t-botones">
                        <h2 id="t-botones">Botones</h2>
                        <p class="mt-2 text-sm texto-suave">
                            Área táctil mínima de 44 px. Recorre con Tab para ver el anillo de foco.
                        </p>
                        <div class="mt-4 flex flex-wrap gap-3">
                            <button type="button" class="boton boton-primario">Solicitar cita</button>
                            <button type="button" class="boton boton-secundario">Conoce nuestros servicios</button>
                            <button type="button" class="boton boton-peligro">Eliminar borrador</button>
                        </div>
                    </section>

                    <section aria-labelledby="t-formularios">
                        <h2 id="t-formularios">Formularios</h2>
                        <form class="mt-4 max-w-md flex flex-col gap-4" action="/diseno" method="get">
                            <div>
                                <label class="etiqueta" for="m-nombre">Nombre completo</label>
                                <input class="campo" type="text" id="m-nombre" autocomplete="name">
                                <p class="ayuda-campo">Como aparece en tu documento de identidad.</p>
                            </div>
                            <div>
                                <label class="etiqueta" for="m-correo">Correo electrónico</label>
                                <input class="campo" type="email" id="m-correo" aria-describedby="m-correo-error" aria-invalid="true">
                                <p class="error-campo" id="m-correo-error">Escribe un correo válido, por ejemplo nombre@dominio.com.</p>
                            </div>
                            <div>
                                <button type="submit" class="boton boton-primario">Enviar solicitud</button>
                            </div>
                        </form>
                    </section>

                    <section aria-labelledby="t-alertas">
                        <h2 id="t-alertas">Alertas e insignias</h2>
                        <div class="mt-4 flex flex-col gap-3">
                            <div class="alerta alerta-exito" role="status">Tu solicitud quedó radicada con el número HRPL-2026-000123.</div>
                            <div class="alerta alerta-aviso" role="status">Jornada de vacunación: este sábado la sede La Nevada abre hasta mediodía.</div>
                            <div class="alerta alerta-error" role="alert">No pudimos guardar los cambios. Revisa los campos marcados.</div>
                            <p class="flex flex-wrap gap-2">
                                <span class="insignia insignia-exito">Urgencias 24/7</span>
                                <span class="insignia insignia-aviso">En revisión</span>
                                <span class="insignia insignia-error">Vencido</span>
                            </p>
                        </div>
                    </section>

                    <section aria-labelledby="t-tarjetas">
                        <h2 id="t-tarjetas">Tarjetas</h2>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <article class="tarjeta">
                                <h3>Sede Principal</h3>
                                <p class="mt-1 text-sm texto-suave">Cra. 24 #16-85, Valledupar</p>
                                <p class="mt-3"><span class="insignia insignia-exito">Urgencias 24/7</span></p>
                            </article>
                            <article class="tarjeta fondo-superficie">
                                <h3>Tarjeta sobre superficie</h3>
                                <p class="mt-1">Para franjas y fondos alternos se usa <code>--superficie</code>.</p>
                            </article>
                        </div>
                    </section>
                </main>

                <script src="/js/muestra.js"></script>
            </body>
            </html>
            HTML;

        $respuesta = new Response($html, Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }
}

// Menús desplegables de la barra de navegación (sitio público y panel).
//
// Los desplegables son <details name="..."> y no un widget de JavaScript: se
// abren con el teclado, funcionan sin scripts y el navegador ya sabe cerrar
// los hermanos que comparten `name` (el «acordeón exclusivo» de HTML). Este
// archivo solo añade lo que falta para que se comporten como un menú y no
// como un acordeón:
//
//   1. Cerrarlos al pulsar fuera.
//   2. Cerrarlos con Escape, devolviendo el foco a su cabecera.
//   3. Respaldo de exclusividad para los navegadores que todavía ignoran
//      `name` en <details> (anteriores a Chrome 120 / Safari 17.2 / Firefox
//      130). Sin esto, ahí siguen quedando dos menús abiertos a la vez.
//
// Solo actúa sobre los <details> que llevan `name`: en el panel hay muchos
// otros (filtros, detalles de fila) que SÍ deben poder estar abiertos a la vez.
(function () {
    'use strict';

    var SELECTOR = 'nav details[name]';

    // En un navegador con soporte, `name` se refleja como propiedad del
    // elemento; donde no, la propiedad no existe y hay que cerrar a mano.
    var SOPORTA_EXCLUSIVIDAD = 'name' in document.createElement('details');

    function menus() {
        return Array.prototype.slice.call(document.querySelectorAll(SELECTOR));
    }

    function menuDe(objetivo) {
        return objetivo instanceof Element ? objetivo.closest(SELECTOR) : null;
    }

    function cerrarTodos(excepto) {
        menus().forEach(function (details) {
            if (details !== excepto && details.open) {
                details.open = false;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var desplegables = menus();

        if (desplegables.length === 0) {
            return;
        }

        if (!SOPORTA_EXCLUSIVIDAD) {
            desplegables.forEach(function (details) {
                details.addEventListener('toggle', function () {
                    if (details.open) {
                        cerrarTodos(details);
                    }
                });
            });
        }

        // Pulsar fuera cierra. Se escucha en captura para que el menú se haya
        // cerrado antes de que el clic llegue a su destino: esperando a la
        // fase de burbujeo, al pulsar un enlace de la barra el menú se quedaba
        // abierto durante toda la navegación.
        document.addEventListener('click', function (evento) {
            cerrarTodos(menuDe(evento.target));
        }, true);

        // El foco puede salir del menú con el tabulador, sin ningún clic.
        document.addEventListener('focusin', function (evento) {
            if (menuDe(evento.target) === null) {
                cerrarTodos(null);
            }
        });

        document.addEventListener('keydown', function (evento) {
            if (evento.key !== 'Escape') {
                return;
            }

            var activo = menuDe(document.activeElement);

            cerrarTodos(null);

            // Escape devuelve el foco a la cabecera del menú que se cerró; si
            // no, el foco se queda dentro de un submenú ya invisible y el
            // siguiente tabulador salta a un sitio impredecible.
            if (activo !== null) {
                var cabecera = activo.querySelector('summary');

                if (cabecera !== null) {
                    cabecera.focus();
                }
            }
        });
    });
})();

// Carrusel del héroe de la portada.
//
// Sin Alpine a propósito: aquí no hay estado que pintar, solo un atributo que
// cambia. `comunicaciones.js` usa Alpine porque tiene pestañas, paneles y
// contadores enlazados; esto es mover `data-activa` de una diapositiva a otra,
// y cargar un framework para eso sería pagarlo en la portada, que es la página
// que más gente abre y la que mide Lighthouse.
//
// Qué NO hace: traer contenido. El servidor entrega todas las diapositivas
// renderizadas y marca cuál va activa. Sin JavaScript se ven todas, una debajo
// de otra: contenido legible e indexable, no una caja vacía. Por eso el
// `display: none` de `app.css` cuelga de `[data-js]`, que `sitio.js` pone en
// `<html>` antes del primer pintado.
(function () {
    'use strict';

    var RELEVO_POR_OMISION_MS = 8000;

    function arrancar(seccion) {
        var diapositivas = Array.prototype.slice.call(seccion.querySelectorAll('.heroe-diapositiva'));

        if (diapositivas.length < 2) {
            return;
        }

        var puntos = Array.prototype.slice.call(seccion.querySelectorAll('.heroe-punto'));
        var anterior = seccion.querySelector('.heroe-anterior');
        var siguiente = seccion.querySelector('.heroe-siguiente');

        var relevoMs = parseInt(seccion.getAttribute('data-rotacion-ms'), 10);

        if (isNaN(relevoMs)) {
            relevoMs = RELEVO_POR_OMISION_MS;
        }

        var actual = 0;
        var temporizador = null;

        function mostrar(indice) {
            actual = (indice + diapositivas.length) % diapositivas.length;

            diapositivas.forEach(function (diapositiva, i) {
                diapositiva.setAttribute('data-activa', i === actual ? 'true' : 'false');
            });

            puntos.forEach(function (punto, i) {
                // El punto de la diapositiva que se ve va relleno; los demás,
                // translúcidos. `aria-current` es lo que lo dice en voz alta:
                // el color por sí solo no se lo cuenta a nadie que no vea.
                punto.classList.toggle('bg-white', i === actual);
                punto.classList.toggle('bg-white/30', i !== actual);

                if (i === actual) {
                    punto.setAttribute('aria-current', 'true');
                } else {
                    punto.removeAttribute('aria-current');
                }
            });
        }

        /**
         * Rotación automática. Se detiene mientras el puntero está encima o el
         * foco del teclado está dentro: cambiarle a alguien el enlace justo
         * antes de que lo pulse es de las cosas más molestas que puede hacer
         * una portada.
         *
         * Cero segundos en Ajustes apaga la rotación, y también la apaga quien
         * pidió menos movimiento en el sistema o en la barra de accesibilidad.
         */
        function arrancarRelevo() {
            pararRelevo();

            if (relevoMs <= 0 || menosMovimiento()) {
                return;
            }

            temporizador = window.setInterval(function () {
                if (seccion.matches(':hover') || seccion.contains(document.activeElement)) {
                    return;
                }

                mostrar(actual + 1);
            }, Math.max(1000, relevoMs));
        }

        function pararRelevo() {
            if (temporizador !== null) {
                window.clearInterval(temporizador);
                temporizador = null;
            }
        }

        function menosMovimiento() {
            return document.documentElement.getAttribute('data-movimiento') === 'reducido'
                || window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        }

        if (anterior !== null) {
            anterior.addEventListener('click', function () {
                mostrar(actual - 1);
                arrancarRelevo();
            });
        }

        if (siguiente !== null) {
            siguiente.addEventListener('click', function () {
                mostrar(actual + 1);
                arrancarRelevo();
            });
        }

        puntos.forEach(function (punto) {
            punto.addEventListener('click', function () {
                mostrar(parseInt(punto.getAttribute('data-indice'), 10) || 0);
                arrancarRelevo();
            });
        });

        // El mismo aviso que escucha el carrusel de campañas, para respetar el
        // cambio de preferencia en caliente y sin recargar.
        window.addEventListener('cambio-de-movimiento', arrancarRelevo);

        mostrar(0);
        arrancarRelevo();
    }

    function iniciar() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-heroes]'), arrancar);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();

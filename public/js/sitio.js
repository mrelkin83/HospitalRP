// Barra de accesibilidad del sitio público: alto contraste, tamaño de letra
// y tema, persistidos en localStorage. Se carga en <head> sin defer a
// propósito: aplicar las preferencias antes del primer pintado evita el
// parpadeo. Pesa ~1 KB; el presupuesto de JS no lo nota.
(function () {
    'use strict';

    var raiz = document.documentElement;
    var CLAVE = 'hrpl_accesibilidad';
    var ESCALA = ['', 'mayor', 'maxima'];

    function leer() {
        try {
            return JSON.parse(localStorage.getItem(CLAVE) || '{}') || {};
        } catch (e) {
            return {};
        }
    }

    function guardar(prefs) {
        try {
            localStorage.setItem(CLAVE, JSON.stringify(prefs));
        } catch (e) { /* modo privado: la preferencia vive solo esta visita */ }
    }

    function aplicar(prefs) {
        if (prefs.tema) { raiz.dataset.tema = prefs.tema; } else { delete raiz.dataset.tema; }
        if (prefs.contraste) { raiz.dataset.contraste = 'alto'; } else { delete raiz.dataset.contraste; }
        if (prefs.fuente) { raiz.dataset.fuente = prefs.fuente; } else { delete raiz.dataset.fuente; }
        if (prefs.movimiento) { raiz.dataset.movimiento = 'reducido'; } else { delete raiz.dataset.movimiento; }
    }

    // Marca de «hay JavaScript», puesta antes del primer pintado. El CSS la
    // usa para esconder lo que solo tiene sentido escondido si algo puede
    // volver a mostrarlo (las pestañas y el carrusel de la portada). Sin JS
    // no se esconde nada y el contenido queda completo.
    raiz.dataset.js = '1';

    var prefs = leer();
    aplicar(prefs);

    var acciones = {
        tema: function () {
            prefs.tema = prefs.tema === 'oscuro' ? '' : 'oscuro';
            if (!prefs.tema) { delete prefs.tema; }
        },
        contraste: function () {
            prefs.contraste = !prefs.contraste;
            if (!prefs.contraste) { delete prefs.contraste; }
        },
        'fuente-mas': function () {
            var i = ESCALA.indexOf(prefs.fuente || '');
            prefs.fuente = ESCALA[Math.min(i + 1, ESCALA.length - 1)];
            if (!prefs.fuente) { delete prefs.fuente; }
        },
        'fuente-menos': function () {
            var i = ESCALA.indexOf(prefs.fuente || '');
            prefs.fuente = ESCALA[Math.max(i - 1, 0)];
            if (!prefs.fuente) { delete prefs.fuente; }
        },
        movimiento: function () {
            prefs.movimiento = !prefs.movimiento;
            if (!prefs.movimiento) { delete prefs.movimiento; }
            // Los carruseles que rotan solos escuchan esto para parar (o
            // reanudar) en el acto, sin obligar a recargar la página.
            window.dispatchEvent(new CustomEvent('cambio-de-movimiento'));
        },
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-alternar]').forEach(function (boton) {
            boton.addEventListener('click', function () {
                var accion = acciones[boton.dataset.alternar];

                if (accion) {
                    accion();
                    aplicar(prefs);
                    guardar(prefs);
                }
            });
        });
    });

    // PWA: el service worker da cascarón offline e instalación en Android.
    // Solo bajo HTTPS (o localhost); si falla, el sitio funciona igual.
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js').catch(function () { /* sin PWA */ });
        });
    }
})();

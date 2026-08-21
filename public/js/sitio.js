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
    }

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
})();

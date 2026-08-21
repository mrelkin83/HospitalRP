// Alternadores de la página de muestra del sistema de diseño.
// Solo para /diseno: la barra de accesibilidad real del sitio llega en la
// Fase 1 con persistencia en localStorage.
(function () {
    'use strict';

    var raiz = document.documentElement;

    var acciones = {
        tema: function () {
            raiz.dataset.tema = raiz.dataset.tema === 'oscuro' ? 'claro' : 'oscuro';
        },
        contraste: function () {
            if (raiz.dataset.contraste === 'alto') {
                delete raiz.dataset.contraste;
            } else {
                raiz.dataset.contraste = 'alto';
            }
        },
        fuente: function () {
            var escala = ['', 'mayor', 'maxima'];
            var actual = escala.indexOf(raiz.dataset.fuente || '');
            var siguiente = escala[(actual + 1) % escala.length];

            if (siguiente === '') {
                delete raiz.dataset.fuente;
            } else {
                raiz.dataset.fuente = siguiente;
            }
        },
    };

    document.querySelectorAll('[data-alternar]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            var accion = acciones[boton.dataset.alternar];

            if (accion) {
                accion();
            }
        });
    });
})();

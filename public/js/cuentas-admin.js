/*
 * Panel de cuentas de cobro: filas dinámicas de cuotas en el formulario
 * de contrato y en la revisión de minutas. En archivo aparte porque la
 * CSP del sitio (script-src 'self') bloquea todo script en línea.
 *
 * Uso: <button type="button" data-agregar-fila="#id-de-la-tabla">.
 */
(function () {
    'use strict';

    document.querySelectorAll('[data-agregar-fila]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            var tabla = document.querySelector(boton.getAttribute('data-agregar-fila'));

            if (!tabla) { return; }

            var cuerpo = tabla.querySelector('tbody');
            var fila = cuerpo.lastElementChild.cloneNode(true);
            fila.querySelectorAll('input').forEach(function (campo) { campo.value = ''; });
            fila.querySelectorAll('select').forEach(function (campo) { campo.selectedIndex = 0; });
            cuerpo.appendChild(fila);
        });
    });
})();

/**
 * El campo de identificación se nombra según el selector.
 *
 * «Cédula o número de contrato» obliga al contratista a decidir qué le
 * están pidiendo cuando ya lo había decidido arriba, en el desplegable.
 * Aquí el rótulo, la ayuda, el ejemplo y el teclado del celular cambian
 * con esa elección: si eligió cédula, se le pide la cédula y el teléfono
 * abre el teclado numérico.
 *
 * El servidor ya pinta el texto correcto para el modo con el que carga la
 * página, así que sin JavaScript la pantalla sigue siendo coherente: esto
 * solo evita tener que recargar para verlo cambiar.
 */
(function () {
    'use strict';

    document.querySelectorAll('select[data-campo-identificador]').forEach(function (selector) {
        var campo = document.getElementById(selector.dataset.campoIdentificador);

        if (campo === null) {
            return;
        }

        var etiqueta = document.querySelector('label[for="' + campo.id + '"]');
        var ayuda = campo.getAttribute('aria-describedby')
            ? document.getElementById(campo.getAttribute('aria-describedby'))
            : null;

        function pintar() {
            var contrato = selector.value === 'contrato';
            var sufijo = contrato ? 'Contrato' : 'Cedula';

            if (etiqueta !== null) {
                etiqueta.textContent = selector.dataset['etiqueta' + sufijo];
            }

            if (ayuda !== null) {
                ayuda.textContent = selector.dataset['ayuda' + sufijo];
            }

            campo.setAttribute('placeholder', selector.dataset['ejemplo' + sufijo]);

            // La cédula es solo dígitos: en el celular conviene el teclado
            // numérico. El número de contrato lleva guion, así que no.
            campo.setAttribute('inputmode', contrato ? 'text' : 'numeric');
        }

        selector.addEventListener('change', pintar);
        pintar();
    });
})();

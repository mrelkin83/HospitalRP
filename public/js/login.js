// Mostrar/ocultar la contraseña en el ingreso al panel.
//
// El botón vive en el HTML (y no lo crea este archivo) para que sin JavaScript
// no quede un control muerto: la plantilla lo marca `hidden` y aquí se
// destapa. Así el formulario sigue funcionando con JS deshabilitado, solo que
// sin el ojo.
//
// La CSP del panel es `script-src 'self'`: nada de `onclick` en la plantilla.
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-ver-clave]').forEach(function (boton) {
            var campo = document.getElementById(boton.dataset.verClave);

            if (!campo) {
                return;
            }

            boton.hidden = false;

            boton.addEventListener('click', function () {
                var oculta = campo.type === 'password';
                campo.type = oculta ? 'text' : 'password';

                boton.setAttribute('aria-pressed', oculta ? 'true' : 'false');
                boton.setAttribute(
                    'aria-label',
                    oculta ? 'Ocultar la contraseña' : 'Mostrar la contraseña',
                );

                // Los dos iconos están en el HTML; solo se alterna cuál se ve.
                var abierto = boton.querySelector('[data-icono="ojo-abierto"]');
                var tachado = boton.querySelector('[data-icono="ojo-tachado"]');

                if (abierto && tachado) {
                    abierto.hidden = oculta;
                    tachado.hidden = !oculta;
                }

                // Devolver el foco al campo deja el cursor donde estaba.
                campo.focus();
            });
        });
    });
})();

// Consentimiento de cookies (Fase 6, Ley 1581 / Res. 1519).
//
// REGLA PARA TODO SCRIPT FUTURO DE TERCEROS (analítica, mapas, videos):
// no se carga directamente en el HTML. Se registra así:
//
//   hrplConsentimiento.cuandoPermita('analiticas', function () {
//       var s = document.createElement('script'); s.src = '...'; ...
//   });
//
// Sin consentimiento expreso, el callback jamás corre y no sale ni una
// petición a terceros. Las cookies esenciales (sesión del panel, este
// consentimiento y las preferencias de accesibilidad) no requieren opt-in.
(function () {
    'use strict';

    var NOMBRE = 'hrpl_consentimiento';
    var DIAS = 180;
    var pendientes = { analiticas: [] };

    function leer() {
        var partes = ('; ' + document.cookie).split('; ' + NOMBRE + '=');

        if (partes.length !== 2) {
            return null;
        }

        try {
            return JSON.parse(decodeURIComponent(partes.pop().split(';').shift()));
        } catch (e) {
            return null;
        }
    }

    function guardar(consentimiento) {
        var caduca = new Date(Date.now() + DIAS * 864e5).toUTCString();
        document.cookie = NOMBRE + '=' + encodeURIComponent(JSON.stringify(consentimiento))
            + '; expires=' + caduca + '; path=/; SameSite=Lax'
            + (location.protocol === 'https:' ? '; Secure' : '');
    }

    function ejecutarPendientes(categoria) {
        pendientes[categoria].splice(0).forEach(function (callback) {
            try { callback(); } catch (e) { /* un tercero roto no tumba el sitio */ }
        });
    }

    window.hrplConsentimiento = {
        permite: function (categoria) {
            var c = leer();

            return categoria === 'esenciales' || (c !== null && c[categoria] === true);
        },
        cuandoPermita: function (categoria, callback) {
            if (this.permite(categoria)) {
                callback();
            } else {
                (pendientes[categoria] = pendientes[categoria] || []).push(callback);
            }
        },
    };

    document.addEventListener('DOMContentLoaded', function () {
        var banner = document.querySelector('[data-banner-cookies]');

        if (!banner) {
            return;
        }

        if (leer() === null) {
            banner.hidden = false;
        }

        banner.addEventListener('click', function (evento) {
            var decision = evento.target instanceof Element ? evento.target.getAttribute('data-cookies') : null;

            if (decision === null) {
                return;
            }

            var casilla = banner.querySelector('input[name="analiticas"]');
            var consentimiento = {
                v: 1,
                esenciales: true,
                analiticas: decision === 'todas' || (decision === 'seleccion' && casilla !== null && casilla.checked),
            };

            guardar(consentimiento);
            banner.hidden = true;

            if (consentimiento.analiticas) {
                ejecutarPendientes('analiticas');
            }
        });
    });
})();

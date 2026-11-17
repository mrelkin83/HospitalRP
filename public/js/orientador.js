/**
 * Orientador del portal: enrutador guiado, no un chatbot.
 *
 * El servidor ya escribió las opciones en el HTML —salen del menú «accesos»
 * que administra el hospital—. Esto solo las mueve: al pulsar una, escribe la
 * pregunta, la respuesta y el enlace, y deja el resto a la vista.
 *
 * No hay texto libre, no hay modelo de lenguaje y no se guarda nada. Las
 * burbujas se construyen con createTextNode, nunca con innerHTML, salvo el
 * detalle administrable, que el servidor ya escapó.
 *
 * El botón y el panel vienen con `hidden` desde el servidor: sin JavaScript
 * no se pintan, porque serían un botón que no abre nada. Las mismas rutas ya
 * están en la portada y en el pie, así que no se pierde ninguna.
 */
(function () {
    'use strict';

    var panel = document.getElementById('orientador');
    var abrir = document.getElementById('orientador-abrir');
    var hilo = document.getElementById('orientador-hilo');
    var opciones = document.getElementById('orientador-opciones');

    if (!panel || !abrir || !hilo || !opciones) {
        return;
    }

    panel.hidden = false;
    abrir.hidden = false;

    /** Burbuja de texto plano: lo que se refleja del visitante nunca es HTML. */
    function burbuja(clase, texto) {
        var p = document.createElement('p');
        p.className = 'burbuja ' + clase;
        p.appendChild(document.createTextNode(texto));
        hilo.appendChild(p);

        return p;
    }

    function responder(boton) {
        var etiqueta = (boton.textContent || '').trim();
        var url = boton.getAttribute('data-url') || '';
        var detalle = boton.getAttribute('data-detalle') || '';

        burbuja('visitante', etiqueta);

        var respuesta = burbuja('asistente', detalle !== '' ? detalle : 'Te llevo a «' + etiqueta + '».');

        if (url !== '') {
            var ir = document.createElement('a');
            ir.className = 'orientador-ir';
            ir.href = url;
            ir.appendChild(document.createTextNode('Ir a ' + etiqueta + ' →'));
            respuesta.appendChild(document.createElement('br'));
            respuesta.appendChild(ir);
        }

        // La opción usada se retira: deja a la vista lo que todavía no probó.
        boton.hidden = true;

        hilo.scrollTop = hilo.scrollHeight;
    }

    opciones.addEventListener('click', function (ev) {
        var boton = ev.target.closest('[data-orientador-opcion]');

        if (boton) {
            responder(boton);
        }
    });

    function alternar(abierto) {
        panel.setAttribute('data-abierto', abierto ? 'si' : 'no');
        abrir.setAttribute('aria-expanded', abierto ? 'true' : 'false');

        if (abierto) {
            var primera = opciones.querySelector('[data-orientador-opcion]:not([hidden])');

            if (primera) {
                primera.focus();
            }

            return;
        }

        abrir.focus();
    }

    abrir.addEventListener('click', function () {
        alternar(panel.getAttribute('data-abierto') !== 'si');
    });

    var cerrar = panel.querySelector('[data-orientador-cerrar]');

    if (cerrar) {
        cerrar.addEventListener('click', function () {
            alternar(false);
        });
    }

    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && panel.getAttribute('data-abierto') === 'si') {
            alternar(false);
        }
    });
}());

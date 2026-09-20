/*
 * Portal de cuentas de cobro: autoguardado del paso «Qué hizo este mes» y
 * firma dibujada en «Mis datos». JavaScript vanilla y defensivo: si algo
 * falla, el formulario clásico sigue funcionando solo con el envío normal.
 */
(function () {
    'use strict';

    // ------------------------------------------------------------------
    // Autoguardado: cada 10 segundos y al salir de cada campo (§7.1 P3).
    // ------------------------------------------------------------------
    var formulario = document.getElementById('formulario-actividades');

    if (formulario) {
        var csrf = formulario.getAttribute('data-csrf') || '';
        var pendientes = {};

        var guardar = function (campo) {
            var obligacion = campo.getAttribute('data-obligacion');

            if (!obligacion) { return; }

            var aviso = document.getElementById('guardado-' + obligacion);

            fetch('/cuentas/nueva/autoguardar', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Token-CSRF': csrf },
                body: JSON.stringify({ obligacion_id: parseInt(obligacion, 10), texto: campo.value })
            }).then(function (respuesta) {
                if (aviso) { aviso.textContent = respuesta.ok ? 'Guardado.' : ''; }
            }).catch(function () { /* sin red: el envío normal lo salvará */ });
        };

        formulario.querySelectorAll('textarea.actividad').forEach(function (campo) {
            campo.addEventListener('blur', function () { guardar(campo); });
            campo.addEventListener('input', function () {
                pendientes[campo.getAttribute('data-obligacion')] = campo;
                var aviso = document.getElementById('guardado-' + campo.getAttribute('data-obligacion'));
                if (aviso) { aviso.textContent = ''; }
            });
        });

        window.setInterval(function () {
            Object.keys(pendientes).forEach(function (clave) {
                guardar(pendientes[clave]);
                delete pendientes[clave];
            });
        }, 10000);

        formulario.querySelectorAll('[data-limpiar]').forEach(function (boton) {
            boton.addEventListener('click', function () {
                var campo = document.getElementById(boton.getAttribute('data-limpiar'));
                if (campo) { campo.value = ''; campo.focus(); guardar(campo); }
            });
        });
    }

    // ------------------------------------------------------------------
    // Firma dibujada: canvas → PNG en un campo oculto (§7.1 P1a).
    // ------------------------------------------------------------------
    var lienzo = document.getElementById('firma-lienzo');

    if (lienzo && lienzo.getContext) {
        var contexto = lienzo.getContext('2d');
        var oculto = document.getElementById('firma-dibujada');
        var dibujando = false;
        var hayTrazo = false;

        contexto.lineWidth = 3;
        contexto.lineCap = 'round';
        contexto.strokeStyle = '#1a202e';

        var punto = function (evento) {
            var caja = lienzo.getBoundingClientRect();
            var origen = evento.touches ? evento.touches[0] : evento;

            return {
                x: (origen.clientX - caja.left) * (lienzo.width / caja.width),
                y: (origen.clientY - caja.top) * (lienzo.height / caja.height)
            };
        };

        var empezar = function (evento) {
            dibujando = true;
            var p = punto(evento);
            contexto.beginPath();
            contexto.moveTo(p.x, p.y);
            evento.preventDefault();
        };

        var trazar = function (evento) {
            if (!dibujando) { return; }
            var p = punto(evento);
            contexto.lineTo(p.x, p.y);
            contexto.stroke();
            hayTrazo = true;
            if (oculto) { oculto.value = lienzo.toDataURL('image/png'); }
            evento.preventDefault();
        };

        var terminar = function () { dibujando = false; };

        lienzo.addEventListener('mousedown', empezar);
        lienzo.addEventListener('mousemove', trazar);
        window.addEventListener('mouseup', terminar);
        lienzo.addEventListener('touchstart', empezar, { passive: false });
        lienzo.addEventListener('touchmove', trazar, { passive: false });
        lienzo.addEventListener('touchend', terminar);

        var limpiar = document.getElementById('limpiar-firma');

        if (limpiar) {
            limpiar.addEventListener('click', function () {
                contexto.clearRect(0, 0, lienzo.width, lienzo.height);
                hayTrazo = false;
                if (oculto) { oculto.value = ''; }
            });
        }
    }
})();

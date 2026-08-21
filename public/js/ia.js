// Botones «Sugerir con IA» del panel. Mejora progresiva pura:
//  - Los botones [data-ia] se imprimen en el servidor SOLO si la IA está
//    activa; sin JS, sin IA o con error, el formulario funciona igual.
//  - El resultado nunca se guarda solo: llena el campo y el funcionario
//    decide si lo edita, lo usa o lo borra antes de guardar.
(function () {
    'use strict';

    var meta = document.querySelector('meta[name="csrf"]');
    var token = meta ? meta.content : '';

    function campo(selector) {
        return selector ? document.querySelector(selector) : null;
    }

    function valorDe(selector) {
        var el = campo(selector);

        if (!el) { return ''; }

        // CKEditor 5 expone su instancia en el elemento de origen.
        if (el.ckeditorInstance) {
            var contenedor = document.createElement('div');
            contenedor.innerHTML = el.ckeditorInstance.getData();

            return contenedor.textContent || '';
        }

        return (el.value !== undefined ? el.value : el.textContent) || '';
    }

    function escribir(selector, texto) {
        var el = campo(selector);

        if (!el) { return; }

        if (el.ckeditorInstance) {
            // La sugerencia llega como texto plano: cada párrafo, un <p>.
            var html = texto.split(/\n{2,}/).map(function (parrafo) {
                var p = document.createElement('p');
                p.textContent = parrafo.replace(/\n/g, ' ').trim();

                return p.outerHTML;
            }).join('');

            el.ckeditorInstance.setData(html);

            return;
        }

        el.value = texto;
        // Las validaciones y autoguardados escuchan input/change.
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function leerImagen(selector) {
        return new Promise(function (resolver, rechazar) {
            var input = campo(selector);
            var archivo = input && input.files && input.files[0];

            if (!archivo) {
                rechazar(new Error('Primero selecciona la imagen.'));
                return;
            }

            var lector = new FileReader();
            lector.onload = function () {
                var partes = String(lector.result).split(',');
                resolver({ mime: archivo.type, base64: partes[1] || '' });
            };
            lector.onerror = function () { rechazar(new Error('No se pudo leer la imagen.')); };
            lector.readAsDataURL(archivo);
        });
    }

    function avisar(boton, texto, esError) {
        var aviso = boton.nextElementSibling;

        if (!aviso || !aviso.hasAttribute('data-ia-aviso')) {
            aviso = document.createElement('span');
            aviso.setAttribute('data-ia-aviso', '');
            aviso.setAttribute('role', 'status');
            aviso.className = 'ms-2 text-sm';
            boton.after(aviso);
        }

        aviso.className = 'ms-2 text-sm ' + (esError ? 'error-campo' : 'texto-suave');
        aviso.textContent = texto;
    }

    function sugerir(boton) {
        var cuerpo = {
            _token: token,
            funcion: boton.dataset.ia,
            titulo: valorDe(boton.dataset.iaTitulo),
            texto: valorDe(boton.dataset.iaTexto)
        };

        var conImagen = boton.dataset.ia === 'texto_alternativo'
            ? leerImagen(boton.dataset.iaImagen).then(function (imagen) {
                cuerpo.imagen_mime = imagen.mime;
                cuerpo.imagen_base64 = imagen.base64;
            })
            : Promise.resolve();

        boton.disabled = true;
        avisar(boton, 'Consultando…', false);

        conImagen.then(function () {
            return fetch('/admin/ia/sugerir', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(cuerpo)
            });
        }).then(function (respuesta) {
            return respuesta.json().then(function (datos) {
                if (!respuesta.ok) {
                    throw new Error(datos.error || 'El asistente no respondió.');
                }

                return datos.sugerencia;
            });
        }).then(function (sugerencia) {
            if (boton.dataset.ia === 'metadatos_seo') {
                escribir(boton.dataset.iaDestinoTitulo, sugerencia.titulo || '');
                escribir(boton.dataset.iaDestinoDescripcion, sugerencia.descripcion || '');
            } else {
                escribir(boton.dataset.iaDestino, sugerencia);
            }

            avisar(boton, 'Listo: revisa y edita antes de guardar.', false);
        }).catch(function (error) {
            avisar(boton, error.message, true);
        }).finally(function () {
            boton.disabled = false;
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-ia]').forEach(function (boton) {
            boton.addEventListener('click', function () { sugerir(boton); });
        });
    });
})();

// Ventana emergente del sitio público.
//
// El servidor ya decidió QUÉ popup toca (vigencia, ubicación, prioridad).
// Aquí solo se decide si ESTE visitante tiene que verlo ahora, que es lo único
// que el servidor no puede saber sin identificarlo: si ya lo cerró y cuánto
// hace de eso.
//
// El recuerdo vive en el navegador y no en una cookie: no sale del equipo, no
// viaja en cada petición y no hay que pedir consentimiento para algo que solo
// guarda «ya vi el aviso número 3».
//
//   siempre → no se recuerda nada; reaparece en cada página.
//   sesion  → sessionStorage; vuelve en la siguiente visita.
//   diaria  → localStorage con la fecha; vuelve al día siguiente.
//
// La clave incluye una huella de la última edición, así que reescribir el
// popup desde el panel se lo devuelve incluso a quien ya lo había cerrado.
(function () {
    'use strict';

    // Toda lectura y escritura va envuelta: en navegación privada, con las
    // cookies de sitio bloqueadas o con la cuota llena, el simple acceso a
    // `sessionStorage` LANZA. Sin el try, en esos navegadores el popup no se
    // abriría jamás, y encima en silencio.
    function almacen(frecuencia) {
        try {
            return frecuencia === 'diaria' ? window.localStorage : window.sessionStorage;
        } catch (error) {
            return null;
        }
    }

    function hoy() {
        return new Date().toISOString().slice(0, 10);
    }

    function yaLoCerro(popup) {
        var frecuencia = popup.dataset.frecuencia;

        if (frecuencia === 'siempre') {
            return false;
        }

        var deposito = almacen(frecuencia);

        if (deposito === null) {
            // Sin memoria disponible, se enseña: un popup de más molesta menos
            // que un anuncio que nadie llega a ver.
            return false;
        }

        try {
            var guardado = deposito.getItem(popup.dataset.clave);

            if (guardado === null) {
                return false;
            }

            return frecuencia === 'diaria' ? guardado === hoy() : true;
        } catch (error) {
            return false;
        }
    }

    function recordarCierre(popup) {
        var frecuencia = popup.dataset.frecuencia;

        if (frecuencia === 'siempre') {
            return;
        }

        var deposito = almacen(frecuencia);

        if (deposito === null) {
            return;
        }

        try {
            deposito.setItem(popup.dataset.clave, frecuencia === 'diaria' ? hoy() : '1');
        } catch (error) {
            // Cuota llena o escritura prohibida: el popup volverá a salir. Es
            // la peor consecuencia posible aquí, y es tolerable.
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var popup = document.getElementById('popup');

        if (popup === null || typeof popup.showModal !== 'function' || yaLoCerro(popup)) {
            return;
        }

        // El cierre se recuerda en `close` y no en el clic del botón: así
        // cuenta igual cerrar con Escape, con la equis o con el botón de
        // abajo. El <dialog> dispara `close` en los tres casos.
        popup.addEventListener('close', function () {
            recordarCierre(popup);
        });

        // Pulsar el fondo oscuro cierra, como en cualquier modal. El evento
        // llega al propio <dialog> solo cuando el clic cae fuera del contenido;
        // por eso basta comparar el destino con el elemento.
        popup.addEventListener('click', function (evento) {
            if (evento.target === popup) {
                popup.close('fondo');
            }
        });

        // Un respiro antes de aparecer: plantarse encima de la página en el
        // mismo instante de la carga se lee como un error del sitio, y el
        // visitante lo cierra sin leerlo.
        window.setTimeout(function () {
            if (!popup.open) {
                popup.showModal();
            }
        }, 900);
    });
})();

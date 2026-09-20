/*
 * Configuración del asistente de IA: los modelos del proveedor elegido se
 * cargan SOLOS —al abrir la pantalla y al cambiar de proveedor— desde el
 * endpoint del panel, que consulta el GET /models del proveedor (con su
 * clave guardada) y cachea el resultado. Si mañana el proveedor publica
 * un modelo nuevo, aparece aquí sin escribir nada a mano.
 */
(function () {
    'use strict';

    var selectorProveedor = document.getElementById('ia-proveedor');
    var selectorModelo = document.getElementById('ia-modelo');
    var nota = document.getElementById('ia-modelos-nota');
    var botonActualizar = document.getElementById('ia-actualizar-modelos');

    if (!selectorProveedor || !selectorModelo) { return; }

    var modeloGuardado = selectorModelo.getAttribute('data-guardado') || '';

    function pintar(modelos, origen, textoNota) {
        var elegido = selectorModelo.value || modeloGuardado;
        selectorModelo.innerHTML = '';

        var predeterminada = document.createElement('option');
        predeterminada.value = '';
        predeterminada.textContent = '(predeterminado del proveedor)';
        selectorModelo.appendChild(predeterminada);

        // El guardado se conserva aunque el proveedor ya no lo liste:
        // cambiarlo es decisión del funcionario, no de una lista.
        if (elegido && modelos.indexOf(elegido) === -1) {
            var actual = document.createElement('option');
            actual.value = elegido;
            actual.textContent = elegido + ' (guardado)';
            selectorModelo.appendChild(actual);
        }

        modelos.forEach(function (modelo) {
            var opcion = document.createElement('option');
            opcion.value = modelo;
            opcion.textContent = modelo;
            selectorModelo.appendChild(opcion);
        });

        selectorModelo.value = elegido;

        if (nota) {
            nota.textContent = modelos.length + ' modelos ('
                + (origen === 'api' ? 'lista en vivo del proveedor' : 'lista de referencia') + ').'
                + (textoNota ? ' ' + textoNota : '');
        }
    }

    function cargar(refrescar) {
        if (nota) { nota.textContent = 'Consultando modelos…'; }

        var url = '/admin/ia/modelos?proveedor=' + encodeURIComponent(selectorProveedor.value)
            + (refrescar ? '&refrescar=1' : '');

        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (datos) {
                pintar(datos.modelos || [], datos.origen || 'estatico', datos.nota || '');
            })
            .catch(function () {
                if (nota) { nota.textContent = 'No se pudo consultar la lista de modelos.'; }
            });
    }

    selectorProveedor.addEventListener('change', function () {
        selectorModelo.value = '';
        cargar(false);
    });

    if (botonActualizar) {
        botonActualizar.addEventListener('click', function () { cargar(true); });
    }

    // Al abrir la pantalla: la lista del proveedor guardado, ya actualizada.
    cargar(false);
})();

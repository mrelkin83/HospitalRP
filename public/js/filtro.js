// Filtro sin recargar (Fase 2): un <select data-filtro="id-de-lista"> oculta
// los elementos cuyo data-valores no incluye el valor elegido. Sin framework:
// el presupuesto de JS del sitio público es sagrado.
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('select[data-filtro]').forEach(function (selector) {
            var lista = document.getElementById(selector.dataset.filtro);

            if (!lista) {
                return;
            }

            var resultado = document.querySelector('[data-filtro-resultado="' + selector.dataset.filtro + '"]');

            selector.addEventListener('change', function () {
                var valor = selector.value;
                var visibles = 0;

                lista.querySelectorAll('[data-valores]').forEach(function (item) {
                    var coincide = valor === '' || item.dataset.valores.split(',').indexOf(valor) !== -1;
                    item.hidden = !coincide;

                    if (coincide) {
                        visibles++;
                    }
                });

                // Anuncio para lectores de pantalla (región aria-live).
                if (resultado) {
                    resultado.textContent = valor === ''
                        ? ''
                        : visibles + ' resultado(s) para el filtro elegido.';
                }
            });
        });
    });
})();

// Pestañas y carrusel de «Campañas y comunicados» (portada).
//
// Alpine en su compilación CSP (`@alpinejs/csp`): el sitio público va con
// `script-src 'self'` y sin `unsafe-eval`, así que la compilación normal
// —que evalúa las expresiones de los atributos con `new Function()`— quedaría
// muda. Esa compilación solo resuelve NOMBRES de propiedades y métodos del
// componente, nunca expresiones sueltas en el HTML; por eso aquí hay getters
// como `claseComunidad` en lugar de un `:class="tab === 'x' ? … : …"`.
//
// Qué NO hace: traer contenido. Las dos listas llegan renderizadas desde el
// servidor. Sin JavaScript se ven las dos completas, una debajo de otra, y
// eso es contenido legible e indexable, no una caja vacía.
(function () {
    'use strict';

    var AUDIENCIAS = ['comunidad', 'personal'];

    // Cada cuánto pasa sola a la siguiente pieza. Lo decide el hospital en
    // Ajustes del sitio → Portada y llega en `data-rotacion-ms`; siete
    // segundos es el respaldo. Cero apaga la rotación.
    var RELEVO_POR_OMISION_MS = 7000;

    function sufijo(audiencia) {
        return audiencia === 'personal' ? 'Personal' : 'Comunidad';
    }

    document.addEventListener('alpine:init', function () {
        window.Alpine.data('comunicaciones', function () {
            return {
                pestana: 'comunidad',
                indices: { comunidad: 0, personal: 0 },

                temporizador: null,

                init: function () {
                    // Si Comunicaciones solo tiene publicado para el personal,
                    // el servidor pinta un único panel y la pestaña por omisión
                    // no existe: arrancar en ella dejaría la sección en blanco.
                    if (this.diapositivas('comunidad').length === 0) {
                        this.pestana = 'personal';
                    }

                    this.sincronizar();
                    this.arrancarRelevo();

                    // Quien pidió menos movimiento en la barra de accesibilidad
                    // —o en su sistema operativo— no quiere carruseles que se
                    // muevan solos. Se respeta en caliente, sin recargar.
                    var self = this;

                    window.addEventListener('cambio-de-movimiento', function () {
                        self.arrancarRelevo();
                    });
                },

                /**
                 * Rotación automática, como un héroe. Se detiene mientras el
                 * puntero está encima o el foco del teclado está dentro: nada
                 * más molesto que perder de vista lo que estabas leyendo, y
                 * peor aún que te cambien el enlace justo antes de pulsarlo.
                 */
                arrancarRelevo: function () {
                    this.detenerRelevo();

                    var espera = this.esperaConfigurada();

                    if (espera <= 0 || this.movimientoReducido()) {
                        return;
                    }

                    var self = this;

                    this.temporizador = window.setInterval(function () {
                        if (self.pausado()) {
                            return;
                        }

                        self.mover(1);
                    }, espera);
                },

                esperaConfigurada: function () {
                    var crudo = this.$root ? this.$root.dataset.rotacionMs : null;
                    var ms = crudo === undefined || crudo === null || crudo === '' ? NaN : Number(crudo);

                    if (Number.isNaN(ms)) {
                        return RELEVO_POR_OMISION_MS;
                    }

                    // Un segundo es el mínimo con el que un carrusel se puede
                    // seguir con la vista; por debajo, parpadea.
                    return ms <= 0 ? 0 : Math.max(1000, ms);
                },

                detenerRelevo: function () {
                    if (this.temporizador !== null) {
                        window.clearInterval(this.temporizador);
                        this.temporizador = null;
                    }
                },

                movimientoReducido: function () {
                    if (document.documentElement.dataset.movimiento === 'reducido') {
                        return true;
                    }

                    return window.matchMedia
                        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                },

                pausado: function () {
                    var seccion = this.$root;

                    if (!seccion) {
                        return false;
                    }

                    // `:hover` cubre el ratón; `contains(activeElement)` cubre el
                    // teclado. Y si la pestaña del navegador no está visible, no
                    // tiene sentido gastar relevos ni batería.
                    return document.hidden
                        || seccion.matches(':hover')
                        || seccion.contains(document.activeElement);
                },

                verComunidad: function () {
                    this.pestana = 'comunidad';
                    this.sincronizar();
                    this.arrancarRelevo();
                },

                verPersonal: function () {
                    this.pestana = 'personal';
                    this.sincronizar();
                    this.arrancarRelevo();
                },

                anterior: function () {
                    this.mover(-1);
                    this.arrancarRelevo();
                },

                siguiente: function () {
                    this.mover(1);
                    this.arrancarRelevo();
                },

                mover: function (paso) {
                    var total = this.diapositivas(this.pestana).length;

                    if (total < 2) {
                        return;
                    }

                    // Circular: desde la última, «siguiente» vuelve a la primera.
                    this.indices[this.pestana] = (this.indices[this.pestana] + paso + total) % total;
                    this.sincronizar();
                },

                diapositivas: function (audiencia) {
                    var lista = this.$refs['lista' + sufijo(audiencia)];

                    return lista ? Array.prototype.slice.call(lista.children) : [];
                },

                // El estado visible se escribe como atributo y lo esconde el CSS
                // (`[data-js] …[data-activa="false"]`). Así el servidor entrega
                // el primer estado ya pintado: no hay parpadeo mientras Alpine
                // arranca, y sin JS no se esconde nada.
                sincronizar: function () {
                    var self = this;

                    AUDIENCIAS.forEach(function (audiencia) {
                        var panel = self.$refs['panel' + sufijo(audiencia)];

                        if (panel) {
                            panel.dataset.activa = audiencia === self.pestana ? 'true' : 'false';
                        }

                        self.diapositivas(audiencia).forEach(function (diapositiva, i) {
                            var visible = i === self.indices[audiencia];

                            diapositiva.dataset.activa = visible ? 'true' : 'false';
                            // El lector de pantalla no debe leer las tres a la vez.
                            diapositiva.setAttribute('aria-hidden', visible ? 'false' : 'true');
                        });
                    });
                },

                clasePestana: function (activa) {
                    return activa
                        ? 'bg-gradient-to-tr from-primario-700 to-primario-500 text-white shadow-md'
                        : 'text-primario-700 dark:text-primario-300 hover:bg-white/10';
                },

                get claseComunidad() {
                    return this.clasePestana(this.pestana === 'comunidad');
                },

                get clasePersonal() {
                    return this.clasePestana(this.pestana === 'personal');
                },

                get seleccionComunidad() {
                    return this.pestana === 'comunidad' ? 'true' : 'false';
                },

                get seleccionPersonal() {
                    return this.pestana === 'personal' ? 'true' : 'false';
                },

                /** «2 de 3», para que el carrusel diga dónde está. */
                get posicion() {
                    var total = this.diapositivas(this.pestana).length;

                    return total < 2 ? '' : (this.indices[this.pestana] + 1) + ' de ' + total;
                },
            };
        });
    });
})();

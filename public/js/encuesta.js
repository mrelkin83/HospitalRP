// Encuesta de la portada: se responde sin salir de la página.
//
// Compilación CSP de Alpine (ver comunicaciones.js): el HTML solo nombra
// propiedades y métodos, nunca expresiones. Lo que hace este componente:
//
//   1. Enseña UNA pregunta a la vez, con «Anterior» y «Siguiente».
//   2. No deja avanzar si la pregunta es obligatoria y está sin contestar.
//   3. Envía por `fetch` al mismo endpoint del formulario completo
//      (POST /encuestas/{slug}, que responde JSON si se lo piden por Accept)
//      y pinta los resultados devueltos sin recargar.
//   4. Permite ver los resultados sin haber votado: ya vienen renderizados
//      desde el servidor y aquí solo se muestran u ocultan.
//
// Sin JavaScript nada de esto existe y la tarjeta sigue funcionando: el
// formulario se envía entero y el navegador va a la página de resultados.
(function () {
    'use strict';

    document.addEventListener('alpine:init', function () {
        window.Alpine.data('encuestaPortada', function () {
            return {
                indice: 0,
                aviso: '',
                enviando: false,
                enviada: false,
                viendoResultados: false,

                init: function () {
                    this.sincronizar();
                },

                preguntas: function () {
                    var lista = this.$refs.preguntas;

                    return lista ? Array.prototype.slice.call(lista.children) : [];
                },

                get esPrimera() {
                    return this.indice === 0;
                },

                get esUltima() {
                    return this.indice >= this.preguntas().length - 1;
                },

                get posicion() {
                    var total = this.preguntas().length;

                    return total < 2 ? '' : 'Pregunta ' + (this.indice + 1) + ' de ' + total;
                },

                get etiquetaResultados() {
                    if (this.enviada) {
                        return this.viendoResultados ? 'Volver a la encuesta' : 'Ver resultados';
                    }

                    return this.viendoResultados ? 'Responder la encuesta' : 'Ver resultados sin responder';
                },

                anterior: function () {
                    if (this.indice > 0) {
                        this.indice--;
                        this.aviso = '';
                        this.sincronizar();
                    }
                },

                siguiente: function () {
                    if (!this.respondida(this.preguntas()[this.indice])) {
                        return;
                    }

                    if (!this.esUltima) {
                        this.indice++;
                        this.sincronizar();
                    }
                },

                alternarResultados: function () {
                    this.viendoResultados = !this.viendoResultados;
                    this.sincronizar();
                },

                /**
                 * Se valida en el cliente solo para no hacer ir y volver al
                 * servidor por una casilla sin marcar; quien manda sigue siendo
                 * el servidor, que revalida todo antes de guardar.
                 */
                respondida: function (pregunta) {
                    if (!pregunta || pregunta.dataset.obligatoria !== 'true') {
                        return true;
                    }

                    var marcada = pregunta.querySelector('input:checked');
                    var texto = pregunta.querySelector('textarea, input[type="text"]');
                    var contestada = marcada !== null || (texto !== null && texto.value.trim() !== '');

                    if (!contestada) {
                        this.aviso = 'Esta pregunta es obligatoria.';
                        this.mostrar(this.$refs.aviso, true);

                        return false;
                    }

                    this.aviso = '';
                    this.mostrar(this.$refs.aviso, false);

                    return true;
                },

                enviar: function (evento) {
                    var formulario = this.$refs.formulario;

                    // Sin fetch —navegador muy viejo— se deja pasar el envío
                    // normal: peor es quedarse sin poder responder.
                    if (!window.fetch || !formulario) {
                        return;
                    }

                    evento.preventDefault();

                    if (this.enviando || !this.respondida(this.preguntas()[this.indice])) {
                        return;
                    }

                    this.enviando = true;
                    var self = this;

                    fetch(formulario.action, {
                        method: 'POST',
                        headers: { Accept: 'application/json' },
                        body: new FormData(formulario),
                    })
                        .then(function (respuesta) {
                            return respuesta.json().then(function (datos) {
                                return { ok: respuesta.ok, datos: datos };
                            });
                        })
                        .then(function (resultado) {
                            self.enviando = false;

                            if (!resultado.ok || resultado.datos.ok !== true) {
                                self.aviso = 'Falta responder alguna pregunta obligatoria.';
                                self.mostrar(self.$refs.aviso, true);

                                return;
                            }

                            self.enviada = true;
                            self.viendoResultados = true;
                            self.aviso = '';
                            self.pintarResultados(resultado.datos);
                            self.sincronizar();
                        })
                        .catch(function () {
                            // El envío falló de verdad (sin red, por ejemplo):
                            // se deja el formulario como estaba para reintentar.
                            self.enviando = false;
                            self.aviso = 'No se pudo enviar. Revisa tu conexión e inténtalo de nuevo.';
                            self.mostrar(self.$refs.aviso, true);
                        });
                },

                /**
                 * Reescribe las barras con las cifras recién devueltas. Se
                 * actualizan las que ya existen en el DOM en vez de construir
                 * HTML desde JavaScript: así el marcado y las clases siguen
                 * siendo los del servidor, y no hay dos maquetas que mantener.
                 */
                pintarResultados: function (datos) {
                    var contenedor = this.$refs.resultados;

                    if (!contenedor || !datos.resultados) {
                        return;
                    }

                    var total = this.$refs.totalRespuestas;

                    if (total) {
                        total.textContent = datos.total === 1
                            ? '1 persona ha respondido.'
                            : datos.total.toLocaleString('es-CO') + ' personas han respondido.';
                    }

                    if (this.$refs.sinRespuestas && datos.total > 0) {
                        this.$refs.sinRespuestas.remove();
                    }

                    var secciones = Array.prototype.slice.call(contenedor.querySelectorAll('section'));
                    var conOpciones = datos.resultados.filter(function (pregunta) {
                        return pregunta.tipo !== 'texto' && pregunta.opciones.length > 0;
                    });

                    conOpciones.forEach(function (pregunta, i) {
                        var seccion = secciones[i];

                        if (!seccion) {
                            return;
                        }

                        Array.prototype.slice.call(seccion.querySelectorAll('li')).forEach(function (fila, j) {
                            var opcion = pregunta.opciones[j];

                            if (!opcion) {
                                return;
                            }

                            var cifra = fila.querySelector('span.tabular-nums');
                            var barra = fila.querySelector('.barra');

                            if (cifra) {
                                cifra.textContent = opcion.porcentaje + ' %';
                            }

                            if (barra) {
                                // Pasos de 5 %: las clases .barra-N de app.css, porque
                                // la CSP del sitio público prohíbe el estilo en línea.
                                barra.className = 'barra barra-' + (Math.round(Math.min(100, Math.max(0, opcion.porcentaje)) / 5) * 5);
                            }
                        });
                    });
                },

                sincronizar: function () {
                    var self = this;
                    var mostrandoResultados = this.viendoResultados;

                    this.mostrar(this.$refs.formulario, !mostrandoResultados);
                    this.mostrar(this.$refs.resultados, mostrandoResultados);

                    this.preguntas().forEach(function (pregunta, i) {
                        self.mostrar(pregunta, i === self.indice);
                    });
                },

                mostrar: function (elemento, visible) {
                    if (elemento) {
                        elemento.dataset.activa = visible ? 'true' : 'false';
                    }
                },
            };
        });
    });
})();

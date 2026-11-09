// Menús desplegables de la barra de navegación (sitio público y panel).
//
// Los desplegables son <details name="..."> y no un widget de JavaScript: se
// abren con el teclado, funcionan sin scripts y el navegador ya sabe cerrar
// los hermanos que comparten `name` (el «acordeón exclusivo» de HTML). Este
// archivo solo añade lo que falta para que se comporten como un menú y no
// como un acordeón:
//
//   1. Cerrarlos al pulsar fuera.
//   2. Cerrarlos con Escape, devolviendo el foco a su cabecera.
//   3. Respaldo de exclusividad para los navegadores que todavía ignoran
//      `name` en <details> (anteriores a Chrome 120 / Safari 17.2 / Firefox
//      130). Sin esto, ahí siguen quedando dos menús abiertos a la vez.
//
// Solo actúa sobre los <details> que llevan `name`: en el panel hay muchos
// otros (filtros, detalles de fila) que SÍ deben poder estar abiertos a la vez.
(function () {
    'use strict';

    var SELECTOR = 'nav details[name]';

    // En un navegador con soporte, `name` se refleja como propiedad del
    // elemento; donde no, la propiedad no existe y hay que cerrar a mano.
    var SOPORTA_EXCLUSIVIDAD = 'name' in document.createElement('details');

    function menus() {
        return Array.prototype.slice.call(document.querySelectorAll(SELECTOR));
    }

    function menuDe(objetivo) {
        return objetivo instanceof Element ? objetivo.closest(SELECTOR) : null;
    }

    function cerrarTodos(excepto) {
        menus().forEach(function (details) {
            if (details !== excepto && details.open) {
                details.open = false;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var desplegables = menus();

        if (desplegables.length === 0) {
            return;
        }

        if (!SOPORTA_EXCLUSIVIDAD) {
            desplegables.forEach(function (details) {
                details.addEventListener('toggle', function () {
                    if (details.open) {
                        cerrarTodos(details);
                    }
                });
            });
        }

        // Pulsar fuera cierra. Se escucha en captura para que el menú se haya
        // cerrado antes de que el clic llegue a su destino: esperando a la
        // fase de burbujeo, al pulsar un enlace de la barra el menú se quedaba
        // abierto durante toda la navegación.
        document.addEventListener('click', function (evento) {
            cerrarTodos(menuDe(evento.target));
        }, true);

        // El foco puede salir del menú con el tabulador, sin ningún clic.
        document.addEventListener('focusin', function (evento) {
            if (menuDe(evento.target) === null) {
                cerrarTodos(null);
            }
        });

        document.addEventListener('keydown', function (evento) {
            if (evento.key !== 'Escape') {
                return;
            }

            var activo = menuDe(document.activeElement);

            cerrarTodos(null);

            // Escape devuelve el foco a la cabecera del menú que se cerró; si
            // no, el foco se queda dentro de un submenú ya invisible y el
            // siguiente tabulador salta a un sitio impredecible.
            if (activo !== null) {
                var cabecera = activo.querySelector('summary');

                if (cabecera !== null) {
                    cabecera.focus();
                }
            }
        });
    });

    // --- Apertura automática al pasar el cursor por encima ---
    //
    // El desplegable se abre con solo posar el cursor en su cabecera, sin
    // pulsar. Se limita a escritorio con ratón de verdad (`hover: hover` y
    // `pointer: fine`) y a partir de 1024 px, por dos razones concretas:
    //
    //   - En pantalla táctil el navegador simula el hover con el primer
    //     toque: ese mismo toque abriría el menú y el <summary> lo cerraría
    //     acto seguido, dejándolo inservible.
    //   - Por debajo de 1024 px el submenú se abre en flujo (app.css) dentro
    //     del menú vertical; abrirlo al pasar empujaría hacia abajo la lista
    //     que la persona está recorriendo con el dedo o el cursor.
    //
    // El clic sigue mandando: pulsar la cabecera de un menú abierto por hover
    // lo cierra —comportamiento nativo de <details>— y no se reabre hasta que
    // el cursor salga y vuelva a entrar. El teclado no se toca.
    //
    // Los dos retardos no son adorno:
    //
    //   - Abrir con espera evita que la barra se dispare cuando el cursor
    //     solo cruza de paso hacia otra cosa. Si ya hay un menú abierto el
    //     relevo es instantáneo, que es como se comporta una barra de menús.
    //   - Cerrar con gracia cubre los 4 px de hueco (`mt-1`) entre la
    //     cabecera y el panel flotante: sin esa holgura el menú se cerraba
    //     justo en el camino hacia sus propios enlaces. Es, además, lo que
    //     pide la WCAG 2.1 en 1.4.13 («hoverable»); el «dismissible» del
    //     mismo criterio ya lo cubre Escape, más arriba.
    var APUNTADOR_RATON = '(hover: hover) and (pointer: fine)';
    var ANCHO_ESCRITORIO = '(min-width: 1024px)';
    var RETARDO_ABRIR = 120;
    var RETARDO_CERRAR = 300;

    document.addEventListener('DOMContentLoaded', function () {
        var desplegables = menus();

        if (desplegables.length === 0 || typeof window.matchMedia !== 'function') {
            return;
        }

        var raton = window.matchMedia(APUNTADOR_RATON);
        var escritorio = window.matchMedia(ANCHO_ESCRITORIO);

        // Un solo temporizador compartido: salir de un menú y entrar en otro
        // debe cancelar el cierre pendiente, no acumular dos relojes que
        // compitan. El relevo lo resuelve `cerrarTodos` al abrir el nuevo.
        var reloj = null;

        function conRaton() {
            return raton.matches && escritorio.matches;
        }

        function cancelar() {
            if (reloj !== null) {
                window.clearTimeout(reloj);
                reloj = null;
            }
        }

        function programar(accion, espera) {
            cancelar();

            reloj = window.setTimeout(function () {
                reloj = null;
                accion();
            }, espera);
        }

        desplegables.forEach(function (details) {
            // mouseenter/mouseleave y no mouseover/mouseout: no burbujean, así
            // que moverse por dentro del submenú no repite el disparo. Y como
            // el panel flotante es descendiente del <details>, entrar en él
            // cuenta como seguir dentro aunque geométricamente no lo esté.
            details.addEventListener('mouseenter', function () {
                if (!conRaton()) {
                    return;
                }

                // Entrar anula lo que estuviera pendiente. Importa sobre todo
                // al pasar de la cabecera al panel flotante: entre los dos hay
                // 4 px de aire, así que el navegador dispara un `mouseleave`
                // en el camino. Si ese cierre no se anulara aquí, el menú se
                // cerraría solo con el cursor dentro de sus propios enlaces.
                cancelar();

                if (details.open) {
                    return;
                }

                var huboRelevo = desplegables.some(function (otro) {
                    return otro.open;
                });

                programar(function () {
                    cerrarTodos(details);
                    details.open = true;
                }, huboRelevo ? 0 : RETARDO_ABRIR);
            });

            details.addEventListener('mouseleave', function () {
                if (!conRaton()) {
                    return;
                }

                // Salir también anula: si el cursor se va antes de que venza
                // la espera de apertura, el menú no debe abrirse a espaldas de
                // nadie.
                cancelar();

                if (!details.open) {
                    return;
                }

                programar(function () {
                    // Si el foco está dentro, el menú no se abrió al pasar:
                    // lo abrió un clic o el teclado. Ahí el cursor no es quien
                    // manda y cerrarlo le quitaría de las manos un menú que la
                    // persona abrió a propósito. Lo cierran Escape, el clic
                    // fuera y el tabulador, como siempre.
                    if (details.contains(document.activeElement)) {
                        return;
                    }

                    details.open = false;
                }, RETARDO_CERRAR);
            });
        });
    });

    // --- Botón «Menú» del móvil (Auditoría Visual V9, sección 11) ---
    //
    // En pantallas angostas la fila horizontal mostraba completas solo 3 de
    // las 9 categorías. El botón despliega la lista entera en vertical
    // (app.css pinta el estado según `data-menu-abierto` del <nav>), anuncia
    // su estado con aria-expanded y se maneja igual por toque y por teclado:
    // es un <button> de verdad, así que Enter y espacio ya funcionan solos.
    // Escape lo cierra y devuelve el foco al botón, el mismo contrato que los
    // submenús de arriba. En el panel admin no existe [data-menu-movil] y
    // este bloque no hace nada.
    document.addEventListener('DOMContentLoaded', function () {
        var boton = document.querySelector('[data-menu-movil]');
        var nav = document.querySelector('[data-menu-principal]');

        if (boton === null || nav === null) {
            return;
        }

        var iconoAbrir = boton.querySelector('[data-icono="abrir"]');
        var iconoCerrar = boton.querySelector('[data-icono="cerrar"]');

        function abierto() {
            return nav.hasAttribute('data-menu-abierto');
        }

        function pintar(estado) {
            if (estado) {
                nav.setAttribute('data-menu-abierto', '');
            } else {
                nav.removeAttribute('data-menu-abierto');
            }

            boton.setAttribute('aria-expanded', estado ? 'true' : 'false');

            if (iconoAbrir !== null && iconoCerrar !== null) {
                iconoAbrir.hidden = estado;
                iconoCerrar.hidden = !estado;
            }
        }

        boton.addEventListener('click', function () {
            pintar(!abierto());
        });

        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape' && abierto()) {
                pintar(false);
                boton.focus();
            }
        });

        // Al pulsar un enlace del menú abierto se cierra: el bfcache conserva
        // el DOM tal cual quedó y, si no, al volver con el historial la página
        // cargaría con media pantalla de menú desplegado.
        nav.addEventListener('click', function (evento) {
            if (evento.target instanceof Element && evento.target.closest('a') !== null) {
                pintar(false);
            }
        });
    });
})();

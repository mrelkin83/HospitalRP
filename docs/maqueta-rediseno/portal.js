/* =====================================================================
   Maqueta del rediseño — comportamiento del portal público.
   Carga con `defer`, así que el DOM ya existe. Todo degrada: si un bloque
   no está en la página, su sección simplemente no hace nada.
   ===================================================================== */
(function () {
    'use strict';

    /* --- Carrusel del hero ---------------------------------------------
       Avanza solo. Cada diapositiva es un enlace, así que el temporizador
       se detiene cuando el cursor o el foco están encima: nadie debería
       perder el clic porque la imagen cambió justo antes. */
    var diapositivas = Array.prototype.slice.call(document.querySelectorAll('.hero-diapositiva'));
    var puntos = Array.prototype.slice.call(document.querySelectorAll('.hero-puntos button'));
    var botonPausa = document.querySelector('[data-hero-pausa]');
    var actual = 0;
    var reloj = null;
    var pausadoPorElVisitante = false;

    function mostrar(i) {
        actual = (i + diapositivas.length) % diapositivas.length;
        diapositivas.forEach(function (d, n) { d.classList.toggle('activa', n === actual); });
        puntos.forEach(function (p, n) { p.setAttribute('aria-current', n === actual ? 'true' : 'false'); });
    }

    function detener() { if (reloj) { window.clearInterval(reloj); reloj = null; } }

    /* El cambio es automático siempre. `prefers-reduced-motion` no lo apaga:
       solo suprime el fundido (lo hace el bloque correspondiente de la hoja
       de estilos), de modo que la imagen cambia sin movimiento. Quien no
       quiera que avance tiene el botón de pausa, que es lo que exige el
       criterio 2.2.2 de la WCAG para contenido que se actualiza solo. */
    function arrancar() {
        detener();
        if (pausadoPorElVisitante || diapositivas.length < 2) { return; }
        reloj = window.setInterval(function () { mostrar(actual + 1); }, 5500);
    }

    puntos.forEach(function (p, n) {
        p.addEventListener('click', function () { mostrar(n); arrancar(); });
    });

    if (botonPausa) {
        botonPausa.addEventListener('click', function () {
            pausadoPorElVisitante = !pausadoPorElVisitante;
            botonPausa.setAttribute('aria-pressed', pausadoPorElVisitante ? 'true' : 'false');
            botonPausa.setAttribute('aria-label', pausadoPorElVisitante
                ? 'Reanudar el cambio automático de imágenes'
                : 'Pausar el cambio automático de imágenes');
            botonPausa.classList.toggle('pausado', pausadoPorElVisitante);
            arrancar();
        });
    }

    var hero = document.querySelector('.hero');
    if (hero) {
        // Mientras el cursor o el foco están sobre la foto no cambia: la
        // diapositiva es un enlace y nadie debe perder el clic.
        hero.addEventListener('mouseenter', detener);
        hero.addEventListener('mouseleave', arrancar);
        hero.addEventListener('focusin', detener);
        hero.addEventListener('focusout', arrancar);
    }
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) { detener(); } else { arrancar(); }
    });
    arrancar();

    /* --- Modo oscuro: solo si el visitante lo pide ---------------------
       Usa el atributo propio `data-tema`, nunca `data-theme`, para que ni
       el tema de Windows ni el del navegador alteren la página. */
    var botonTema = document.querySelector('[data-tema-boton]');
    if (botonTema) {
        botonTema.addEventListener('click', function () {
            var raiz = document.documentElement;
            var oscuro = raiz.getAttribute('data-tema') === 'oscuro';
            raiz.setAttribute('data-tema', oscuro ? 'claro' : 'oscuro');
            botonTema.textContent = oscuro ? 'Tema oscuro' : 'Tema claro';
            botonTema.setAttribute('aria-pressed', oscuro ? 'false' : 'true');
        });
    }

    /* --- Menú móvil ----------------------------------------------------- */
    var cajon = document.getElementById('cajon-menu');
    var abrirMenu = document.querySelector('.abrir-menu');
    var cerrarMenu = document.querySelector('.cerrar-menu');

    function menu(abierto) {
        if (!cajon) { return; }
        cajon.setAttribute('data-abierto', abierto ? 'si' : 'no');
        document.body.style.overflow = abierto ? 'hidden' : '';
        if (abrirMenu) { abrirMenu.setAttribute('aria-expanded', abierto ? 'true' : 'false'); }
        if (abierto && cerrarMenu) { cerrarMenu.focus(); }
        if (!abierto && abrirMenu) { abrirMenu.focus(); }
    }

    if (abrirMenu) { abrirMenu.addEventListener('click', function () { menu(true); }); }
    if (cerrarMenu) { cerrarMenu.addEventListener('click', function () { menu(false); }); }
    if (cajon) {
        cajon.addEventListener('click', function (ev) {
            if (ev.target.closest('a')) { menu(false); }
        });
    }

    /* --- Orientador guiado ----------------------------------------------
       Árbol de decisión fijo, sin IA y sin dato clínico: enruta al trámite,
       al teléfono o a la página que corresponde. */
    var panel = document.getElementById('orientador');
    var hilo = document.getElementById('orientador-hilo');
    var opciones = document.getElementById('orientador-opciones');
    var abrirOrientador = document.getElementById('abrir-orientador');

    var TEMAS = {
        cita: {
            boton: 'Pedir una cita',
            dice: 'La cita de consulta externa se solicita en línea o por teléfono al <a href="tel:+573175153643">317 515 3643</a>, de lunes a viernes de 7:30 a. m. a 12:00 m. y de 2:00 a 6:00 p. m. Ten a la mano tu documento de identidad y el nombre de tu EPS.',
            enlace: { texto: 'Solicitar cita en línea', href: 'index.html#ciudadania' }
        },
        urgencias: {
            boton: 'Urgencias',
            dice: 'Urgencias atiende <strong>las 24 horas, todos los días</strong>, en la Calle 16 No. 17‑141, Barrio Hernando Santana. No necesitas cita. Si es una emergencia, llama al <a href="tel:+576055712339">(605) 571 2339</a> o acude directamente.',
            enlace: { texto: 'Ver cómo llegar', href: 'index.html#ciudadania' }
        },
        pqrsdf: {
            boton: 'Poner una PQRSDF',
            dice: 'Puedes radicar una petición, queja, reclamo, sugerencia, denuncia o felicitación desde el portal. Al terminar recibes un número de radicado para hacerle seguimiento; el término de respuesta se cuenta en días hábiles.',
            enlace: { texto: 'Radicar PQRSDF', href: 'index.html#ciudadania' }
        },
        radicado: {
            boton: 'Consultar un radicado',
            dice: 'Con el número de radicado y tu documento de identidad consultas el estado de la solicitud. No necesitas crear una cuenta.',
            enlace: { texto: 'Consultar un radicado', href: 'index.html#ciudadania' }
        },
        sede: {
            boton: 'Horarios y ubicación',
            dice: 'Sede única: <strong>Calle 16 No. 17‑141</strong>, Barrio Hernando Santana (Av. La Popa), Valledupar. Atención administrativa y consulta externa de lunes a viernes, 7:30 a. m. – 12:00 m. y 2:00 – 6:00 p. m. Urgencias, las 24 horas.',
            enlace: { texto: 'Ver la sede', href: 'index.html#ciudadania' }
        },
        documentos: {
            boton: 'Un documento o certificado',
            dice: 'Informes, contratación, presupuesto y normativa están en Transparencia. La <strong>copia de historia clínica</strong> se radica como trámite y se entrega por un canal controlado: por seguridad, el portal nunca entrega información clínica.',
            enlace: { texto: 'Ir a Transparencia', href: 'index.html#transparencia' }
        },
        persona: {
            boton: 'Hablar con una persona',
            dice: 'Atención al Usuario (SIAU) te atiende en el PBX <a href="tel:+576055712339">(605) 571 2339</a> o en <a href="mailto:contacto@hrplopez.gov.co">contacto@hrplopez.gov.co</a>, de lunes a viernes en horario de oficina.',
            enlace: null
        }
    };

    var ORDEN = ['cita', 'urgencias', 'pqrsdf', 'radicado', 'sede', 'documentos', 'persona'];

    function burbuja(clase, html) {
        var p = document.createElement('p');
        p.className = 'burbuja ' + clase;
        p.innerHTML = html;
        hilo.appendChild(p);
        hilo.scrollTop = hilo.scrollHeight;
    }

    function pintarOpciones(omitir) {
        opciones.innerHTML = '';
        ORDEN.forEach(function (clave) {
            if (clave === omitir) { return; }
            var b = document.createElement('button');
            b.type = 'button';
            b.textContent = TEMAS[clave].boton;
            b.addEventListener('click', function () { responder(clave); });
            opciones.appendChild(b);
        });
    }

    function responder(clave) {
        var tema = TEMAS[clave];
        burbuja('visitante', tema.boton);
        var html = tema.dice;
        if (tema.enlace) {
            html += '<br><a href="' + tema.enlace.href + '">' + tema.enlace.texto + ' →</a>';
        }
        burbuja('asistente', html);
        pintarOpciones(clave);
    }

    function orientador(abierto) {
        if (!panel) { return; }
        panel.setAttribute('data-abierto', abierto ? 'si' : 'no');
        abrirOrientador.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        if (abierto) {
            var primera = panel.querySelector('.orientador-opciones button');
            if (primera) { primera.focus(); }
        } else {
            abrirOrientador.focus();
        }
    }

    if (panel && abrirOrientador) {
        pintarOpciones(null);
        abrirOrientador.addEventListener('click', function () {
            orientador(panel.getAttribute('data-abierto') !== 'si');
        });
        panel.querySelector('[data-cerrar-orientador]').addEventListener('click', function () {
            orientador(false);
        });
    }

    document.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Escape') { return; }
        if (cajon && cajon.getAttribute('data-abierto') === 'si') { menu(false); }
        if (panel && panel.getAttribute('data-abierto') === 'si') { orientador(false); }
    });
}());

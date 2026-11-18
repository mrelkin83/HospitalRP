// Service worker del sitio público (Fase 7).
//
// Estrategia deliberadamente conservadora para un sitio institucional:
//  - Estáticos (css, js, iconos, fuentes): caché primero. Las URL llevan
//    huella (`?v=…`, ver App\Core\Support\Recursos), así que un archivo
//    nuevo es una URL nueva y nunca se sirve una copia vieja.
//
//    Esto NO era cierto hasta el 2026-09-14: la hoja de estilos se pedía
//    siempre como `/build/app.css` y caché-primero la congelaba. Al
//    cambiar la paleta del sitio, quien ya había visitado la página
//    seguía viendo el diseño anterior. Si algún día se quita la huella de
//    las URL, hay que quitar también el caché-primero de aquí.
//  - Navegaciones: red primero; si no hay conexión, la copia en caché de esa
//    página o el cascarón /desconectado.html.
//  - JAMÁS se cachea el panel (/admin) ni respuestas no-GET: allí viven
//    sesiones y datos personales.
'use strict';

// Subir la versión purga TODO lo cacheado en el `activate`. Se sube al
// desplegar cambios que el visitante deba ver sí o sí.
// v12 (2026-11-17): portada reordenada segun la maqueta, sin duplicados.
// v11 (2026-11-17): portada recompuesta segun la maqueta aprobada.
// v10 (2026-11-17): servicios con fotografía, pestañas de sección y onda.
// v9 (2026-11-17): rediseño aprobado por la gerencia —cabecera azul, velo
// del héroe, banda de servicio y orientador—. Se sube la versión para que
// quien ya había visitado el sitio no siga sirviéndose la hoja de estilos y
// los scripts anteriores desde su propia caché.
var VERSION = 'hrpl-v12';
var CASCARON = [
    '/desconectado.html',
    '/build/app.css',
    '/js/sitio.js',
    '/js/filtro.js',
    '/js/consentimiento.js',
    '/iconos/icono-192.png',
    '/manifest.webmanifest'
];

self.addEventListener('install', function (evento) {
    evento.waitUntil(
        caches.open(VERSION).then(function (cache) {
            return cache.addAll(CASCARON);
        }).then(function () {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function (evento) {
    evento.waitUntil(
        caches.keys().then(function (claves) {
            return Promise.all(claves.filter(function (clave) {
                return clave !== VERSION;
            }).map(function (clave) {
                return caches.delete(clave);
            }));
        }).then(function () {
            return self.clients.claim();
        })
    );
});

function esEstatico(url) {
    return url.pathname.indexOf('/build/') === 0
        || url.pathname.indexOf('/js/') === 0
        || url.pathname.indexOf('/iconos/') === 0
        || url.pathname === '/manifest.webmanifest';
}

self.addEventListener('fetch', function (evento) {
    var peticion = evento.request;
    var url = new URL(peticion.url);

    if (peticion.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (url.pathname.indexOf('/admin') === 0) {
        return;
    }

    if (esEstatico(url)) {
        evento.respondWith(
            caches.match(peticion).then(function (respuesta) {
                return respuesta || fetch(peticion).then(function (deRed) {
                    var copia = deRed.clone();
                    caches.open(VERSION).then(function (cache) {
                        cache.put(peticion, copia);
                    });
                    return deRed;
                });
            })
        );
        return;
    }

    if (peticion.mode === 'navigate') {
        evento.respondWith(
            fetch(peticion).then(function (deRed) {
                if (deRed.ok) {
                    var copia = deRed.clone();
                    caches.open(VERSION).then(function (cache) {
                        cache.put(peticion, copia);
                    });
                }
                return deRed;
            }).catch(function () {
                return caches.match(peticion).then(function (enCache) {
                    return enCache || caches.match('/desconectado.html');
                });
            })
        );
    }
});

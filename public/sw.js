// Service worker del sitio público (Fase 7).
//
// Estrategia deliberadamente conservadora para un sitio institucional:
//  - Estáticos (css, js, iconos, fuentes): caché primero, son versionados.
//  - Navegaciones: red primero; si no hay conexión, la copia en caché de esa
//    página o el cascarón /desconectado.html.
//  - JAMÁS se cachea el panel (/admin) ni respuestas no-GET: allí viven
//    sesiones y datos personales.
'use strict';

var VERSION = 'hrpl-v1';
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

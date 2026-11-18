<?php

declare(strict_types=1);

/**
 * Cambia los marcadores genéricos por fotografía real en noticias y campañas.
 *
 * Las dos secciones se sembraron con un `portada-*.jpg`: un rectángulo azul
 * con un icono tenue, que en pantalla se ve como un hueco de color. El caso
 * de las noticias arrastraba además a «Nuestras instalaciones» de la portada,
 * que toma sus imágenes de las mismas noticias.
 *
 * La fotografía real ya está en la mediateca desde el Portafolio de Servicios
 * 2026. Esto la reparte: primero por palabra clave del titular —una noticia de
 * urgencias se lleva la foto de urgencias— y lo que no casa, por turno, para
 * que no salga la misma foto dos veces seguidas.
 *
 * Es idempotente y conservador: solo toca las filas cuya imagen actual sea un
 * marcador. Lo que el hospital haya subido a mano se queda como está, así que
 * se puede volver a ejecutar sin miedo tras cada despliegue.
 */

$config = require __DIR__ . '/../../config/migraciones-bd.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
    $config['user'],
    $config['password'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

/** Palabra del titular => fragmento del nombre de la fotografía. */
const TEMAS = [
    'urgencia' => 'urgencias-24-horas',
    'vacun' => 'consulta-externa',
    'cirug' => 'cirugia',
    'quirúrg' => 'cirugia',
    'parto' => 'atencion-del-parto',
    'lactancia' => 'banco-de-leche-humana',
    'leche' => 'banco-de-leche-humana',
    'canguro' => 'programa-madre-canguro',
    'mental' => 'atencion-en-salud-mental',
    'imagen' => 'imagenologia',
    'resonancia' => 'resonancia-magnetica',
    'ecograf' => 'ecografia-obstetrica-3d-y-4d',
    'ambulancia' => 'transporte-asistencial',
    'traslado' => 'transporte-asistencial',
    'hospitaliza' => 'hospitalizacion',
    'cita' => 'consulta-externa',
    'consulta' => 'consulta-externa',
    'cardiovascular' => 'consulta-externa',
    'docencia' => 'pagina-docencia',
    'capacita' => 'pagina-docencia',
    'formación' => 'pagina-docencia',
    'talento humano' => 'pagina-docencia',
];

/** Las dos tablas que publican con imagen de portada. */
const TABLAS = ['noticias', 'campanas'];

// Catálogo de fotografía real: los webp del Portafolio.
$fotos = [];

foreach ($pdo->query('SELECT id, nombre_original FROM archivos WHERE nombre_original LIKE "%.webp" ORDER BY id') as $fila) {
    $fotos[(int) $fila['id']] = (string) $fila['nombre_original'];
}

if ($fotos === []) {
    exit("No hay fotografía del Portafolio en la mediateca. Nada que repartir.\n");
}

$idsFotos = array_keys($fotos);

/** Devuelve el id de la foto que corresponde al titular, o null si ninguna. */
$porTitular = static function (string $titulo) use ($fotos): ?int {
    $titulo = mb_strtolower($titulo);

    // De la palabra más larga a la más corta, y no en el orden del mapa:
    // «capacitación» CONTIENE «cita», así que con el orden literal el Plan
    // de Capacitación se llevaba la foto de consulta externa. La clave más
    // específica tiene que ganar siempre a la que es su propio trozo.
    $temas = TEMAS;
    uksort($temas, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

    foreach ($temas as $palabra => $fragmento) {
        if (!str_contains($titulo, $palabra)) {
            continue;
        }

        foreach ($fotos as $id => $nombre) {
            if (str_contains($nombre, $fragmento)) {
                return $id;
            }
        }
    }

    return null;
};

// El turno es compartido entre tablas: así campañas no repite las mismas
// fotos con las que acaba de empezar noticias.
$turno = 0;
$total = 0;

foreach (TABLAS as $tabla) {
    $pendientes = $pdo->query(
        "SELECT t.id, t.titulo
           FROM {$tabla} t
           JOIN archivos a ON a.id = t.imagen_id
          WHERE a.nombre_original LIKE 'portada-%'
          ORDER BY t.id"
    )->fetchAll(PDO::FETCH_ASSOC);

    if ($pendientes === []) {
        printf("%s: ya tienen fotografía real, nada que repartir.%s", $tabla, PHP_EOL);

        continue;
    }

    printf("--- %s ---%s", $tabla, PHP_EOL);

    $asignar = $pdo->prepare("UPDATE {$tabla} SET imagen_id = ? WHERE id = ?");
    $porTema = 0;
    $porTurno = 0;

    foreach ($pendientes as $fila) {
        $elegida = $porTitular($fila['titulo']);

        if ($elegida === null) {
            $elegida = $idsFotos[$turno % count($idsFotos)];
            $turno++;
            $porTurno++;
        } else {
            $porTema++;
        }

        $asignar->execute([$elegida, $fila['id']]);
        printf('  #%-3s %-44s -> %s%s', $fila['id'], mb_substr($fila['titulo'], 0, 42), $fotos[$elegida], PHP_EOL);
    }

    printf('  %d actualizadas (%d por tema, %d por reparto)%s', count($pendientes), $porTema, $porTurno, PHP_EOL);
    $total += count($pendientes);
}

printf('%sTotal: %d filas con fotografía real.%s', PHP_EOL, $total, PHP_EOL);

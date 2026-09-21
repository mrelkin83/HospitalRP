<?php

declare(strict_types=1);

namespace App\Modules\Sistema\Service;

/**
 * Los permisos que el sitio reconoce, agrupados como se le presentan al
 * administrador.
 *
 * Es una constante de código y no una tabla a propósito: un permiso existe
 * porque una ruta lo exige (`_permiso` en config/rutas.php), así que
 * inventarlo desde el panel no daría acceso a nada. Lo que sí se administra
 * desde el panel —y es lo que manda la regla de oro #2— es qué rol tiene
 * cuáles.
 *
 * Al añadir un módulo nuevo con su `_permiso`, se añade aquí: si no, el rol
 * podrá tenerlo solo escribiendo JSON a mano, que es justo lo que este panel
 * viene a evitar.
 */
final readonly class CatalogoPermisos
{
    /**
     * grupo => [etiqueta del grupo, permiso => descripción]
     *
     * @var array<string, array{titulo: string, permisos: array<string, string>}>
     */
    private const array GRUPOS = [
        'contenido' => [
            'titulo' => 'Contenido',
            'permisos' => [
                'contenido.ver' => 'Ver noticias, páginas, campañas, avisos, FAQ y categorías',
                'contenido.editar' => 'Crear y editar contenido, subir medios y enviarlo a revisión',
                // Lo exigen NoticiasController y CampanasController para aprobar
                // o publicar. Faltaba en este catálogo, así que no había forma
                // de dárselo a un rol desde el panel: o eras administrador con
                // «*», o nadie podía publicar. Separado de `editar` a propósito:
                // el flujo editorial de CLAUDE.md §6 quiere que quien redacta no
                // sea necesariamente quien aprueba.
                'contenido.publicar' => 'Aprobar y publicar contenido, o retirarlo del sitio',
            ],
        ],
        'documentos' => [
            'titulo' => 'Gestión documental',
            'permisos' => [
                'documentos.ver' => 'Consultar el listado de documentos',
                'documentos.editar' => 'Cargar documentos y nuevas versiones',
                'documentos.publicar' => 'Publicar o retirar un documento del sitio',
            ],
        ],
        'tramites' => [
            'titulo' => 'Trámites y PQRSDF',
            'permisos' => [
                'tramites.gestionar' => 'Atender la bandeja: asignar, responder y cambiar estados',
                'tramites.configurar' => 'Definir tipos de trámite y sus campos',
            ],
        ],
        'cuentas' => [
            'titulo' => 'Cuentas de cobro',
            'permisos' => [
                // La administración completa del módulo: contratos,
                // contratistas, supervisores y parámetros. El permiso del
                // supervisor (revisar y aprobar cuentas) llegará con su
                // bandeja en la fase 3 del módulo.
                'cuentas.administrar' => 'Administrar contratos, contratistas, supervisores y parámetros de cuentas de cobro',
                // La bandeja del supervisor: revisar, aprobar con firma o
                // devolver las cuentas de SUS contratos. Separado de
                // `administrar` a propósito: quien supervisa no configura.
                'cuentas.supervisar' => 'Revisar y aprobar cuentas de cobro de los contratos que supervisa',
                // La bandeja del equipo revisor: la verificación previa que
                // firma la cuenta antes de que llegue al supervisor. Quien
                // solo revisa no ve nada más del panel.
                'cuentas.revisar' => 'Verificar y firmar la revisión previa de las cuentas de cobro enviadas',
            ],
        ],
        'transparencia' => [
            'titulo' => 'Transparencia y datos abiertos',
            'permisos' => [
                'transparencia.ver' => 'Consultar el menú de transparencia y los datasets',
                'transparencia.editar' => 'Editar secciones ITA, ítems y datos abiertos',
            ],
        ],
        'participacion' => [
            'titulo' => 'Participación y difusión',
            'permisos' => [
                'convocatorias.ver' => 'Consultar convocatorias',
                'convocatorias.editar' => 'Publicar y cerrar convocatorias',
                'eventos.ver' => 'Consultar la agenda de eventos',
                'eventos.editar' => 'Publicar y editar eventos',
                'encuestas.ver' => 'Consultar encuestas y sus resultados',
                'encuestas.editar' => 'Crear encuestas y sus preguntas',
                'indicadores.ver' => 'Consultar los indicadores estadísticos',
                'indicadores.editar' => 'Cargar y publicar indicadores',
            ],
        ],
        'directorio' => [
            'titulo' => 'Directorio institucional',
            'permisos' => [
                'directorio.ver' => 'Consultar sedes, servicios, especialidades y profesionales',
                'directorio.editar' => 'Editar sedes, servicios, especialidades, profesionales y servidores públicos',
            ],
        ],
        'cumplimiento' => [
            'titulo' => 'Cumplimiento legal',
            'permisos' => [
                'cumplimiento.politicas' => 'Editar y publicar las políticas del sitio (Res. 1519)',
            ],
        ],
        'sistema' => [
            'titulo' => 'Sistema',
            'permisos' => [
                'sistema.dependencias' => 'Administrar las dependencias de la entidad',
                'sistema.menus' => 'Reorganizar los menús del sitio público',
                'sistema.ia' => 'Configurar el asistente de inteligencia artificial',
                'sistema.configuracion' => 'Cambiar los datos institucionales y ajustes del sitio',
                'sistema.usuarios' => 'Crear usuarios, restablecer contraseñas y desactivar cuentas',
                'sistema.roles' => 'Definir roles y repartir permisos',
                'sistema.auditoria' => 'Consultar el registro de auditoría',
                'sistema.redirecciones' => 'Administrar las redirecciones de URL antiguas',
            ],
        ],
    ];

    /**
     * @return array<string, array{titulo: string, permisos: array<string, string>}>
     */
    public function grupos(): array
    {
        return self::GRUPOS;
    }

    /**
     * @return list<string>
     */
    public function todos(): array
    {
        $permisos = [];

        foreach (self::GRUPOS as $grupo) {
            foreach (array_keys($grupo['permisos']) as $permiso) {
                $permisos[] = $permiso;
            }
        }

        return $permisos;
    }

    public function existe(string $permiso): bool
    {
        return in_array($permiso, $this->todos(), true);
    }

    /**
     * Descarta lo que no esté en el catálogo y quita repetidos.
     *
     * El comodín `*` se conserva porque es el rol administrador: se concede
     * desde aquí para no obligar a marcar veintitantas casillas, y porque un
     * permiso futuro debe llegarle solo.
     *
     * @param list<string> $permisos
     * @return list<string>
     */
    public function depurar(array $permisos): array
    {
        $validos = [];

        foreach ($permisos as $permiso) {
            if ($permiso === '*' || $this->existe($permiso)) {
                $validos[] = $permiso;
            }
        }

        return array_values(array_unique($validos));
    }

    /**
     * Texto legible de un permiso para la auditoría y las pantallas.
     */
    public function describir(string $permiso): string
    {
        if ($permiso === '*') {
            return 'Acceso total al panel';
        }

        foreach (self::GRUPOS as $grupo) {
            if (isset($grupo['permisos'][$permiso])) {
                return $grupo['permisos'][$permiso];
            }
        }

        return $permiso;
    }
}

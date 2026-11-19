<?php

/*
|--------------------------------------------------------------------------
| Configuración de SIGER / HelpDesk 2.0
|--------------------------------------------------------------------------
|
| Menú principal. Cada elemento se muestra solo si el usuario tiene alguno
| de los permisos indicados en "permisos" o alguno de los roles de "roles".
| Si la ruta todavía no existe, el elemento aparece deshabilitado como
| "Próximamente"; basta con registrar la ruta en la fase correspondiente.
|
| Los módulos de administración (catálogos, usuarios, auditoría) aún no
| tienen permisos propios: se limitan por rol hasta que se aprueben sus
| identificadores (fases 1 y 7).
|
*/

return [

    'menu' => [
        [
            'etiqueta' => 'Inicio',
            'ruta' => 'dashboard',
            'activa' => 'dashboard',
            'descripcion' => 'Resumen general del sistema.',
            'color' => 'siger',
            'svg_path' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        ],
        [
            'etiqueta' => 'Expedientes',
            'ruta' => 'expedientes.index',
            'activa' => 'expedientes.*',
            'permisos' => ['servicios.ver'],
            'descripcion' => 'Recepción y consulta de oficios y sus partidas.',
            'fase' => 2,
            'color' => 'emerald',
            'svg_path' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        ],
        [
            'etiqueta' => 'Partidas',
            'ruta' => 'partidas.index',
            'activa' => 'partidas.*',
            'permisos' => ['partidas.ver'],
            'descripcion' => 'Turnado, folios, asignación y atención de partidas.',
            'fase' => 3,
            'color' => 'cyan',
            'svg_path' => 'M4 6h16M4 12h16M4 18h16M8 4v4m8 2v4M8 16v4',
        ],
        [
            'etiqueta' => 'Materiales',
            'ruta' => 'solicitudes-materiales.index',
            'activa' => 'solicitudes-materiales.*',
            'permisos' => ['solicitudes_materiales.ver'],
            'descripcion' => 'Solicitudes de materiales y entregas de Adquisiciones.',
            'fase' => 5,
            'color' => 'amber',
            'svg_path' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        ],
    ],

    'menu_administracion' => [
        [
            'etiqueta' => 'Catálogos',
            'ruta' => 'catalogos.index',
            'activa' => 'catalogos.*',
            'roles' => ['Super usuario', 'Administrador'],
            'descripcion' => 'Áreas, departamentos, tipos de servicio y operativos.',
            'fase' => 1,
            'color' => 'indigo',
            'svg_path' => 'M4 6h16M4 10h16M4 14h16M4 18h16',
        ],
        [
            'etiqueta' => 'Usuarios',
            'ruta' => 'usuarios.index',
            'activa' => 'usuarios.*',
            'roles' => ['Super usuario', 'Administrador'],
            'descripcion' => 'Cuentas, roles y departamento de adscripción.',
            'fase' => 1,
            'color' => 'violet',
            'svg_path' => 'M17 20h5v-2a4 4 0 00-4-4h-1m-4 6H3v-2a4 4 0 014-4h3m3-4a4 4 0 100-8 4 4 0 000 8zm6 0a3 3 0 10-6 0 3 3 0 006 0z',
        ],
        [
            'etiqueta' => 'Auditoría',
            'ruta' => 'auditoria.index',
            'activa' => 'auditoria.*',
            'roles' => ['Super usuario', 'Administrador'],
            'descripcion' => 'Bitácora de operaciones del sistema.',
            'fase' => 7,
            'color' => 'sky',
            'svg_path' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
    ],

];

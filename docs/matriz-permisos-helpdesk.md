# Matriz de permisos de HelpDesk 2.0

Estado: concesiones de la tabla aprobadas por el usuario.
`PermisosHelpDeskSeeder` aplica estas concesiones para el guard `web`, conserva
permisos ajenos a estos 20 identificadores y no modifica asignaciones de usuarios.
Las celdas P siguen sin concederse. Las Policies aplican los permisos por registro,
departamento, asignación y estado; no existe bypass para Super usuario.
Base: RF-001, RF-007, RF-014, RF-016, RF-017, RF-025 y RF-026 del documento v1.3.

## Alcances

- **G**: alcance administrativo general, sujeto a permisos y validaciones de cada operación.
- **D**: departamento de adscripción; para consultar un expediente debe existir al menos una partida de ese departamento.
- **A**: partidas con asignación operativa activa vinculada al usuario mediante `cat_operativos.user_id`.
- **R**: recepción inicial y canalización por vía rápida, conforme al ámbito de recepción que se apruebe.
- **P**: decisión pendiente; no conceder automáticamente.
- **—**: no conceder por defecto.

Cada celda aprobada concede el permiso, sin excepciones a las reglas del RF.
Una cuenta inactiva no debe acceder. `departamento_id = NULL` no otorga alcance general.
No se propone un bypass automático de autorización para Super usuario.

## Matriz de los 20 permisos existentes

| Permiso | Super usuario | Administrador | Jefe | Recepción/Coordinador | Operativo |
|---|---|---|---|---|---|
| `servicios.ver` | G | G | D | R / D | A |
| `servicios.crear` | G | G | — | R | — |
| `partidas.ver` | G | G | D | R / D | A |
| `partidas.crear` | G | G | — | — | — |
| `partidas.turnar` | G | G | P | R, solo vía rápida | — |
| `partidas.returnar` | G | G | P | — | — |
| `partidas.cancelar` | — | G | D | — | — |
| `solicitudes_materiales.ver` | G | G | D | — | — |
| `solicitudes_materiales.crear` | — | — | D | — | — |
| `solicitudes_materiales.editar` | — | — | D | — | — |
| `solicitudes_materiales.enviar` | — | — | D | — | — |
| `solicitudes_materiales.revisar` | G | G | — | — | — |
| `solicitudes_materiales.solicitar_informacion` | G | G | — | — | — |
| `solicitudes_materiales.autorizar` | G | G | — | — | — |
| `solicitudes_materiales.rechazar` | G | G | — | — | — |
| `solicitudes_materiales.ver_historial` | G | G | D | — | — |
| `operativos_partida.asignar` | G | G | D | D | — |
| `operativos_partida.finalizar` | G | G | D | D | — |
| `documentos.ver` | G | G | D | R / D | A |
| `documentos.descargar` | G | G | D | R / D | A |

`partidas.crear` no es necesario para que Recepción registre un expediente:
la primera partida se crea automáticamente como parte de `servicios.crear`.
La partida adicional de materiales se crea mediante la autorización de RF-017;
no se concede al Jefe la creación directa de esa partida.

## Restricciones que deberán comprobar las Policies y los servicios

- Un Jefe no modifica partidas de otros departamentos. La consulta de la partida
  ADQ generada desde una solicitud propia necesita definir su alcance específico.
- Crear, editar y enviar solicitudes corresponde al Jefe de la partida original;
  deben respetarse autoría, estados y aclaraciones de RF-017. No puede autorizar
  su propia solicitud. Las facultades de Dirección requieren permisos específicos.
- RF-026 reserva la cancelación a Administrador o Jefe; Super usuario necesitaría
  además uno de esos roles y el permiso, sin saltarse las restricciones funcionales.
- Un Operativo no registra solicitudes formales ni se asigna o finaliza sus propias
  asignaciones por defecto. Al finalizar su asignación pierde el acceso derivado de ella.
- Documentos y reportes deben validar alcance en cada consulta o descarga.
- Los estados terminales, las devoluciones y la inmutabilidad histórica siguen
  aplicándose incluso a los usuarios con alcance general.
- La adscripción se mantiene exclusivamente en `users.departamento_id`, sin pivote
  de departamentos. No se concede acceso general a Coordinación por defecto.

## Aprobación y decisiones de alcance pendientes

1. Concesiones de la tabla aprobadas, incluidas las facultades operativas de
   Super usuario y Administrador. Esta aprobación no resuelve las celdas P.
2. Precisar si los Jefes podrán turnar y re-turnar, y a qué departamentos destino.
   RF-014 remite a destinos autorizados que RF-001 no enumera.
3. Precisar el alcance de Recepción sobre expedientes sin departamento y las
   facultades del rol combinado Recepción/Coordinador. Confirmar si Coordinación
   queda limitada a su adscripción o necesita excepciones de acceso.
4. Precisar la consulta del Jefe a partidas ADQ derivadas de sus solicitudes.

Los permisos de usuarios, catálogos, devoluciones, inicio de atención, conclusión,
evidencias, auditoría y reportes todavía no tienen identificadores en el seeder.
Se definirán con aprobación en el módulo correspondiente; esta tabla no cubre
todavía todas las operaciones del sistema ni debe utilizarse como matriz final.

## Departamentos iniciales

`DepartamentosHelpDeskSeeder` crea RM (Recursos Materiales), SG (Servicios Generales)
y ADQ (Adquisiciones) si no existen, buscándolos por `siglas`. Conserva nombres,
responsables, ubicaciones y estados de registros existentes. No asigna usuarios,
no genera folios y no crea cuentas de acceso.

## Uso de las Policies

Laravel descubre automáticamente las Policies de `app/Policies` por el nombre del
modelo. Los futuros controladores, componentes y acciones deben invocarlas en el
servidor mediante `Gate::authorize` o mecanismos equivalentes. Su existencia no
filtra listados ni protege rutas que todavía no las invoquen.

| Modelo | Abilities implementadas |
|---|---|
| `Servicio` | `view`, `create` |
| `Partida` | `view`, `create`, `turnar`, `returnar`, `cancelar` |
| `SolicitudMaterial` | `view`, `viewHistory`, `create`, `update`, `enviar`, `revisar`, `solicitarInformacion`, `autorizar`, `rechazar` |
| `OperativoPartida` | `create`, `finalizar` |
| `ServicioArchivo`, `ServicioAcuse` | `view`, `download` |
| `HistorialSolicitudMaterial` | `view` |

Ejemplos de contexto adicional requerido:

```php
Gate::authorize('create', [Partida::class, $servicio]);
Gate::authorize('turnar', [$partida, $departamentoDestino]);
Gate::authorize('create', [SolicitudMaterial::class, $partidaOriginal]);
Gate::authorize('create', [OperativoPartida::class, $partida, $operativo]);
```

Recepción puede registrar expedientes. La consulta de expedientes sin departamento
sigue pendiente y se deniega. Su turno por vía rápida exige departamento propuesto
igual a su adscripción y destino igual a esa adscripción. No se habilitan excepciones
interdepartamentales. Un Jefe no obtiene acceso a la partida ADQ por ser su origen.
Dirección consulta solicitudes enviadas y sus etapas posteriores, no borradores.

No se conceden abilities de eliminación ni de modificación de históricos. Las
Policies no sustituyen validaciones de datos, filtrado de consultas ni transacciones:
los futuros servicios deben autorizar y validar nuevamente bajo bloqueo antes de
modificar registros. La suspensión de sesiones de cuentas inactivas queda en el
módulo de autenticación; las Policies ya deniegan sus operaciones protegidas.

Prueba específica en Docker, con la conexión MySQL configurada y sus migraciones
ya aplicadas: `docker compose exec app vendor/bin/phpunit -c tests/phpunit-policies.xml`.
Esta configuración usa transacciones con rollback, caché en memoria y datos de
prueba; no ejecuta migraciones ni utiliza `RefreshDatabase`.

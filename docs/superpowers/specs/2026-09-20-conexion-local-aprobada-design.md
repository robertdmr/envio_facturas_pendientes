# Respaldo a conexión local de `puntopan` con aprobación explícita

Fecha: 2026-09-20

## Resumen

Cuando la conexión al servidor **remoto** de `puntopan` no está disponible, la
aplicación **no** cae al instante: muestra una pantalla de confirmación y, solo
si el operador aprueba, pasa a leer la **copia local** de la base
(`127.0.0.1`/`puntopan`). La aprobación caduca por tiempo (TTL configurable) y
queda visible en la interfaz mientras esté vigente.

Motivación: `PUNTOPAN_DB_HOST=10.242.217.171` solo es alcanzable desde la red de
la oficina. Fuera de ella, la app es inservible. Existe una copia local de
`puntopan` en el mismo MySQL que `puntopan_app` (`127.0.0.1`, confirmado:
`SHOW DATABASES` incluye `puntopan` y `puntopan_app`).

## Decisión de diseño

**Nunca** se usa la copia local sin consentimiento. Cambiar de origen de datos en
silencio es peligroso: la copia local puede estar desactualizada y la app además
escribe en `fecdc` (única excepción autorizada a la regla de solo lectura). El
operador debe saber contra qué base está trabajando.

## Máquina de estados

Clase única `app/Services/PuntopanConexion.php`:

| Estado | Condición | Acción |
|---|---|---|
| `REMOTE_OK` | sondeo remoto OK | usa la conexión `puntopan` (remota) |
| `LOCAL_APROBADO` | aprobación vigente en sesión + local OK | reescribe la config de la conexión `puntopan` con la copia local |
| `REQUIERE_APROBACION` | remoto caído, sin aprobación vigente | redirige a la pantalla de confirmación |
| `NO_DISPONIBLE` | remoto caído y local también | 503 con mensaje claro |

### Sondeo

- `PUNTOPAN_PROBE_TIMEOUT` (def. 2 s): `fsockopen` TCP contra el host/puerto
  **remoto**. Es la comprobación barata; no abre PDO ni autentica.
- La disponibilidad de la **copia local** se comprueba con una conexión real
  (es local, así que es barata) porque no basta con que el puerto esté abierto:
  la base debe existir. Se descartó el sondeo TCP para el local.
- Resultado cacheado `PUNTOPAN_PROBE_TTL` (def. 30 s) en `Cache` (en tests,
  `array`; en producción, la base).
- La conexión remota añade `PDO::ATTR_TIMEOUT` para que una consulta real falle
  rápido en vez de colgarse hasta el timeout del sistema operativo.
- `olvidarSondeo()` invalida la caché (lo usa la acción "Reintentar").

### Aprobación

- Se guarda en sesión: `puntopan.local_aprobado_hasta` (timestamp Unix).
- Vigencia: `PUNTOPAN_LOCAL_APPROVAL_TTL` (def. 3600 s).
- Al expirar, se reintenta el remoto y, si sigue caído, se vuelve a preguntar.
- Interruptor maestro `PUNTOPAN_ALLOW_LOCAL` (def. `true`): en `false` nunca se
  ofrece la copia local y se responde 503 directamente.

## Componentes

### `config/database.php`

- `puntopan_remoto`: definición **intacta** del servidor remoto. Nunca se
  reescribe, así que los sondeos y el regreso al remoto siempre tienen datos
  fiables (incluso en procesos de larga vida como `queue:work`).
- `puntopan`: la conexión **efectiva** que usan los modelos y
  `DB::connection('puntopan')`. Arranca igual al remoto y, cuando hace falta, se
  reescribe con la copia local.
- `puntopan_fallback`: reutiliza `DB_HOST`, `DB_PORT`, `DB_USERNAME`,
  `DB_PASSWORD` (el MySQL local de la app) y usa `PUNTOPAN_LOCAL_DB_DATABASE`
  (def. `puntopan`) como base.
- Clave `database.puntopan_respaldo` con los ajustes: `permitido`, `forzado`,
  `ttl_aprobacion`, `ttl_sondeo`, `timeout_sondeo`, `base_local`.

### `app/Services/PuntopanConexion.php`

Responsabilidades (una sola clase, sin acoplarse a HTTP):

- `estado(): string` — devuelve `REMOTE_OK` | `LOCAL_APROBADO` |
  `REQUIERE_APROBACION` | `NO_DISPONIBLE`.
- `remotoDisponible(): bool` / `localDisponible(): bool` — sondeos cacheados.
- `aprobar(): void` / `revocar(): void` / `aprobadoHasta(): ?Carbon`.
- `aplicarLocal(): void` — copia `database.connections.puntopan_fallback` sobre
  `database.connections.puntopan` y purga la conexión ya resuelta
  (`DB::purge('puntopan')`), para que los modelos y `DB::connection('puntopan')`
  sigan funcionando sin cambios.
- `olvidarSondeo(): void`.
- Registra `Log::warning` **una sola vez** por ventana de sondeo al activar la
  copia local.

### `app/Http/Middleware/AsegurarConexionPuntopan.php`

Se aplica a todas las rutas que leen `puntopan`. Antes de ejecutar el
controlador decide según el estado. Evita try/catch repetidos en cada acción y
guarda la URL pretendida en sesión (`puntopan.url_pretendida`) para volver tras
aprobar.

Rutas excluidas: la propia pantalla de confirmación y sus acciones.

### `app/Http/Controllers/PuntopanConexionController.php`

- `GET /puntopan/conexion-local` → `puntopan.conexion-local.editar`: pantalla de
  confirmación (host remoto, motivo del fallo, host local, TTL de la aprobación).
- `POST /puntopan/conexion-local` → `puntopan.conexion-local.aprobar`: aprueba y
  redirige a la URL pretendida (o al listado).
- `POST /puntopan/conexion-local/reintentar` → `puntopan.conexion-local.reintentar`:
  revoca la aprobación, invalida el sondeo y reintenta el remoto.
- `POST /puntopan/conexion-local/volver` → `puntopan.conexion-local.volver`:
  revoca la aprobación sin salir de la pantalla (vuelve al remoto si revive).

### Vistas

- `resources/views/puntopan/conexion-local.blade.php`: pantalla con el aviso y
  los botones **Usar copia local** / **Reintentar servidor remoto**.
- Banner en `resources/views/layouts/app.blade.php` cuando hay aprobación
  vigente: *"Usando copia local de `puntopan` (aprobada hasta HH:MM)"* con botón
  **Volver al servidor remoto**. El layout recibe `$puntopanConexion` por un
  `View::share` desde `AppServiceProvider`.

### Defensa en profundidad

Si una petición falla con `Illuminate\Database\QueryException` /
`PDOException` de conexión contra `puntopan` y **no** hay aprobación vigente, el
handler de excepciones (`bootstrap/app.php`) redirige a la pantalla de
confirmación en lugar de mostrar un 500. Cubre el caso "el remoto se cayó justo
después del sondeo".

### Contextos sin sesión (artisan, `EnviarPendienteJob`, cron)

No pueden pedir aprobación. Si el remoto está caído y no hay aprobación vigente,
**fallan con mensaje explícito** y no tocan la copia local. Escape hatch para
desarrollo y tests: `PUNTOPAN_FORCE_LOCAL=true` (usa la copia local sin
preguntar).

## Variables de entorno

```
PUNTOPAN_LOCAL_DB_DATABASE=puntopan
PUNTOPAN_ALLOW_LOCAL=true
PUNTOPAN_FORCE_LOCAL=false
PUNTOPAN_PROBE_TTL=30
PUNTOPAN_PROBE_TIMEOUT=2
PUNTOPAN_LOCAL_APPROVAL_TTL=3600
```

## Tests

`tests/Feature/PuntopanConexionLocalTest.php` (sin `RefreshDatabase`, como el
resto de la suite). El sondeo del remoto se sustituye por un doble controlable
(`$this->app->instance(SondeadorTcp::class, ...)`); la copia local se comprueba
de verdad.

1. Remoto caído + ruta que lee `puntopan` → redirige a la pantalla de confirmación.
2. Aprobar → 200 y la conexión efectiva apunta a la copia local.
3. Aprobación caducada → vuelve a pedir aprobación.
4. Aprobar pero local caído → 503.
5. `PUNTOPAN_ALLOW_LOCAL=false` → 503 sin ofrecer la copia local.
6. `POST /puntopan/conexion-local/reintentar` con remoto vivo → vuelve al remoto.
7. `PuntopanConexion` con prober inyectado: estados `REMOTO_OK`,
   `REQUIERE_APROBACION`, `LOCAL_APROBADO`, `NO_DISPONIBLE`.
8. El banner aparece con aprobación vigente y desaparece al volver al remoto.
9. `EnviarPendienteJob` lanza excepción si no hay aprobación ni remoto.

### Suite completa sin la red de la oficina

Los tests existentes consultan la conexión `puntopan` directamente, así que el
middleware no basta para que corran en local: `tests/TestCase.php` aplica la
copia local en `setUp()` cuando `forzado` está activo, y `phpunit.xml` define
`PUNTOPAN_FORCE_LOCAL=true`. Con `false` la suite vuelve a probar contra el
remoto.

## Coste asumido

El primer request de cada ventana de `PROBE_TTL` paga el timeout del sondeo
(hasta ~2 s). El resto, instantáneo. Se acepta a cambio de que ninguna consulta
se cuelgue esperando a un host inalcanzable.

## Notas

- La regla de oro se mantiene: la copia local también es de solo lectura salvo la
  excepción autorizada de `fecdc`.
- Los `whereIn` de `consultaFiltrada()` ya evitan el `JOIN` cross-database, así
  que el cambio de origen de `puntopan` no rompe el listado.

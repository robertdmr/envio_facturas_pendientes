# Plan de implementación: respaldo a conexión local de `puntopan` con aprobación

**Goal:** Cuando el servidor remoto de `puntopan` no responde, pedir aprobación explícita al operador antes de usar la copia local, con vigencia por TTL y aviso visible en la UI.

**Architecture:** Una clase de estado (`PuntopanConexion`) sondea remoto y local (cacheado), decide entre cuatro estados y reescribe la configuración de la conexión `puntopan` cuando hay aprobación vigente en sesión. Un middleware aplicado a las rutas que leen `puntopan` intercepta la petición y redirige a una pantalla de confirmación. Ni modelos ni controladores cambian.

**Tech Stack:** Laravel 13, Blade + Tailwind, PHPUnit 12 (tests contra BD real, sin `RefreshDatabase`).

**Spec:** `docs/superpowers/specs/2026-09-20-conexion-local-aprobada-design.md`

---

### Task 1: Configuración de la conexión local

**Files:**
- Modify: `config/database.php` (bloque `connections.puntopan`, nuevo `puntopan_fallback`, nueva clave `puntopan_respaldo`)

- [ ] Añadir `puntopan_fallback` reutilizando `DB_*` y `PUNTOPAN_LOCAL_DB_DATABASE` (def. `puntopan`).
- [ ] Añadir `PDO::ATTR_TIMEOUT => timeout_sondeo` a la conexión remota para fallar rápido.
- [ ] Añadir la clave `puntopan_respaldo` (`permitido`, `forzado`, `ttl_aprobacion`, `ttl_sondeo`, `timeout_sondeo`, `base_local`).
- [ ] Verificar: `php artisan tinker --execute="print_r(config('database.connections.puntopan_fallback'));"`

### Task 2: Servicio `PuntopanConexion`

**Files:**
- Create: `app/Services/PuntopanConexion.php`
- Test: `tests/Unit/PuntopanConexionTest.php`

- [ ] Escribir test de `estado()` con prober inyectado: remoto OK → `REMOTE_OK`; remoto caído → `REQUIERE_APROBACION`; remoto y local caídos → `NO_DISPONIBLE`; `permitido=false` → `NO_DISPONIBLE`.
- [ ] Ejecutar y ver el fallo.
- [ ] Implementar `PuntopanConexion` (`estado`, `remotoDisponible`, `localDisponible`, `aprobar`, `revocar`, `aprobadoHasta`, `aplicarLocal`, `olvidarSondeo`).
- [ ] Ejecutar test: PASS.

### Task 3: Middleware

**Files:**
- Create: `app/Http/Middleware/AsegurarConexionPuntopan.php`
- Modify: `bootstrap/app.php` (alias `puntopan.conexion`)

- [ ] Lógica: `REMOTE_OK` → sigue; `LOCAL_APROBADO` → `aplicarLocal()` y sigue; `REQUIERE_APROBACION` → guarda URL pretendida y redirige a `puntopan.conexion-local.editar`; `NO_DISPONIBLE` → 503.
- [ ] Registrar alias en `bootstrap/app.php`.

### Task 4: Controlador y rutas

**Files:**
- Create: `app/Http/Controllers/PuntopanConexionController.php`
- Modify: `routes/web.php`

- [ ] Rutas: `GET/POST /puntopan/conexion-local`, `POST /puntopan/conexion-local/reintentar`, `POST /puntopan/conexion-local/volver`.
- [ ] `editar()` devuelve la vista con host remoto, motivo y TTL.
- [ ] `aprobar()` guarda aprobación y redirige a la URL pretendida.
- [ ] `reintentar()` revoca + `olvidarSondeo()` + redirige.
- [ ] `volver()` revoca y redirige al listado.
- [ ] Aplicar el middleware a las rutas existentes que leen `puntopan` (excepto las de conexión local).

### Task 5: Vistas

**Files:**
- Create: `resources/views/puntopan/conexion-local.blade.php`
- Modify: `resources/views/layouts/app.blade.php`
- Modify: `app/Providers/AppServiceProvider.php` (`View::share`)

- [ ] Pantalla de confirmación con aviso, host remoto/local y botones Usar copia local / Reintentar.
- [ ] Banner en el layout cuando `PuntopanConexion::aprobadoHasta()` esté vigente.

### Task 6: Defensa en profundidad

**Files:**
- Modify: `bootstrap/app.php`

- [ ] En `withExceptions`, si la excepción es de conexión a `puntopan` y no hay aprobación vigente → redirigir a la pantalla de confirmación.

### Task 7: Variables de entorno

**Files:**
- Modify: `.env`, `.env.example`, `phpunit.xml`, `tests/TestCase.php`

- [ ] Añadir `PUNTOPAN_LOCAL_DB_DATABASE`, `PUNTOPAN_ALLOW_LOCAL`, `PUNTOPAN_FORCE_LOCAL`, `PUNTOPAN_PROBE_TTL`, `PUNTOPAN_PROBE_TIMEOUT`, `PUNTOPAN_LOCAL_APPROVAL_TTL`.
- [ ] `phpunit.xml` define `PUNTOPAN_FORCE_LOCAL=true` y `tests/TestCase.php` aplica la copia local en `setUp()`, porque los tests existentes consultan `DB::connection('puntopan')` fuera del ciclo de request.

### Task 8: Tests de feature

**Files:**
- Create: `tests/Feature/PuntopanConexionLocalTest.php`
- Create: `tests/Support/SondeadorTcpFalso.php`

- [ ] Casos: redirección a confirmación con remoto caído; aprobar → 200 con copia local; TTL expirado → vuelve a preguntar; aprobar con local caído → 503; `PUNTOPAN_ALLOW_LOCAL=false` → 503; reintentar con remoto vivo → vuelve al remoto; el job falla sin aprobación.
- [ ] Ejecutar la suite completa.

---

## Verificación final

- [ ] `php artisan test` en verde (los tests que dependen del remoto se ejecutan con `PUNTOPAN_FORCE_LOCAL=true`).
- [ ] `php artisan config:clear` y prueba manual en `https://facturas-puntopan.test` (remoto inalcanzable desde esta red) → debe aparecer la pantalla de confirmación.
- [ ] Comprobar que el banner aparece tras aprobar y desaparece con "Volver al servidor remoto".

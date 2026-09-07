# Filtro de estado de envío (Enviado / Pendiente / Sin estado) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Agregar al listado de facturas un filtro de Estado (Enviado / Pendiente / Sin estado) y repartir el form de filtros en dos líneas.

**Architecture:** La query del listado corre contra la BD `puntopan`; `facturas_pendientes` vive en la BD de la app (`puntopan_app`, conexión default). En `consultaFiltrada()` (usada por `index()` y `exportar()`) se agrega, SOLO cuando el filtro `estado` viene lleno, un `leftJoin` cross-database hacia `facturas_pendientes` calificado con el nombre de BD derivado en runtime de la conexión default. El controlador pasa `$estados` al view y el Blade agrega un `<select name="estado">`, distribuyendo los campos en dos líneas de tres con `xl:col-span-2`.

**Tech Stack:** Laravel 13 (PHP 8.3), Blade + Tailwind, MySQL (`puntopan` solo lectura + `puntopan_app` app, mismo servidor), PHPUnit 12 con tests de feature sobre BD real.

**Spec:** `docs/superpowers/specs/2026-09-06-filtro-estado-envio-design.md`

---

## File Structure

- Modify: `app/Http/Controllers/FacturaController.php` — `consultaFiltrada()` (leftJoin cross-BD condicional + filtro `estado`) y pasar `$estados` en `index()`.
- Modify: `resources/views/facturas/index.blade.php` — dos líneas de filtros + select "Estado".
- Modify: `tests/Feature/FacturaListTest.php` — test de orden actualizado + tests de filtro por estado.
- Modify: `tests/Feature/FacturaExportTest.php` — test de exportación con filtro de estado.

Contexto del código actual:
- `consultaFiltrada()` (FacturaController.php ~159-198): `leftJoinSub` de `DetalleFactura` → `leftJoin clientes` → filtros `q`, `cliente`, `tipo`, `desde`, `hasta` → `select` → orden. Corre contra la conexión `puntopan` (BD `puntopan`). La usan `index()` (paginado) y `exportar()` (CSV).
- `index()` pasa al view: `facturas`, `tipos` (`['Contado','Credito']`), `pendientes`.
- `facturas_pendientes` está en la BD de la app (`puntopan_app`, conexión default): columnas `nrofactura` (único), `enviado` (bool), `respuesta`. Estado Enviado = `enviado=1`; Pendiente = `enviado=0`; Sin estado = sin fila. Nombre de la BD: `FacturaPendiente::query()->getConnection()->getDatabaseName()` (NO hardcodear).
- Form en `index.blade.php`: una línea, grid `xl:grid-cols-6`, orden actual q(2 cols) → tipo → desde → hasta → cliente.

---

### Task 1: Tests RED (orden + filtros de estado)

**Files:**
- Modify: `tests/Feature/FacturaListTest.php`
- Modify: `tests/Feature/FacturaExportTest.php`

- [ ] **Step 1: Agregar imports y helpers a `FacturaListTest`**

En `tests/Feature/FacturaListTest.php`, el `use` actual es:

```php
use App\Models\Factura;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
```

Reemplazarlo por (agrega el import de `FacturaPendiente`):

```php
use App\Models\Factura;
use App\Models\FacturaPendiente;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
```

Agregar al final de la clase `FacturaListTest` (antes de la llave de cierre) estos helpers privados:

```php
    private function bdPendientes(): string
    {
        return (string) FacturaPendiente::query()->getConnection()->getDatabaseName();
    }

    private function nroSinEstado(): ?string
    {
        return DB::connection('puntopan')->table('facturas')
            ->leftJoin($this->bdPendientes().'.facturas_pendientes as fp', 'fp.nrofactura', '=', 'facturas.NroFactura')
            ->whereNull('fp.nrofactura')
            ->orderByDesc('facturas.FechaFactura')
            ->orderByDesc('facturas.NroFactura')
            ->value('facturas.NroFactura');
    }

    private function nroEnviado(): ?string
    {
        return DB::connection('puntopan')->table('facturas')
            ->join($this->bdPendientes().'.facturas_pendientes as fp', 'fp.nrofactura', '=', 'facturas.NroFactura')
            ->where('fp.enviado', true)
            ->orderByDesc('facturas.FechaFactura')
            ->orderByDesc('facturas.NroFactura')
            ->value('facturas.NroFactura');
    }
```

- [ ] **Step 2: Actualizar el test de orden de campos**

En `tests/Feature/FacturaListTest.php`, reemplazar el método `test_index_shows_filter_fields_in_expected_order` por:

```php
    public function test_index_shows_filter_fields_in_expected_order(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder([
                'name="q"',
                'name="tipo"',
                'name="cliente"',
                'name="desde"',
                'name="hasta"',
                'name="estado"',
            ], false);
    }
```

- [ ] **Step 3: Agregar tests de filtro por estado a `FacturaListTest`**

Agregar estos dos métodos a `FacturaListTest`:

```php
    public function test_index_filters_by_estado_enviado(): void
    {
        $enviado = $this->nroEnviado();
        $sinEstado = $this->nroSinEstado();

        $this->assertNotNull($sinEstado);
        if ($enviado === null) {
            $this->markTestSkipped('No hay facturas enviadas en la BD puntopan.');
        }

        $this->get('/?estado=enviado')
            ->assertOk()
            ->assertSee($enviado)
            ->assertDontSee($sinEstado);
    }

    public function test_index_filters_by_estado_sin_estado(): void
    {
        $sinEstado = $this->nroSinEstado();

        $this->assertNotNull($sinEstado);

        $conRegistro = DB::connection('puntopan')->table('facturas')
            ->join($this->bdPendientes().'.facturas_pendientes as fp', 'fp.nrofactura', '=', 'facturas.NroFactura')
            ->orderByDesc('facturas.FechaFactura')
            ->orderByDesc('facturas.NroFactura')
            ->value('facturas.NroFactura');

        if ($conRegistro === null) {
            $this->markTestSkipped('No hay facturas con registro en facturas_pendientes en la BD de la app.');
        }

        $this->get('/?estado=sin')
            ->assertOk()
            ->assertSee($sinEstado)
            ->assertDontSee($conRegistro);
    }
```

- [ ] **Step 4: Agregar helper y test de exportación a `FacturaExportTest`**

En `tests/Feature/FacturaExportTest.php`, el `use` actual:

```php
use App\Models\DetalleFactura;
use App\Models\Factura;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
```

Reemplazarlo por (agrega `FacturaPendiente`):

```php
use App\Models\DetalleFactura;
use App\Models\Factura;
use App\Models\FacturaPendiente;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
```

Y agregar estos helpers/métodos a la clase:

```php
    private function bdPendientes(): string
    {
        return (string) FacturaPendiente::query()->getConnection()->getDatabaseName();
    }

    private function nroConRegistro(): ?string
    {
        return DB::connection('puntopan')->table('facturas')
            ->join($this->bdPendientes().'.facturas_pendientes as fp', 'fp.nrofactura', '=', 'facturas.NroFactura')
            ->orderByDesc('facturas.FechaFactura')
            ->orderByDesc('facturas.NroFactura')
            ->value('facturas.NroFactura');
    }

    public function test_export_respects_estado_filter(): void
    {
        $sinEstado = DB::connection('puntopan')->table('facturas')
            ->leftJoin($this->bdPendientes().'.facturas_pendientes as fp', 'fp.nrofactura', '=', 'facturas.NroFactura')
            ->whereNull('fp.nrofactura')
            ->orderByDesc('facturas.FechaFactura')
            ->orderByDesc('facturas.NroFactura')
            ->value('facturas.NroFactura');
        $conRegistro = $this->nroConRegistro();

        $this->assertNotNull($sinEstado);
        if ($conRegistro === null) {
            $this->markTestSkipped('No hay facturas con registro en facturas_pendientes en la BD de la app.');
        }

        $response = $this->get('/facturas/exportar?estado=sin');

        $response->assertOk();
        $this->assertStringContainsString('"'.$sinEstado.'"', $response->getContent());
        $this->assertStringNotContainsString('"'.$conRegistro.'"', $response->getContent());
    }
```

- [ ] **Step 5: Ejecutar y verificar que fallan**

Run: `php artisan test --filter=FacturaListTest`
Expected: FAIL — el test de orden falla (el HTML aún no tiene `name="estado"` ni el nuevo orden) y los tests de estado fallan porque el filtro no se aplica (el listado incluye facturas con y sin registro).

Run: `php artisan test --filter=FacturaExportTest`
Expected: FAIL — `test_export_respects_estado_filter` incluye facturas con registro (filtro no aplicado). Los demás tests de ese archivo siguen pasando.

- [ ] **Step 6: Commit**

```bash
git add tests/Feature/FacturaListTest.php tests/Feature/FacturaExportTest.php
git commit -m "test: send-state filter red tests"
```

---

### Task 2: Lógica del filtro en el controlador

**Files:**
- Modify: `app/Http/Controllers/FacturaController.php`

- [ ] **Step 1: Agregar el filtro `estado` en `consultaFiltrada()`**

En `app/Http/Controllers/FacturaController.php`, dentro de `consultaFiltrada()`, justo después del `when` de `hasta` (antes del `select`), agregar:

```php
            ->when($request->filled('estado'), function ($query) use ($request) {
                $tabla = FacturaPendiente::query()->getConnection()->getDatabaseName().'.facturas_pendientes as fp';

                $query->leftJoin($tabla, 'fp.nrofactura', '=', 'facturas.NroFactura');

                $estado = (string) $request->string('estado');

                if ($estado === 'enviado') {
                    $query->where('fp.enviado', true);
                } elseif ($estado === 'pendiente') {
                    $query->where('fp.enviado', false);
                } elseif ($estado === 'sin') {
                    $query->whereNull('fp.nrofactura');
                }
            })
```

Notas:
- `FacturaPendiente` ya está importada en el controlador (`use App\Models\FacturaPendiente;`). El `leftJoin` es **condicional**: solo se arma cuando `estado` viene lleno, de modo que listado y exportación normales no pagan el join cross-BD.
- `FacturaPendiente::query()->getConnection()->getDatabaseName()` devuelve el nombre de la BD de la app (p. ej. `puntopan_app`), NO se hardcodea.
- El join va hacia la BD de la app porque `facturas_pendientes` NO existe en la BD `puntopan` (donde corre la query).

- [ ] **Step 2: Pasar `$estados` al view en `index()`**

En `index()`, el `return view(...)` actual es:

```php
        return view('facturas.index', [
            'facturas' => $facturas,
            'tipos' => ['Contado', 'Credito'],
            'pendientes' => $pendientes,
        ]);
```

Reemplazarlo por:

```php
        return view('facturas.index', [
            'facturas' => $facturas,
            'tipos' => ['Contado', 'Credito'],
            'estados' => ['enviado' => 'Enviado', 'pendiente' => 'Pendiente', 'sin' => 'Sin estado'],
            'pendientes' => $pendientes,
        ]);
```

- [ ] **Step 3: Ejecutar los tests del filtro de estado**

Run: `php artisan test --filter=estado`
Expected: PASS — `test_index_filters_by_estado_enviado`, `test_index_filters_by_estado_sin_estado` y `test_export_respects_estado_filter` ya pasan (el test de orden sigue en rojo hasta la Task 3).

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/FacturaController.php
git commit -m "feat: filter invoices by send state"
```

---

### Task 3: Vista — dos líneas de filtros y select Estado

**Files:**
- Modify: `resources/views/facturas/index.blade.php`

- [ ] **Step 1: Actualizar el bloque `@php`**

En `resources/views/facturas/index.blade.php`, el bloque `@php` actual:

```php
@php
    $money = fn ($value) => number_format((float) $value, 0, ',', '.');
    $q = request()->query('q', '');
    $cliente = request()->query('cliente', '');
    $desde = request()->query('desde', '');
    $hasta = request()->query('hasta', '');
    $tipo = request()->query('tipo', '');
@endphp
```

Reemplazarlo por (agrega `$estadoFiltro`):

```php
@php
    $money = fn ($value) => number_format((float) $value, 0, ',', '.');
    $q = request()->query('q', '');
    $cliente = request()->query('cliente', '');
    $desde = request()->query('desde', '');
    $hasta = request()->query('hasta', '');
    $tipo = request()->query('tipo', '');
    $estadoFiltro = request()->query('estado', '');
@endphp
```

- [ ] **Step 2: Reemplazar el grid de filtros por dos líneas + select Estado**

Reemplazar el contenido del `div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6"` (bloque completo que hoy contiene q, tipo, desde, hasta, cliente) por:

```html
            <div class="xl:col-span-2">
                <label for="q" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">N° factura</label>
                <input type="text" id="q" name="q" value="{{ $q }}" placeholder="001-001-0000000"
                       class="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="xl:col-span-2">
                <label for="tipo" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Tipo</label>
                <select id="tipo" name="tipo"
                        class="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    @foreach ($tipos as $opcion)
                        <option value="{{ $opcion }}" @selected($tipo === $opcion)>{{ $opcion }}</option>
                    @endforeach
                </select>
            </div>
            <div class="xl:col-span-2">
                <label for="cliente" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Cliente</label>
                <input type="text" id="cliente" name="cliente" value="{{ $cliente }}" placeholder="Nombre del cliente"
                       class="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="xl:col-span-2">
                <label for="desde" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Desde</label>
                <input type="date" id="desde" name="desde" value="{{ $desde }}"
                       class="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="xl:col-span-2">
                <label for="hasta" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Hasta</label>
                <input type="date" id="hasta" name="hasta" value="{{ $hasta }}"
                       class="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="xl:col-span-2">
                <label for="estado" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Estado</label>
                <select id="estado" name="estado"
                        class="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    @foreach ($estados as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected($estadoFiltro === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
```

Nota: cada campo queda con `xl:col-span-2`, de modo que en `xl` el grid de 6 columnas forma dos líneas de tres: **L1: N° factura · Tipo · Cliente** y **L2: Desde · Hasta · Estado**. El orden DOM es `q`, `tipo`, `cliente`, `desde`, `hasta`, `estado`. No se tocan los botones (Limpiar / Filtrar / Exportar a Excel).

- [ ] **Step 3: Ejecutar los tests y verificar que pasan**

Run: `php artisan test --filter=FacturaListTest`
Expected: PASS (9 tests: 7 existentes + 2 de estado, con el test de orden actualizado)

Run: `php artisan test --filter=FacturaExportTest`
Expected: PASS (6 tests)

- [ ] **Step 4: Commit**

```bash
git add resources/views/facturas/index.blade.php
git commit -m "feat: add send-state filter and two-line filter layout"
```

---

### Task 4: Formateo y verificación completa

**Files:**
- None (solo comandos)

- [ ] **Step 1: Aplicar pint**

Run: `./vendor/bin/pint app/Http/Controllers/FacturaController.php tests/Feature/FacturaListTest.php tests/Feature/FacturaExportTest.php`
Expected: salida de pint (formatea o confirma limpio).

- [ ] **Step 2: Correr la suite completa**

Run: `php artisan test`
Expected: PASS — toda la suite.

- [ ] **Step 3: Commit final si pint cambió archivos**

```bash
git status --porcelain
```
Si pint modificó archivos:
```bash
git add -A
git commit -m "style: apply pint formatting"
```
Si no cambió nada, no crear commit vacío; reportar árbol limpio.

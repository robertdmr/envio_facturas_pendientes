# Exportar facturas filtradas a Excel — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Agregar un botón que exporte a CSV (abre en Excel) todos los registros de facturas que coinciden con el filtro actual, y corregir el orden de los campos del filtro (N° Factura, Tipo, Desde, Hasta, Cliente).

**Architecture:** Se extrae la construcción de la query filtrada del método `index()` del `FacturaController` a un método privado reutilizable. `index` la pagina (10 por página) y un nuevo método `exportar` la devuelve completa como descarga CSV con BOM UTF-8. Se registra una nueva ruta GET antes de la ruta `{factura}` y se agrega un botón `formaction` en el form del listado.

**Tech Stack:** Laravel 13 (PHP 8.3), Blade + Tailwind, MySQL (conexión `puntopan`, solo lectura), PHPUnit 12 para tests de feature sobre la BD real.

**Spec:** `docs/superpowers/specs/2026-09-06-exportar-facturas-filtradas-design.md`

---

## File Structure

- Modify: `app/Http/Controllers/FacturaController.php` — extraer `consultaFiltrada()`, agregar `exportar()`.
- Modify: `routes/web.php` — registrar `GET /facturas/exportar` (antes de la ruta show).
- Modify: `resources/views/facturas/index.blade.php` — reordenar campos del filtro y agregar botón de exportación.
- Create: `tests/Feature/FacturaExportTest.php` — tests del endpoint de exportación.
- Modify: `tests/Feature/FacturaListTest.php` — tests de orden de campos y botón.

Contexto del código actual:
- `index()` (controlador líneas 16-68): query con `leftJoinSub` de `DetalleFactura`, `leftJoin clientes`, filtros `q`, `cliente`, `tipo`, `desde`, `hasta`, orden `FechaFactura` desc + `NroFactura` desc, `paginate(10)`.
- Form del filtro en `index.blade.php` (líneas 20-67): grid de 6 columnas con orden actual q(2), cliente, desde, hasta, tipo.
- Las tablas `facturas`, `clientes`, `detalle_factura`/`comandadet` viven en la BD `puntopan`; los estados Enviado/Pendiente en la tabla local `facturas_pendientes` (columnas `nrofactura`, `enviado`).

---

### Task 1: Test de feature para el endpoint de exportación (falla primero)

**Files:**
- Create: `tests/Feature/FacturaExportTest.php`

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/FacturaExportTest.php` con el contenido exacto:

```php
<?php

namespace Tests\Feature;

use App\Models\Factura;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FacturaExportTest extends TestCase
{
    public function test_export_returns_csv_with_expected_header(): void
    {
        $response = $this->get('/facturas/exportar');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="facturas_', (string) $response->headers->get('Content-Disposition'));
        $response->assertSee('N° Factura', false);
        $response->assertSee('Situación', false);
    }

    public function test_export_respects_tipo_filter(): void
    {
        $credito = Factura::query()->where('TipoFactura', 'Credito')
            ->orderByDesc('FechaFactura')
            ->orderByDesc('NroFactura')
            ->value('NroFactura');
        $contado = Factura::query()->where('TipoFactura', 'Contado')
            ->orderByDesc('FechaFactura')
            ->orderByDesc('NroFactura')
            ->value('NroFactura');

        $this->assertNotNull($credito);
        $this->assertNotNull($contado);

        $response = $this->get('/facturas/exportar?tipo=Credito');

        $response->assertOk();
        $this->assertStringContainsString('"'.$credito.'"', $response->getContent());
        $this->assertStringNotContainsString('"'.$contado.'"', $response->getContent());
    }

    public function test_export_is_not_limited_to_first_page(): void
    {
        $nro = Factura::query()
            ->orderByDesc('FechaFactura')
            ->orderByDesc('NroFactura')
            ->offset(15)
            ->value('NroFactura');

        $this->assertNotNull($nro);

        $response = $this->get('/facturas/exportar');

        $response->assertOk();
        $this->assertStringContainsString('"'.$nro.'"', $response->getContent());
    }

    public function test_export_escapes_client_names_with_commas(): void
    {
        $fila = DB::connection('puntopan')->table('facturas')
            ->join('clientes', 'clientes.IdCliente', '=', 'facturas.IdCliente')
            ->whereNotNull('clientes.NombreEmpresa')
            ->where('clientes.NombreEmpresa', 'like', '%,%')
            ->orderByDesc('facturas.FechaFactura')
            ->orderByDesc('facturas.NroFactura')
            ->first(['facturas.NroFactura', 'clientes.NombreEmpresa']);

        if ($fila === null) {
            $this->markTestSkipped('No hay clientes con coma en el nombre en la BD puntopan.');
        }

        $response = $this->get('/facturas/exportar?q='.urlencode($fila->NroFactura));

        $response->assertOk();
        $this->assertStringContainsString('"'.str_replace('"', '""', $fila->NombreEmpresa).'"', $response->getContent());
    }
}
```

- [ ] **Step 2: Ejecutar el test y verificar que falla**

Run: `php artisan test --filter=FacturaExportTest`
Expected: FAIL — todos los tests dan 404 porque la ruta `/facturas/exportar` no existe aún.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/FacturaExportTest.php
git commit -m "test: export endpoint returns filtered invoices as csv"
```

---

### Task 2: Implementar el endpoint de exportación

**Files:**
- Modify: `app/Http/Controllers/FacturaController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Refactorizar `index()` para extraer la query filtrada**

En `app/Http/Controllers/FacturaController.php`, reemplazar el método `index()` (líneas 16-68) por:

```php
    public function index(Request $request)
    {
        $facturas = $this->consultaFiltrada($request)
            ->paginate(10)
            ->withQueryString();

        $pendientes = FacturaPendiente::query()
            ->whereIn('nrofactura', $facturas->pluck('NroFactura'))
            ->get()
            ->keyBy('nrofactura');

        return view('facturas.index', [
            'facturas' => $facturas,
            'tipos' => ['Contado', 'Credito'],
            'pendientes' => $pendientes,
        ]);
    }
```

- [ ] **Step 2: Agregar los métodos privados de consulta y el método `exportar`**

Agregar estos métodos al final de la clase (después del método `respuesta`, antes de `filtroFechaValido` — el orden exacto no importa, pint lo dejará prolijo):

```php
    private function consultaFiltrada(Request $request): Builder
    {
        return Factura::query()
            ->leftJoinSub(
                DetalleFactura::query()
                    ->select('NroFactura')
                    ->selectRaw('COUNT(*) as items')
                    ->selectRaw('COALESCE(SUM(Cantidad * PrecioVenta - descuento), 0) as total')
                    ->whereNotNull('NroFactura')
                    ->groupBy('NroFactura'),
                'detalle',
                'facturas.NroFactura',
                '=',
                'detalle.NroFactura'
            )
            ->leftJoin('clientes', 'clientes.IdCliente', '=', 'facturas.IdCliente')
            ->when($request->filled('q'), function ($query) use ($request) {
                $query->where('facturas.NroFactura', 'like', '%'.$request->string('q').'%');
            })
            ->when($request->filled('cliente'), function ($query) use ($request) {
                $query->where('clientes.NombreEmpresa', 'like', '%'.$request->string('cliente').'%');
            })
            ->when($request->filled('tipo'), function ($query) use ($request) {
                $query->where('facturas.TipoFactura', $request->string('tipo'));
            })
            ->when($this->filtroFechaValido($request, 'desde'), function ($query) use ($request) {
                $query->whereDate('facturas.FechaFactura', '>=', $request->string('desde'));
            })
            ->when($this->filtroFechaValido($request, 'hasta'), function ($query) use ($request) {
                $query->whereDate('facturas.FechaFactura', '<=', $request->string('hasta'));
            })
            ->select(
                'facturas.*',
                'detalle.items',
                'detalle.total',
                'clientes.NombreEmpresa as nombre_cliente'
            )
            ->orderByDesc('facturas.FechaFactura')
            ->orderByDesc('facturas.NroFactura');
    }

    public function exportar(Request $request)
    {
        $facturas = $this->consultaFiltrada($request)->get();

        $pendientes = FacturaPendiente::query()
            ->whereIn('nrofactura', $facturas->pluck('NroFactura'))
            ->get()
            ->keyBy('nrofactura');

        $estado = function (Factura $factura) use ($pendientes): string {
            $pendiente = $pendientes[$factura->NroFactura] ?? null;

            if ($pendiente === null) {
                return '';
            }

            return $pendiente->enviado ? 'Enviado' : 'Pendiente';
        };

        $filas = $facturas->map(fn (Factura $factura) => [
            (string) $factura->NroFactura,
            substr((string) $factura->FechaFactura, 0, 10),
            (string) $factura->nombre_cliente,
            (string) $factura->TipoFactura,
            (string) $factura->SituFactura,
            (int) $factura->items,
            number_format((float) $factura->total, 2, '.', ''),
            $estado($factura),
        ]);

        $escapar = static fn ($valor) => '"'.str_replace('"', '""', (string) $valor).'"';

        $csv = "\xEF\xBB\xBF";
        $csv .= implode(',', array_map($escapar, [
            'N° Factura', 'Fecha', 'Cliente', 'Tipo', 'Situación', 'Ítems', 'Total', 'Estado',
        ]))."\r\n";

        foreach ($filas as $fila) {
            $csv .= implode(',', array_map($escapar, $fila))."\r\n";
        }

        $nombre = 'facturas_'.date('Ymd_His').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
        ]);
    }
```

- [ ] **Step 3: Agregar el import de `Builder`**

En el bloque de `use` de `app/Http/Controllers/FacturaController.php`, agregar:

```php
use Illuminate\Database\Eloquent\Builder;
```

El bloque de imports queda ordenado así (pint lo ordena solo):

```php
use App\Jobs\EnviarPendienteJob;
use App\Models\DetalleFactura;
use App\Models\Factura;
use App\Models\FacturaPendiente;
use App\Services\EnvioEfacturaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use RuntimeException;
```

- [ ] **Step 4: Registrar la ruta de exportación**

En `routes/web.php`, insertar entre la ruta `/` (línea 7) y la ruta `facturas/{factura}` (línea 9):

```php
Route::get('/facturas/exportar', [FacturaController::class, 'exportar'])->name('facturas.exportar');
```

Importante: debe quedar **antes** de `Route::get('/facturas/{factura}', ...)` para que `exportar` no se resuelva como parámetro `{factura}`.

- [ ] **Step 5: Ejecutar los tests de exportación y del listado**

Run: `php artisan test --filter=FacturaExportTest`
Expected: PASS (4 tests)

Run: `php artisan test --filter=FacturaListTest`
Expected: PASS — confirma que el refactor del `index()` no rompió el listado.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/FacturaController.php routes/web.php
git commit -m "feat: export filtered invoices to csv"
```

---

### Task 3: Reordenar filtros y agregar botón de exportación en la vista

**Files:**
- Modify: `tests/Feature/FacturaListTest.php`
- Modify: `resources/views/facturas/index.blade.php`

- [ ] **Step 1: Escribir los tests que fallan**

Agregar estos dos métodos a la clase `FacturaListTest` en `tests/Feature/FacturaListTest.php` (antes de la llave final de la clase):

```php
    public function test_index_shows_filter_fields_in_expected_order(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder([
                'name="q"',
                'name="tipo"',
                'name="desde"',
                'name="hasta"',
                'name="cliente"',
            ], false);
    }

    public function test_index_shows_export_button_to_export_route(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Exportar a Excel')
            ->assertSee(route('facturas.exportar'), false);
    }
```

- [ ] **Step 2: Ejecutar y verificar que fallan**

Run: `php artisan test --filter=FacturaListTest`
Expected: FAIL — los 2 tests nuevos fallan (el orden actual en el HTML es q, cliente, desde, hasta, tipo, y no existe el botón "Exportar a Excel").

- [ ] **Step 3: Reordenar los campos del formulario de filtros**

En `resources/views/facturas/index.blade.php`, reemplazar todo el bloque interno del grid (el `div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6"`, líneas 22-53) por el siguiente contenido, que deja el orden: N° Factura (2 columnas), Tipo, Desde, Hasta, Cliente:

```html
            <div class="xl:col-span-2">
                <label for="q" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">N° factura</label>
                <input type="text" id="q" name="q" value="{{ $q }}" placeholder="001-001-0000000"
                       class="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="tipo" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Tipo</label>
                <select id="tipo" name="tipo"
                        class="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    @foreach ($tipos as $opcion)
                        <option value="{{ $opcion }}" @selected($tipo === $opcion)>{{ $opcion }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="desde" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Desde</label>
                <input type="date" id="desde" name="desde" value="{{ $desde }}"
                       class="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="hasta" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Hasta</label>
                <input type="date" id="hasta" name="hasta" value="{{ $hasta }}"
                       class="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="cliente" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Cliente</label>
                <input type="text" id="cliente" name="cliente" value="{{ $cliente }}" placeholder="Nombre del cliente"
                       class="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
```

- [ ] **Step 4: Agregar el botón de exportación**

En el mismo archivo, dentro del `div class="flex items-center gap-2"` de la fila de acciones del form (líneas 56-65), agregar el botón de exportación **después** del botón "Filtrar". El bloque de acciones queda así (el orden importa: "Filtrar" debe seguir siendo el primer botón `type="submit"` para que Enter filtre y no exporte):

```html
            <div class="flex items-center gap-2">
                <a href="{{ route('facturas.index') }}"
                   class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                    Limpiar
                </a>
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Filtrar
                </button>
                <button type="submit" formaction="{{ route('facturas.exportar') }}"
                        class="rounded-lg border border-emerald-600 bg-white px-4 py-2 text-sm font-medium text-emerald-700 shadow-sm hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                    Exportar a Excel
                </button>
            </div>
```

- [ ] **Step 5: Ejecutar los tests y verificar que pasan**

Run: `php artisan test --filter=FacturaListTest`
Expected: PASS (5 tests: 3 originales + 2 nuevos)

Run: `php artisan test --filter=FacturaExportTest`
Expected: PASS (4 tests)

- [ ] **Step 6: Commit**

```bash
git add resources/views/facturas/index.blade.php tests/Feature/FacturaListTest.php
git commit -m "feat: reorder listing filters and add export button"
```

---

### Task 4: Formateo y verificación completa

**Files:**
- None (solo comandos)

- [ ] **Step 1: Aplicar pint**

Run: `./vendor/bin/pint app/Http/Controllers/FacturaController.php tests/Feature/FacturaExportTest.php tests/Feature/FacturaListTest.php`
Expected: salida indicando que los archivos están bien formateados (o los corrige).

- [ ] **Step 2: Correr la suite completa**

Run: `php artisan test`
Expected: PASS — toda la suite (incluye los tests existentes de envío, configuración, builder, etc.).

- [ ] **Step 3: Commit final si pint cambió archivos**

```bash
git add -A
git commit -m "style: apply pint formatting" || echo "sin cambios para commitear"
```

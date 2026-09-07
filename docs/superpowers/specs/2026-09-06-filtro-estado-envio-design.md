# Filtro de estado de envío (Enviado / Pendiente / Sin estado)

Fecha: 2026-09-06

## Resumen

Se agrega un nuevo filtro de **Estado** al listado de facturas, que permite acotar por el estado de envío a la e-factura: **Enviado**, **Pendiente** o **Sin estado** (nunca se generó JSON). De paso, el formulario de filtros pasa a distribuirse en **dos líneas**.

## Contexto

- Vista: `resources/views/facturas/index.blade.php` — form GET de filtros, hoy en una sola línea con orden q, tipo, desde, hasta, cliente.
- Controlador: `app/Http/Controllers/FacturaController.php` — `consultaFiltrada()` centraliza joins + filtros y la usan `index()` (paginado) y `exportar()` (CSV completo).
- **Topología de BD:** la query del listado corre contra la BD **`puntopan`** (conexión `puntopan`, solo lectura, tablas `facturas`, `clientes`, `comandadet`). El estado de envío vive en **`facturas_pendientes`**, que está en la BD de la app **`puntopan_app`** (conexión default), NO en `puntopan`. Ambas BD están en el mismo servidor MySQL, por lo que es posible un `leftJoin` cross-database calificando el nombre (`puntopan_app`.`facturas_pendientes`).
- `facturas_pendientes` (`nrofactura` único, `enviado` bool, `respuesta` nullable):
  - **Enviado** → existe registro y `enviado = 1`.
  - **Pendiente** → existe registro y `enviado = 0` (se generó el JSON pero aún no se confirmó el envío).
  - **Sin estado** → no existe registro (nunca se generó el JSON).

## Requerimientos

1. **Layout en dos líneas** del form de filtros (grid `xl:grid-cols-6`, cada campo `xl:col-span-2` → 3 por línea):
   - Línea 1: N° Factura · Tipo · Cliente
   - Línea 2: Desde · Hasta · **Estado**
   - El orden DOM queda: `q`, `tipo`, `cliente`, `desde`, `hasta`, `estado`.
2. **Select "Estado"** con opciones: `Todos` (vacío), `Enviado` (valor `enviado`), `Pendiente` (`pendiente`), `Sin estado` (`sin`). Se muestra igual que `$tipos` (select con `Todos` + foreach de `$estados`).
3. **Lógica en `consultaFiltrada()`**: solo cuando el filtro `estado` viene lleno, agregar un `leftJoin` cross-database con `facturas_pendientes` de la BD de la app (nombre derivado de la conexión default en runtime, sin hardcodear) y aplicar:
   - `enviado` → `fp.enviado = 1`
   - `pendiente` → `fp.enviado = 0`
   - `sin` → `fp.nrofactura IS NULL`
   - Como `exportar()` reutiliza `consultaFiltrada()`, la exportación a CSV respeta este filtro automáticamente.
4. **Controlador `index()`**: pasar `estados` al view junto a `tipos`.
5. Preservar el comportamiento existente (listado paginado y CSV) cuando el filtro no se usa: el join cross-BD solo se agrega cuando `estado` está presente.

## Decisiones de diseño

- El join a `facturas_pendientes` es **condicional** (dentro del `when` de `estado`) y **cross-database**, calificando el nombre de la BD de la app. El nombre de BD se obtiene en runtime de la conexión default (`FacturaPendiente::query()->getConnection()->getDatabaseName()`), evitando hardcodear `puntopan_app`.
- `facturas_pendientes.nrofactura` es único, por lo que el join nunca duplica filas.
- No cambian el método `exportar()` ni el formato del CSV (solo su query base).

## Archivos a modificar

| Archivo | Cambio |
| --- | --- |
| `app/Http/Controllers/FacturaController.php` | `leftJoin` cross-BD condicional + filtro `estado` en `consultaFiltrada()`; pasar `$estados` al view en `index()` |
| `resources/views/facturas/index.blade.php` | Distribuir campos en 2 líneas; agregar select "Estado" (usando `$estados`) |
| `tests/Feature/FacturaListTest.php` | Actualizar test de orden; tests de filtro por estado (con join cross-BD) |
| `tests/Feature/FacturaExportTest.php` | Test de exportación respetando filtro de estado |

## Pruebas

Feature tests sobre la BD real (mismo patrón que el resto). Los helpers de test derivan el nombre de la BD de `facturas_pendientes` de la conexión default y consultan con join cross-BD calificado:

1. `test_index_shows_filter_fields_in_expected_order` → se actualiza al nuevo orden DOM: `name="q"`, `name="tipo"`, `name="cliente"`, `name="desde"`, `name="hasta"`, `name="estado"`.
2. `test_index_filters_by_estado_enviado` → con `?estado=enviado` aparecen facturas enviadas y no aparecen facturas sin estado. Se resuelve una factura enviada real desde la BD; si no existe ninguna, `markTestSkipped`.
3. `test_index_filters_by_estado_sin_estado` → con `?estado=sin` aparecen facturas sin registro en `facturas_pendientes` y no aparece una factura con registro conocida.
4. `test_export_respects_estado_filter` (en `FacturaExportTest`) → `GET /facturas/exportar?estado=sin` incluye una factura sin estado y excluye una con registro conocida; condicional a datos (skip si no hay casos).

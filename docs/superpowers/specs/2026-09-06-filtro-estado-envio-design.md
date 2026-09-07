# Filtro de estado de envío (Enviado / Pendiente / Sin estado)

Fecha: 2026-09-06

## Resumen

Se agrega un nuevo filtro de **Estado** al listado de facturas, que permite acotar por el estado de envío a la e-factura: **Enviado**, **Pendiente** o **Sin estado** (nunca se generó JSON). De paso, el formulario de filtros pasa a distribuirse en **dos líneas**.

## Contexto

- Vista: `resources/views/facturas/index.blade.php` — form GET de filtros, hoy en una sola línea con orden q, tipo, desde, hasta, cliente.
- Controlador: `app/Http/Controllers/FacturaController.php` — `consultaFiltrada()` centraliza joins + filtros y la usan `index()` (paginado) y `exportar()` (CSV completo).
- El estado de envío vive en la tabla local `facturas_pendientes` (`nrofactura` único, `enviado` bool, `respuesta` text nullable):
  - **Enviado** → existe registro y `enviado = 1`.
  - **Pendiente** → existe registro y `enviado = 0` (se generó el JSON pero aún no se confirmó el envío).
  - **Sin estado** → no existe registro (nunca se generó el JSON).

## Requerimientos

1. **Layout en dos líneas** del form de filtros (grid `xl:grid-cols-6`, cada campo `xl:col-span-2` → 3 por línea):
   - Línea 1: N° Factura · Tipo · Cliente
   - Línea 2: Desde · Hasta · **Estado**
   - El orden DOM queda: `q`, `tipo`, `cliente`, `desde`, `hasta`, `estado`.
2. **Select "Estado"** con opciones: `Todos` (vacío), `Enviado` (valor `enviado`), `Pendiente` (`pendiente`), `Sin estado` (`sin`). Se muestra igual que `$tipos` (select con `Todos` + foreach de `$estados`).
3. **Lógica en `consultaFiltrada()`**: agregar `leftJoin` con `facturas_pendientes` por `nrofactura` y aplicar, cuando `estado` viene lleno:
   - `enviado` → `facturas_pendientes.enviado = 1`
   - `pendiente` → `facturas_pendientes.enviado = 0`
   - `sin` → `facturas_pendientes.nrofactura IS NULL`
   - Como `exportar()` reutiliza `consultaFiltrada()`, la exportación a CSV respeta este filtro automáticamente.
4. **Controlador `index()`**: pasar `estados` al view junto a `tipos`.
5. Preservar el comportamiento existente (listado paginado y CSV) cuando el filtro no se usa.

## Decisiones de diseño

- El `leftJoin` se agrega en `consultaFiltrada()` (la query compartida), de modo que listado y exportación filtran igual. No se tocan `routes/web.php` ni la lógica de la columna Estado de la tabla.
- `facturas_pendientes.nrofactura` es único, por lo que el join nunca duplica filas.
- No cambian el método `exportar()` ni el formato del CSV (solo su query base).

## Archivos a modificar

| Archivo | Cambio |
| --- | --- |
| `app/Http/Controllers/FacturaController.php` | `leftJoin` + filtro `estado` en `consultaFiltrada()`; pasar `$estados` al view en `index()` |
| `resources/views/facturas/index.blade.php` | Distribuir campos en 2 líneas; agregar select "Estado" (usando `$estados`) |
| `tests/Feature/FacturaListTest.php` | Actualizar test de orden; tests de filtro por estado |
| `tests/Feature/FacturaExportTest.php` | Test de exportación respetando filtro de estado |

## Pruebas

Feature tests sobre la BD real `puntopan` (mismo patrón que el resto):

1. `test_index_shows_filter_fields_in_expected_order` → se actualiza al nuevo orden DOM: `name="q"`, `name="tipo"`, `name="cliente"`, `name="desde"`, `name="hasta"`, `name="estado"`.
2. `test_index_filters_by_estado_enviado` → con `?estado=enviado` aparecen facturas enviadas y no aparecen facturas sin registro. Se resuelve una factura enviada real desde la BD; si no existe ninguna, `markTestSkipped`.
3. `test_index_filters_by_estado_sin_estado` → con `?estado=sin` aparecen facturas sin registro en `facturas_pendientes` y no aparece una factura enviada/pendiente conocida.
4. `test_export_respects_estado_filter` (en `FacturaExportTest`) → `GET /facturas/exportar?estado=sin` incluye una factura sin estado y excluye una enviada conocida; condicional a datos (skip si no hay casos).

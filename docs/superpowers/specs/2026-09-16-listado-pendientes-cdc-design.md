# Listado de facturas pendientes con CDC y actualización de `fecdc`

Fecha: 2026-09-16

## Resumen

Nueva sección **Pendientes** (`GET /pendientes`) que lista las filas de
`facturas_pendientes` (BD de la app `puntopan_app`) mostrando el **CDC** extraído
de la respuesta del endpoint, y el estado de ese comprobante en la tabla remota
`fecdc` (BD legacy `puntopan`). Incluye un botón (individual y por lote) que
escribe el CDC en `fecdc`.

## Contexto verificado

- `facturas_pendientes` vive en `puntopan_app` (local `127.0.0.1`): `nrofactura`
  único, `enviado`, `respuesta` (cuerpo JSON del endpoint).
- `fecdc` vive en `puntopan` (remoto `10.242.217.171`): `id` PK, `nrofactura`
  (puede repetirse), `fecha_envio`, `fecha_actualizacion` (varchar, formato
  `j/n/Y G:i:s`), `cdc`, `qr`, `estado` (valores en minúscula: `creado`,
  `enviado`, `aprobado`, `rechazado`), etc.
- La respuesta del endpoint tiene el CDC en la raíz: `{"qr":"...","cdc":"...","tipo":"FE"}`.
- `puntopan_app` y `puntopan` están en **servidores distintos**: no se puede
  hacer `JOIN` cross-database. El listado y `fecdc` se consultan por separado.

## Excepción a la regla de solo lectura (autorizada)

`puntopan` se mantiene como base de **solo lectura**, con **una única excepción
explícita autorizada**: la escritura del campo `cdc` (más `fecha_actualizacion` y
`estado`) en la tabla `fecdc`, siempre filtrando por `nrofactura`. No se escribe
ninguna otra tabla ni columna de `puntopan`.

## Backend

- **Modelo** `app/Models/Fecdc.php`: conexión `puntopan`, tabla `fecdc`, PK `id`,
  sin timestamps, `$guarded = []`.
- **Helper** `FacturaPendiente::cdc(): ?string`: decodifica `respuesta` y devuelve
  `datos['cdc']` si es string no vacío; si no, `null`.
- **Controlador** `app/Http/Controllers/FacturaPendienteController.php`:
  - `index(Request)`: pagina `FacturaPendiente` (20/página, orden
    `updated_at DESC, id DESC`, filtro `q` por `nrofactura` LIKE) y consulta
    `fecdc` remoto con `whereIn(nrofactura)` agrupado por `nrofactura`
    (la fila de mayor `id` es la que se muestra; se informa el total de filas).
  - `actualizarCdc(string $nrofactura)`: 404 si no hay pendiente; 422 si la
    respuesta no tiene `cdc`; actualiza **todas** las filas de `fecdc` de ese
    `nrofactura` (`cdc`, `fecha_actualizacion = now()->format('j/n/Y G:i:s')`,
    `estado = 'aprobado'`); responde `{nrofactura, cdc, filas_actualizadas}`.
  - `actualizarCdcLote(Request)`: valida `nrofacturas[]` (array, min 1, max
    2000); recorre los nros únicos, actualiza los que tienen `cdc` y omite el
    resto; responde `{actualizadas, omitidas, filas}`.
  - `actualizarFecdc(array $cdcPorNro): int` privado, compartido.

### Rendimiento de la escritura (fix del 502)

El servidor remoto tiene ~500 ms de latencia por sentencia y `fecdc.nrofactura`
**no tiene índice**. Un `UPDATE ... WHERE nrofactura = ?` hace full scan (≈9,5 s
medido) y con un lote de N facturas supera el `max_execution_time` de PHP-FPM
(30 s) → **502**. Por eso `actualizarFecdc()`:

1. Resuelve los `id` de una sola vez con un `SELECT ... WHERE nrofactura IN (...)`
   (sin locks).
2. Actualiza por clave primaria con **una sola** sentencia `UPDATE` por chunk de
   200 ids, usando `SET cdc = CASE id WHEN ... THEN ... END` y
   `WHERE id IN (...)`, lo que limita los locks a las filas modificadas.

Medición: 50 facturas pasaron de ~26 s a ~0,5 s.
  - Errores de conexión al remoto → 503 (individual) u omitida (lote).

## Rutas

- `GET /pendientes` → `pendientes.index`.
- `POST /pendientes/{nrofactura}/cdc` → `pendientes.actualizarCdc`.
- `POST /pendientes/actualizar-cdc` → `pendientes.actualizarCdcLote`.

## Vista `resources/views/pendientes/index.blade.php`

- Filtro por N° factura + Limpiar/Filtrar.
- Barra de selección con contador, "Limpiar selección" y "Actualizar
  seleccionadas" (misma mecánica que el listado de facturas: checkbox maestro,
  selección persistida en `sessionStorage`).
- Tabla: selección · N° Factura · Estado envío · CDC (respuesta) · CDC en `fecdc`
  · Estado `fecdc` · Fecha envío `fecdc` · Acciones.
- Botón **Actualizar CDC** por fila (deshabilitado si la respuesta no trae cdc) y
  acción por lote. Ambos con `fetch` + CSRF; muestran resultado y recargan.
- Paginación con `withQueryString()`.
- Enlace **Pendientes** en el sidebar (`routeIs('pendientes.*')`). El pie del
  sidebar refleja la excepción de escritura.

## Pruebas

Feature tests contra la BD real (patrón del proyecto). Las escrituras a `fecdc`
se hacen dentro de una transacción sobre la conexión `puntopan` que se revierte
en `tearDown` (no se deja ningún cambio en el remoto).

- `PendienteListTest`: `GET /pendientes` lista una pendiente con su CDC; muestra
  el `cdc` de `fecdc` para una factura enlazada.
- `ActualizarCdcTest`: actualiza `cdc`/`estado='aprobado'`/`fecha_actualizacion`
  en todas las filas; 422 sin cdc; 404 sin pendiente.
- `ActualizarCdcLoteTest`: actualiza varias y omite las sin cdc; valida array no
  vacío.

# Exportar facturas filtradas a Excel y reordenar filtros

Fecha: 2026-09-06

## Resumen

En el listado de facturas (`/` → `facturas.index`) se agrega la posibilidad de exportar a un archivo que se abre en Excel los registros que coinciden con el filtro actual. Además se corrige el orden de los campos del formulario de filtrado.

## Contexto

- Vista: `resources/views/facturas/index.blade.php`
- Controlador: `app/Http/Controllers/FacturaController.php` (método `index`)
- Rutas: `routes/web.php`
- El listado pagina de a 10 y filtra por `q` (N° factura), `cliente`, `tipo`, `desde`, `hasta`.

## Requerimientos

1. Reordenar los campos del filtro en este orden: **N° Factura**, **Tipo**, **Desde**, **Hasta** y **Cliente** (al final). Se mantienen los 5 campos existentes.
2. Agregar un botón **"Exportar a Excel"** en la fila de acciones del formulario de filtros (junto a *Limpiar* y *Filtrar*). El botón exporta con los valores escritos en el formulario aunque no se haya presionado *Filtrar*.
3. La exportación incluye **todos** los registros que coinciden con el filtro (sin límite de 10 por página), con el mismo orden del listado (`FechaFactura` desc, `NroFactura` desc).
4. Formato: **CSV con BOM UTF-8** (se abre directo en Excel). Sin dependencias nuevas.
5. Columnas del CSV: `N° Factura, Fecha, Cliente, Tipo, Situación, Ítems, Total, Estado`.
   - `Estado` se resuelve igual que la tabla (Enviado / Pendiente / vacío) según `facturas_pendientes`.
   - `Ítems` como entero. `Total` numérico con punto decimal. Ambos sin separadores de miles, para que Excel los trate como números.
   - Texto escapado correctamente para CSV (comillas y separadores).

## Decisiones de diseño

- **Reutilización de filtros**: se extrae la construcción de la query (joins + filtros + orden) del `index()` a un método privado reutilizado por `index` y `exportar`, evitando duplicación.
- **Botón dentro del form**: se usa un botón `type="submit"` con `formaction="{{ route('facturas.exportar') }}"`. El form es `GET`, así la ruta de exportación recibe los mismos query params.
- **Nueva ruta**: `GET /facturas/exportar` → `FacturaController@exportar`. Se registra **antes** de `facturas/{factura}` en `routes/web.php` para evitar que `exportar` se resuelva como parámetro `{factura}`.
- **CSV**: respuesta con `Content-Type: text/csv; charset=UTF-8` y `Content-Disposition: attachment; filename="facturas_<timestamp>.csv"`, comenzando con BOM UTF-8 (`\xEF\xBB\xBF`).

## Archivos a modificar

| Archivo | Cambio |
| --- | --- |
| `resources/views/facturas/index.blade.php` | Reordenar campos del filtro; agregar botón de exportación |
| `app/Http/Controllers/FacturaController.php` | Extraer lógica de filtros; agregar método `exportar` |
| `routes/web.php` | Registrar `GET /facturas/exportar` antes de la ruta show |

## Pruebas

Nuevo test de feature `FacturaExportTest` que verifica, contra la base `puntopan` real (igual que los tests existentes):

1. `GET /facturas/exportar` responde 200 con `Content-Type` CSV y cabecera esperada (`N° Factura`, etc.).
2. La exportación respeta un filtro (p. ej. `?tipo=Credito` no incluye una factura de contado conocida, y sí incluye una de crédito).
3. La exportación no está limitada a la paginación (incluye un registro que no estaría en la primera página).
4. Contenido escapado: una factura cuyo cliente contenga `,` o `"` aparece correctamente citada (si existe en los datos).

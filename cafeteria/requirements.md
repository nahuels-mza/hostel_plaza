# Cafeteria

Problema: Crear una pequeña applicacion que permita manejar y administrar una cafeteria la cual tiene un stock que se agregan productos y cantidad de dichos productos

## Usuarios:
Existen dos usuarios uno general (gatonegro) y otro admin. Ambos usuarios accesaran por user/pass. El usuario general tendra acceso a menos secciones, definidas mas abajo

## Seccioes
Una seccion para poder crear una comanda
Una seccion para ver el stock, el stock solo lo puede ingresar/aumentar el usuario admin. Cada venta de la comanda disminuye la cantidad del sstock
Una seccion para los insumos.
Una seccion para eventos, el comportamiento es similar a la comanda; solo que se mantiene durante "la vida" del evento

## Acciones - Flujos
### Flujo de compra
Los productos se agregan a la comanda y se puede modificar la cantidad
Existen dos tipos de compradores:
- huesped del hostel : Debemos ingresar nombre
- no huesped: no es necesario el nombre
Las formas de pago son efectivo, transferencia y tarjetas
Al realizar la venta
- se descuenta del stock
- se guarda la informacion de la comanda por dia (ej ventas21Sept.json)

### Flujo carga de stock/insumos
Solamente el user admin puede acceder a la seccion para cargar nuevos productos
Solamente el user admin puede acceder a la seccion para cargar nuevas cantidades a los productos
Actualmente el stock se lee desde un excel, pero idealmente lo manejaremos en la app

### Flujo de analisis de costos
El user admin necesita poder analizar el flujo de los costos para detectar las mejoras en compras

### Flujo de analisis de ventas
El user admin necesita saber mensualemente las ventas, que producto se vende mas y si se vende mas a huespedes del hostel

## Decisiones de diseño (para las secciones de arriba)
- Eventos: ver fase 6 — quedan unificados con "mesas" bajo el mismo mecanismo de comanda persistente; un solo pago al cerrar (no pagos parciales), descuento de stock recién en ese momento.
- Costos: se lleva historial de compras (fecha, cantidad, costo) en vez de un solo campo de costo fijo, para poder ver evolución de precios de compra.
- Insumos: la carga/actualización queda restringida a admin, igual que el stock de productos (el usuario general solo opera comanda y eventos).

## Plan de implementación (por fases)
1. **Roles** — dos pares de credenciales (`cafeteria_user`/`cafeteria_pass` general, `cafeteria_admin_user`/`cafeteria_admin_pass` admin) en `do_not_upload/secrets.php`; rol guardado en sesión; helper `hp_cafeteria_require_admin()`. ✅ implementado
2. **Comprador + ventas por día** — toggle huésped/no-huésped en la comanda; ventas separadas en un archivo por día (`cafeteria/sales/ventas_YYYY-MM-DD.json`). ✅ implementado
   - La cantidad a vender nunca puede superar el stock disponible: se valida tanto en el cliente (no deja sumar más del disponible al carrito) como en el servidor (`save_sale.php` rechaza toda la venta con `409 insufficient_stock` si algo no alcanza, antes de descontar nada).
3. **Stock/insumos solo-admin + CRUD de productos/insumos** — `update_stock.php`/`update_ingredient.php` exigen admin; `cafeteria/purchases.json` (historial de compras) + `add_purchase.php`. ✅ implementado
   - CRUD completo: alta (`create_product.php`/`create_ingredient.php`, generan el `id` como slug del nombre), edición de precio de venta por separado del alta y de la carga de stock (`update_price.php`, validado como número no negativo con hasta 2 decimales, mismo formato `$1.234,56` en toda la app), y baja (`delete_product.php`/`delete_ingredient.php`, no afecta el historial de ventas/compras ya registrado que queda como snapshot).
   - El precio de venta es opcional al crear el producto (se define después desde "editar" en la tabla) — el alta solo pide nombre, categoría y precio opcional; stock/unidad se cargan al registrar la primera compra.
   - La fila de cada producto/insumo separa las acciones en controles colapsables ("Ajustar" para correcciones manuales sin costo, "Registrar compra" para sumar stock con costo, "Eliminar") en vez de amontonar todo en una sola fila.
   - El usuario general solo ve las tablas de stock/insumos en modo lectura, sin la columna de costo de compra ni ningún control de edición.
4. **Análisis de costos** (admin) — página `costos.php`: costo vs. precio de venta, margen, evolución de compras. ✅ implementado
   - Margen por producto: precio − costo de la última compra, ordenado con los que faltan datos y los de menor margen primero; ⚠️ si el margen es menor a 20% (incluye margen negativo, cuando el costo superó al precio de venta).
   - Evolución de costos: compara la última compra de cada ítem contra la anterior; ⚠️ si el costo subió más de 10%.
   - Historial de compras: últimas 50, más reciente primero, con cantidad/costo unitario/total/quién la registró.
   - Página 100% admin-only (a diferencia de stock/insumos, el general no tiene ni vista de lectura acá — redirige a la comanda).
5. **Análisis de ventas** (admin) — página `reportes.php`: ventas por mes, ranking de productos, huésped vs. no-huésped. ✅ implementado
   - Selector de mes (últimos 12 meses); suma todos los `cafeteria/sales/ventas_YYYY-MM-*.json` de ese mes (`hp_cafeteria_load_sales_for_month()`).
   - Totales: monto vendido, cantidad de ventas, ticket promedio.
   - Huésped vs. no huésped: cantidad, % sobre el total de ventas y monto, de cada tipo.
   - Por método de pago: total en efectivo/transferencia/tarjeta.
   - Ranking de productos más vendidos (por cantidad), con el total facturado de cada uno.
   - Mismo patrón admin-only que costos.php (el general no tiene acceso).
6. **Comandas múltiples (mesas)** — reemplaza el diseño original de Eventos de abajo por un mecanismo único: comandas persistentes con nombre libre, en vez de una comanda única en memoria del navegador.
   - `cafeteria/comandas.json`: lista de comandas abiertas — `id`, `name` (texto libre puesto al abrir, ej. "Mesa 5", "Cumple Juan"), `type` ("mesa"/"evento", solo etiqueta para reportes, no cambia el comportamiento), `items: [{id, qty, price}]` (precio *snapshot* al agregar, igual que hoy), `opened_at`, `opened_by`. `buyer_type`/`guest` quedan sin definir hasta el cierre.
   - `index.php` pasa a mostrar primero una pantalla "Mesas abiertas" (listado + botón "Nueva mesa"); al entrar a una, la misma grilla/carrito de siempre pero el carrito vive en el server (`add_item.php`/`remove_item.php` con flock, mismo patrón que el resto de la app) en vez de solo en JS — así una mesa abierta hace 20 minutos se puede seguir viendo/editando desde cualquier dispositivo.
   - **Descuento de stock recién al cobrar** (no al agregar el item), igual que la comanda de hoy — decisión consciente: dos mesas abiertas a la vez pueden "pisarse" el último disponible de un producto; la segunda en cerrar recibe el mismo `insufficient_stock` que ya maneja la UI, ahora entre mesas en vez de dentro de una sola comanda.
   - `save_sale.php` pasa a `close_comanda.php`: recibe `comanda_id` + `buyer_type`/`guest`/`payment_method` (pedidos recién en este paso, como hoy), valida y descuenta stock, guarda la venta en `sales/ventas_YYYY-MM-DD.json` (agregando `table_name`/`tab_type` para no perder trazabilidad), y borra la comanda de `comandas.json`.
   - Cancelar una mesa abierta sin cobrar (ej. el cliente se fue) está permitido para ambos roles, sin restricción de admin.

~~6. **Eventos** — `cafeteria/events.json`, página `eventos.php` (accesible a ambos roles como la comanda), `close_event.php` (pago único al cerrar, cae en el archivo de ventas del día con `type: "evento"`). Descuento de stock instantáneo al agregar cada producto durante la vida del evento.~~ Superado por el punto anterior: no hace falta una sección aparte, un evento es simplemente una comanda con nombre libre y `type: "evento"`.

## Cierre de la primera iteración
Con las fases 1–5 implementadas queda una v1 funcional: roles, comanda con validación de stock, CRUD de productos/insumos, costos y reportes de ventas. Falta la fase 6 (eventos).

## Tipos de producto (fase 7 — modelo de stock/producción) ✅ implementado
Se identificaron 5 tipos de producto reales (empanadas producidas acá, cuantificables como agua/gaseosa, no cuantificables como leche/café, insumos no vendibles, y vendibles sin stock propio como café expreso). En vez de crear categorías nuevas, se resolvió con dos flags booleanos por producto en `products.json` (default seguro para no romper los 26 productos ya cargados):

- `tracks_stock` (default `true`): si es `false`, el producto es vendible pero **no** lleva contador de stock — la comanda nunca lo bloquea por falta de stock y `save_sale.php` no lo valida ni lo descuenta. Reemplaza el hack de `stock: 99` que usaban Lavandería/Lavado Staff/Promo (ahora en `tracks_stock: false`, sin ese número falso). Estos productos no aparecen en la tabla principal de `stock.php` — tienen su propia sección "Vendibles sin stock propio" (solo nombre/categoría/precio, sin columna de stock ni acciones de ajuste/compra). Se puede pasar un producto de una lista a la otra con "Marcar como sin stock propio" / "Activar stock" (`update_flags.php`).
- `made_in_house` (default `false`): si es `true`, el producto tiene una **receta** (`recipes.json`: producto → lista de insumo+cantidad por unidad) y se repone con "Registrar producción" en vez de (o además de) "Registrar compra".
  - `update_recipe.php`: define/edita la receta de un producto (admin).
  - `produce_product.php`: produce N unidades — valida que haya insumos suficientes para toda la tanda (rechaza con `409 insufficient_ingredients` y el detalle si falta algo, sin descontar nada), descuenta cada insumo de `ingredients.json` según la receta, suma stock al producto, y calcula el costo unitario automáticamente (costo total de insumos consumidos ÷ cantidad producida) — no hace falta cargar el costo a mano como en una compra.
  - Cada producción queda en `purchases.json` con `kind: "production"` (incluye qué insumos se consumieron, `consumed: [...]`). En `costos.php`, "compra" y "producción" del mismo producto se tratan como una sola línea de costo (ambas responden la misma pregunta: cuánto sale conseguir una unidad más), y el historial las distingue con la etiqueta "Producción propia".

Los 26 productos existentes quedaron con `made_in_house: false` (nadie se migró automáticamente) — para pasar algo como "Empanada" a producción hay que marcarlo desde "Config" en `stock.php`, definirle una receta, y recién ahí usar "Registrar producción". Mientras no tenga receta, "Registrar producción" devuelve error pidiendo definirla primero.

## Historial de ventas / comandas cerradas (fase 8) ✅ implementado
Página nueva `ventas.php` (admin-only, 100% lectura — ni un botón de escritura) para poder ver comandas ya cerradas de cualquier fecha o rango, sin tener que esperar al resumen mensual de `reportes.php`.

- Filtro "Desde"/"Hasta" por GET (bookmarkeable), con atajos "Hoy" / "Últimos 7 días" / "Este mes". Default: hoy.
- Nuevo helper `hp_cafeteria_load_sales_for_range($from, $to)` en `helpers.php`: suma los `sales/ventas_YYYY-MM-DD.json` día por día dentro del rango (tope de 366 días para no abrir miles de archivos por error de input).
- Por cada venta: fecha/hora, mesa/tipo (`table_name`/`tab_type` que ya guarda `close_comanda.php`), comprador (huésped+nombre o no-huésped), método de pago, y el detalle línea por línea de los productos vendidos con su subtotal.
- Resumen arriba: total del período y cantidad de comandas cerradas.
- Nav "Ventas" agregado junto a Costos/Reportes en todas las páginas admin.
# Flujos recientes

## Asignación de cuentas por cobrar al crear un despacho
1. En `admin/dispatches/create` se listan los pedidos candidatos (APROBADO, PREPARANDO o PROCESADO) y las cuentas por cobrar con saldo positivo.
2. El formulario permite elegir almacén, ruta, chofer, vehículo y fecha, además de seleccionar los pedidos (`orders[]`) y las cuentas por cobrar (`accounts_receivable[]`).
3. `DispatchController@store` valida ambos listados y crea el despacho con estatus `PLANEADO`.
4. Para cada pedido seleccionado se crea un `DispatchItem` con estatus `ASIGNADO`.
5. Si se marcaron cuentas por cobrar, se actualiza su `driver_id` con el chofer elegido para que queden asignadas a la ruta.
6. Se redirige a la edición del despacho para continuar con el flujo operativo.

## Cargo automático en pedidos de crédito
1. El controlador `SalesOrderController` ahora recibe el servicio de cuentas por cobrar (`ArService`).
2. Al marcar un pedido como entregado (`deliver`), se valida que esté `EN_RUTA` y se ejecuta una transacción.
3. Dentro de la transacción se llama a `registerCreditCharge`:
   - Solo aplica si `payment_method` es `CREDITO` y el pedido tiene cliente.
   - Evita duplicados verificando que no exista un `ArMovement` tipo `CARGO` con `source` igual al pedido.
   - Usa la fecha del pedido (o la actual) y el total del pedido para generar el cargo con `ArService::charge`.
4. Después del cargo, el pedido cambia a estatus `ENTREGADO` y se guarda la marca de tiempo.
5. Con este flujo, el saldo del cliente aparece en `admin/ar` inmediatamente después de confirmar la entrega del pedido a crédito.

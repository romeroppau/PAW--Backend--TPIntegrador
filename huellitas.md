# Huellitas — HuellaVet

Huellitas es el programa de puntos de HuellaVet. El cliente suma puntos por acciones que le hacen bien a su mascota, no solo por comprar, y los canjea por productos enteros de la tienda o por la consulta base.

## Regla de base

**1 Huellita = $100.**

- Con este valor las cifras quedan fáciles de leer: una consulta de $25.000 cuesta 250 Huellitas.
- El valor se guarda en un solo parámetro (`valor_huellita`, en la tabla `configuracion`). El veterinario lo puede ajustar con la inflación sin tocar cada producto.

Los precios de este documento son de ejemplo; se reemplazan por los reales de la veterinaria.

## Cómo se ganan

| Acción | Huellitas | Equivale a | Condición |
|---|---|---|---|
| Registro en la plataforma | 50 | $5.000 | Una sola vez por cuenta |
| Vacuna o desparasitación al día | 30 | $3.000 | Por cada aplicación hecha dentro de los 7 días de la fecha del calendario sanitario |
| Compra en la tienda | 1 cada $2.000 | 5% de lo gastado | Se acreditan cuando el pago está aprobado. Las compras hechas con canje no suman |
| Reseña publicada | 10 | $1.000 | Máximo 1 por mes, y solo si el cliente tuvo una consulta o una compra |
| Adopción finalizada | 150 | $15.000 | Al marcar "Adopción completada". Cubre el kit de bienvenida (alimento y collar) |

Con estos números, un cliente típico (registro, 3 vacunas al año y unos $20.000 por mes de alimento) junta unas **280 Huellitas por año**, es decir, **una consulta gratis por año**. Es un beneficio real que no le hace perder plata a la veterinaria.

## Canje de productos

Cada producto tiene un atributo `huellitas` que carga el veterinario: es lo que cuesta canjear ese producto entero.

- **Si queda vacío, el producto no se puede canjear.**
- **Valor sugerido:** como referencia, el sistema sugiere `precio / 100`, redondeado hacia arriba a la decena.
- **Medicamentos:** nunca se pueden canjear, para que nadie "pague" un tratamiento con puntos sin indicación. Su `huellitas` siempre queda vacío.

| Producto (ejemplo) | Precio | Huellitas |
|---|---|---|
| Snack dental | $4.500 | 50 |
| Juguete mordillo | $8.000 | 80 |
| Shampoo para perros | $9.000 | 90 |
| Collar regulable | $12.000 | 120 |
| Alimento 3 kg | $18.000 | 180 |
| Medicamentos | — | No canjeable |

**En el carrito:**
- Por cada producto, el cliente elige pagarlo con dinero o canjearlo.
- Si lo canjea, `item_carrito.huellitas_a_canjear` guarda `huellitas × cantidad`. Si lo paga con dinero, queda en 0.
- La factura cobra solo los productos pagados con dinero.
- Si todo el carrito se canjeó, no hace falta pasar por MercadoPago.

## Canje de consultas

Acá no hay un atributo fijo. El sistema calcula **valor base de la consulta / valor de la Huellita**.

| Servicio (ejemplo) | Precio | Huellitas |
|---|---|---|
| Control post-adopción | $15.000 | 150 |
| Vacunación (aplicación) | $20.000 | 200 |
| Consulta general | $25.000 | 250 |

- **Solo cubre la consulta base.** Los insumos y los productos que se usen o se vendan en la atención se facturan aparte. Si no, alguien podría canjear una consulta y llevarse una cirugía de regalo.
- Las Huellitas usadas quedan en `factura_consulta.huellitas_usadas`. Si la consulta se pagó con dinero, queda vacío.

## Reglas

- **Canje completo:** solo se canjea si el saldo alcanza para el total del producto o de la consulta base. No hay canje parcial.
- **Vencimiento:** las Huellitas vencen a los 12 meses de acreditadas. Si no, la veterinaria acumula una deuda en puntos que nunca se cierra.
- **Reserva:** al iniciar el canje se reservan los puntos, que ya no se pueden usar en otro canje. Si el pago o la consulta se cancela, vuelven al saldo.
- **Devoluciones:** si se devuelve una compra, se descuentan las Huellitas que esa compra generó.

## Cómo se guarda en la base

Ver `docs/base-de-datos/DER HuellaVet.pdf`.

| Dónde | Qué guarda |
|---|---|
| `producto.huellitas` | Lo que cuesta canjear el producto entero. Vacío = no se puede canjear |
| `configuracion` (`valor_huellita`) | Cuántos pesos vale una Huellita. Hoy, 100 |
| `movimiento_huellitas` | Cada acreditación, canje, vencimiento o devolución: cliente, tipo, motivo, cantidad, estado, fecha y fecha de vencimiento |
| `item_carrito.huellitas_a_canjear` | Huellitas que se usan en ese producto del carrito |
| `factura_consulta.huellitas_usadas` | Huellitas usadas para pagar la consulta base |

**El saldo no se guarda como un número suelto: se calcula sumando los movimientos.**

```
saldo = acreditaciones CONFIRMADAS
      − canjes RESERVADOS o CONFIRMADOS
      − vencimientos
      − devoluciones
```

Así el cliente ve de dónde salió cada punto, y no hay un número que se pueda desincronizar.

Cada movimiento tiene un tipo y un estado:

| Tipo | Motivos | Cómo afecta al saldo |
|---|---|---|
| `ACREDITACION` | `REGISTRO`, `VACUNA_AL_DIA`, `COMPRA`, `RESENA`, `ADOPCION` | Suma. Guarda `vence_el` (fecha de acreditación + 12 meses) |
| `CANJE` | `CANJE_PRODUCTO`, `CANJE_CONSULTA` | Resta desde que se reserva |
| `VENCIMIENTO` | `VENCIMIENTO` | Resta los puntos vencidos que no se usaron |
| `DEVOLUCION` | `DEVOLUCION_COMPRA` | Resta las Huellitas que había generado la compra devuelta |

| Estado | Significado |
|---|---|
| `RESERVADO` | Canje iniciado, todavía sin pagar o sin finalizar. Ya descuenta del saldo |
| `CONFIRMADO` | Movimiento firme |
| `ANULADO` | Canje cancelado. No cuenta, y los puntos vuelven al saldo |

**Vencimiento:** una tarea diaria, que dispara GitHub Actions, busca las acreditaciones con `vence_el` pasado. Calcula cuántos de esos puntos no se usaron, consumiendo primero los más viejos, y registra un movimiento de `VENCIMIENTO` por esa cantidad.

## Quién hace qué

`movimiento_huellitas` y `configuracion` son de **Backend**: solo Backend escribe en ellas. Los demás módulos leen el saldo directamente de la base, y para cambiarlo llaman a Backend por los endpoints internos, con su token de servicio (ver `SEGURIDAD.md`).

| Endpoint interno de Backend | Quién lo llama | Para qué |
|---|---|---|
| `POST /internal/huellitas/acreditar` | Pacientes, Inventario, Adopciones | Sumar puntos: vacuna al día (Pacientes), compra pagada (Inventario), adopción finalizada (Adopciones). El registro y las reseñas los acredita Backend directamente |
| `POST /internal/huellitas/reservar` | Inventario, Pacientes | Iniciar un canje. Si el saldo no alcanza, responde `409` y no reserva nada |
| `POST /internal/huellitas/confirmar` | Inventario, Pacientes | Confirmar el canje cuando se paga la compra o se finaliza la consulta |
| `POST /internal/huellitas/liberar` | Inventario, Pacientes | Anular el canje si la compra o la consulta se cancela |
| `POST /internal/huellitas/devolucion` | Inventario | Descontar las Huellitas que había generado una compra devuelta |

Cada llamada lleva un `idOperacion` único. Si el mismo pedido llega dos veces (por ejemplo, un reintento del usuario o un aviso repetido de MercadoPago), Backend lo aplica una sola vez.

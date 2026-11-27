# HuellaVet — Guía de uso por rol

Qué puede hacer cada rol y cómo, paso a paso. Todo se hace desde **Mi panel**: el botón con el nombre de la sección abre sus opciones.

- **Sitio:** https://huellavet-frontend.vercel.app (producción) · https://huellavet-la25-dev.vercel.app (desarrollo).
- **Si tarda en abrir:** el servidor (Render, plan gratuito) se apaga si nadie entra en 15 minutos y la primera visita tarda alrededor de un minuto.

## Cuentas de prueba

| Rol | Producción | Local y desarrollo (semillas) |
| --- | --- | --- |
| Veterinario | `vet@huellavet.com` | `vet@prueba.huellavet` |
| Cliente | `sofia.benitez.huellavet@gmail.com` (Sofía) y `tomas.aguirre.huellavet@gmail.com` (Tomás) | `cliente@prueba.huellavet` y `cliente2@prueba.huellavet` |
| Rescatista (aprobado) | `paula.herrera.huellavet@gmail.com` (Paula) | `rescatista@prueba.huellavet` |
| Rescatista (sin aprobar) | se registra uno nuevo desde la web | `rescatista2@prueba.huellavet` |

- **Contraseñas de producción:** se entregan por privado, no están en el repositorio.
- **Contraseñas de local y desarrollo:** están en [`base/semillas/README.md`](https://github.com/romeroppau/PAW--ApiGateway--TPIntegrador/blob/main/base/semillas/README.md) del ApiGateway, junto con los datos que trae cada cuenta.
- **Datos de producción:** las cuentas ya tienen mascotas, turnos, consultas, compras, adopciones y reseñas de los últimos seis meses, para ver las pantallas con datos.

---

## Sin sesión (cualquier visitante)

| Quiero… | Dónde |
| --- | --- |
| Ver la veterinaria, sus servicios, horarios y contacto | Portada y **Contactanos** |
| Ver el catálogo y las promociones | **Tienda** (para comprar hay que iniciar sesión) |
| Ver los animales en adopción | **Adopción** (para postularse hay que iniciar sesión) |
| Leer las reseñas | **Reseñas** |
| Crear una cuenta | **Registrarse** → cliente o rescatista. Llega un código por WhatsApp al teléfono: la cuenta se crea solo con el código correcto |

---

## Cliente

| Quiero… | Cómo |
| --- | --- |
| Cargar a mi mascota | **Mis mascotas → Fichas de mis mascotas → Nueva mascota** (nombre, especie, raza, nacimiento, foto) |
| Ver su historia clínica y sus facturas | **Mis mascotas → Historial clínico y facturas**: cada consulta con su diagnóstico, lo que se usó y el enlace a la factura |
| Ver sus vacunas | **Mis mascotas → Calendario sanitario**: lo dado y lo que le toca, con la fecha |
| Pedir un turno | **Turnos → Pedir turno por WhatsApp**: se coordina con la veterinaria, que lo carga en la agenda. Llega un aviso por la campana y por WhatsApp |
| Ver mis turnos | **Turnos → Mis turnos** (próximos, anteriores y cancelados; los atendidos enlazan a su factura) |
| Ver los descuentos de turnos | **Turnos → Ver descuentos**: los horarios con poca demanda tienen precio rebajado |
| Comprar | **Tienda → Ver catálogo** → **Agregar al carrito** → **Carrito** → **Ir a pagar** → pagar con Mercado Pago (de prueba). Hay 15 minutos para pagar; después se retira en la veterinaria dentro del plazo que dice la compra |
| Cancelar una compra | **Tienda → Mis compras** → la compra → **Cancelar**. Si ya estaba pagada, se devuelve un porcentaje según la anticipación (la pantalla lo dice antes de confirmar) |
| Adoptar | **Adopción → Animales disponibles** → el animal → **Postularme**. En **Mis postulaciones** se ve el estado; si me eligen, me avisan y la veterinaria me da el turno del chequeo |
| Opinar | **Reseñas → Escribir reseña** |
| Ver mis avisos | La campana de arriba o **Notificaciones** |
| Cambiar mis datos | **Mi cuenta → Datos personales** (el teléfono nuevo se verifica por WhatsApp) |

---

## Rescatista

Un rescatista nuevo queda **pendiente** hasta que el veterinario lo aprueba; mientras tanto puede comprar, pero no publicar.

| Quiero… | Cómo |
| --- | --- |
| Cargar un animal rescatado | **Mis publicaciones → Mis mascotas → Nueva mascota** |
| Publicarlo en adopción | **Mis publicaciones → Publicaciones, postulantes y adopciones → Publicar**: elijo la mascota y escribo la descripción. Queda **pendiente** hasta que la veterinaria la aprueba |
| Corregir una publicación rechazada | En la misma pantalla, la publicación muestra el motivo del rechazo → **Editar** → al guardar vuelve a revisión |
| Elegir al adoptante | La publicación → **Postulantes** → **Elegir**. Al elegido le llega el aviso; la veterinaria le da el turno del chequeo |
| Seguir la adopción | La publicación muestra en qué paso está: con postulantes, adoptante elegido, chequeo programado, finalizada |
| Dar de baja una publicación | La publicación → **Dar de baja** (si había chequeo programado, se cancela el turno y se avisa a todos) |
| Comprar y opinar | Igual que el cliente: **Tienda** y **Reseñas** |

---

## Veterinario

| Quiero… | Cómo |
| --- | --- |
| Ver los turnos del día | **Agenda → Agenda de turnos** (cambia de día con las flechas o el calendario) |
| Dar un turno | En la agenda → **Nuevo turno**: el cliente, la mascota, el día, la hora y los motivos (por ejemplo, una vacuna del calendario). Al cliente le llega el aviso |
| Reprogramar o cancelar un turno | En la agenda, el turno → **Reprogramar** o **Cancelar** |
| Atender | En la agenda, el turno → **Atender**: se abre la consulta. Cargo el diagnóstico, las observaciones, los insumos y los productos usados. **Finalizar consulta** descuenta el stock (primero el lote que vence antes, nunca uno vencido), marca las vacunas como dadas y genera la factura |
| Registrar que el cliente pagó la consulta | La factura (desde la agenda, el historial o el reporte de turnos atendidos) → **Registrar el pago**: elijo efectivo, transferencia o tarjeta → **Marcar como pagada** |
| Ver un cliente y sus mascotas | **Mis pacientes → Clientes** o **Ficha de mascotas**; la historia de cada una en **Historial clínico** |
| Manejar el calendario de vacunas | **Mis pacientes → Calendario sanitario**: el plan de cada especie y las dosis de cada mascota |
| Productos de la tienda | **Productos → Productos y stock**: alta, edición, baja, precio, si vence. **Cargar stock** crea un lote (L1, L2…) con su vencimiento y el código del fabricante (opcional) |
| Insumos de las consultas | **Insumos → Insumos y stock**: igual que los productos, con lotes |
| Ver cada entrada y salida de stock | **Productos → Movimientos** e **Insumos → Movimientos** (carga, consulta, venta, cancelación, vencimiento), con su lote y enlace a la consulta o la venta |
| Lotes vencidos | Todos los días a las 9 la tarea diaria da de baja el stock de los lotes vencidos (un movimiento "Vencimiento" por lote) y me avisa por la campana |
| Entregar un pedido de la tienda | **Tienda → Ventas y pedidos para retirar** → el pedido → **Marcar como entregado** |
| Ver reportes y sugerencias | **Reportes**: qué horarios promocionar (con un botón para publicar el descuento), qué productos liquidar por vencimiento, promociones publicadas y turnos atendidos |
| Aprobar rescatistas | **Rescatistas** → el rescatista → **Aprobar** o **Rechazar** (con el motivo) |
| Moderar publicaciones | **Adopción → Publicaciones por revisar** → **Aprobar** o **Rechazar** (con el motivo, que le llega al rescatista) |
| Seguir y cerrar adopciones | **Adopción → Adopciones en curso**: **Programar chequeo** (día y hora) y, después del chequeo, **Completar adopción**: la mascota pasa al adoptante con su historia clínica |
| Cambiar cómo funciona la veterinaria | **Configuración**: horario de atención, duración de los turnos, precio de la consulta, avisos, stock mínimo, plazos de la tienda, política de reintegro, datos de contacto |
| Responder reseñas | **Reseñas → Ver reseñas** → **Responder** |

---

## Avisos automáticos

| Aviso | A quién | Por dónde |
| --- | --- | --- |
| Turno dado, reprogramado o cancelado | Cliente | Campana y WhatsApp |
| Vacuna próxima o atrasada | Cliente | Campana y WhatsApp (tarea diaria) |
| Compra pagada, recordatorio de retiro, cancelación y reintegro | Comprador | Campana y WhatsApp |
| Nuevo pedido para retirar | Veterinario | Campana |
| Publicación nueva, aprobada o rechazada; postulación nueva o cancelada; adoptante elegido | Veterinario, rescatista o cliente, según el caso | Campana |
| Turno del chequeo de adopción | Adoptante y rescatista | Campana y WhatsApp |
| Stock bajo, lotes por vencer, lotes dados de baja por vencimiento, controles de adopción | Veterinario | Campana (tarea diaria) |

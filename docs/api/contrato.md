# Contrato de la API — Autenticación (módulo Backend)

Qué recibe y qué devuelve cada endpoint de autenticación. El frontend y los demás módulos programan contra este documento. Si un endpoint cambia, este archivo se actualiza en el mismo commit.

Las reglas de seguridad detrás de estos endpoints están en [SEGURIDAD.md](../../SEGURIDAD.md).

Las reglas generales que valen para todos los modulos (formato JSON,formato de errores, codigos HTTP y uso del token CSRF) estan en [CONTRATO_API_ApiGateway.md](https://github.com/romeroppau/PAW--ApiGateway--TPIntegrador/blob/main/api/CONTRATO_API_ApiGateway.md). 

## Resumen

| Método y ruta | Quién | Para qué |
|---|---|---|
| `POST /api/auth/registro/cliente` | Público | Crear una cuenta de cliente. Deja la sesión iniciada |
| `POST /api/auth/registro/rescatista` | Público | Crear una cuenta de rescatista en estado `PENDIENTE`. Deja la sesión iniciada |
| `POST /api/auth/login` | Público | Iniciar sesión |
| `POST /api/auth/logout` | Con sesión | Cerrar sesión |
| `GET /api/auth/yo` | Con sesión | Datos del usuario actual, su rol y su token CSRF |
| `GET /api/rescatistas` | Veterinario | Rescatistas por estado de validación (Entrega 5) |
| `POST /api/rescatistas/{id}/aprobar` | Veterinario | Aprobar a un rescatista pendiente: desde ahí puede publicar en adopciones |
| `POST /api/rescatistas/{id}/rechazar` | Veterinario | Rechazar a un rescatista pendiente |
| `GET /api/cuenta` | Con sesión | Mis datos: los datos de la propia cuenta (Entrega 5) |
| `PUT /api/cuenta` | Con sesión | Cambiar el nombre y el teléfono (y la organización, el rescatista) |
| `PUT /api/cuenta/contrasena` | Con sesión | Cambiar la contraseña, con la actual |
| `GET /api/clientes` | Veterinario | Lista de clientes, con buscador, de a 20 (Entrega 5) |
| `GET /api/clientes/{id}` | Veterinario | Ficha de un cliente (o de un rescatista, dueño de mascotas) |

El veterinario no tiene endpoint de registro: se crea con el script `scripts/crear_veterinario.php`.

## Reglas generales

### Sesión

- La sesión viaja en la cookie **`huellavet_sesion`**, que contiene un JWT firmado con RS256 cuyo contenido es `{"id", "rol", "csrf", "exp"}`.
- La cookie se crea así:

  ```
  Set-Cookie: huellavet_sesion=<jwt>; Max-Age=43200; Path=/; Secure; HttpOnly; SameSite=Strict
  ```

  - `Max-Age=43200`: dura 12 horas. No se renueva sola; cuando vence, hay que volver a iniciar sesión.
  - `HttpOnly`: el JavaScript del frontend no la puede leer. El navegador la manda solo en cada pedido.
- Roles posibles: `CLIENTE`, `VETERINARIO`, `RESCATISTA`.

### Token CSRF

- El login, los dos registros y `GET /api/auth/yo` devuelven el campo `csrf` en el JSON. Es el mismo valor que está dentro del JWT.
- El login y los registros no lo llevan, porque todavía no hay sesión.

### La "sesión" que devuelven varios endpoints

El login, los dos registros y `GET /api/auth/yo` devuelven siempre el mismo objeto, así el frontend lo maneja de una sola forma:

```json
{
  "id": 15,
  "nombre": "Ana Pérez",
  "email": "ana@mail.com",
  "rol": "CLIENTE",
  "estado_validacion": null,
  "csrf": "9f2c4e...a71b"
}
```

`estado_validacion` (Entrega 5): para un rescatista, `PENDIENTE`, `APROBADO` o `RECHAZADO`; para los demás roles, `null`. Se lee de la base cada vez, así que en `/yo` se ve el cambio en cuanto el veterinario lo aprueba, sin volver a iniciar sesión. El JWT no lo lleva.

Nunca se devuelve el hash de la contraseña ni otros campos internos.


### Validación de campos

Se aplica en los dos registros. Antes de validar, se quitan los espacios al principio y al final de cada campo, salvo de la contraseña.



| Campo | Regla | Mensaje si falta | Mensaje si es inválido |
|---|---|---|---|
| `nombre` | Texto de 2 a 100 caracteres | "El nombre es obligatorio" | "El nombre debe tener entre 2 y 100 caracteres" |
| `email` | Email válido de hasta 150 caracteres. Se guarda en minúsculas | "El email es obligatorio" | "El email no es válido" |
| `password` | Texto de 8 a 72 caracteres | "La contraseña es obligatoria" | "La contraseña debe tener entre 8 y 72 caracteres" |
| `telefono` | Teléfono argentino (ver abajo) | "El teléfono es obligatorio" | "El teléfono no es válido. Ejemplo: 2323 555555" |
| `organizacion` | Opcional. Texto de hasta 100 caracteres (solo rescatista) | — | "La organización puede tener hasta 100 caracteres" |

**Teléfono:** se quitan espacios, guiones y paréntesis, y se acepta el formato `^(\+?54)?9?\d{10}$`: 10 dígitos (código de área sin el 0 y número sin el 15), con `+54` y `9` opcionales adelante. Se guardan solo los 10 dígitos.

Si un campo llega con un tipo que no es texto (un número, una lista), se considera inválido.

---

## POST /api/auth/registro/cliente

Crea una cuenta con rol `CLIENTE` y deja la sesión iniciada.

**Pedido:**

```json
{
  "nombre": "Ana Pérez",
  "email": "ana@mail.com",
  "password": "unaClave123",
  "telefono": "2323 555555"
}
```

Si el pedido trae otros campos (por ejemplo `rol`), se ignoran.

**201 Created**, con la cookie `huellavet_sesion`:

```json
{
  "id": 15,
  "nombre": "Ana Pérez",
  "email": "ana@mail.com",
  "rol": "CLIENTE",
  "csrf": "9f2c4e...a71b"
}
```

**Errores:**

- `400`, si algún campo falta o es inválido:

  ```json
  {
    "error": "Datos inválidos",
    "campos": {
      "email": "El email no es válido",
      "telefono": "El teléfono es obligatorio"
    }
  }
  ```

- `409`, si el email ya tiene una cuenta:

  ```json
  { "error": "Ya existe una cuenta con ese email" }
  ```

---

## POST /api/auth/registro/rescatista

Crea una cuenta con rol `RESCATISTA` en estado `PENDIENTE` y deja la sesión iniciada. Mientras no lo apruebe el veterinario (Entrega 5), puede iniciar sesión pero no publicar en adopciones.

**Pedido:**

```json
{
  "nombre": "Juan Gómez",
  "email": "juan@mail.com",
  "password": "otraClave456",
  "telefono": "+54 9 2323 444444",
  "organizacion": "Patitas Luján"
}
```

`organizacion` es opcional: se puede omitir o mandar vacío. Si el pedido trae otros campos (por ejemplo `rol` o `estado_validacion`), se ignoran.

**201 Created**, con la cookie `huellavet_sesion`:

```json
{
  "id": 16,
  "nombre": "Juan Gómez",
  "email": "juan@mail.com",
  "rol": "RESCATISTA",
  "csrf": "1b7d0a...c35e"
}
```

**Errores:** los mismos que el registro de cliente (`400` y `409`).

---

## POST /api/auth/login

**Pedido:**

```json
{
  "email": "ana@mail.com",
  "password": "unaClave123"
}
```

**200 OK**, con la cookie `huellavet_sesion`:

```json
{
  "id": 15,
  "nombre": "Ana Pérez",
  "email": "ana@mail.com",
  "rol": "CLIENTE",
  "csrf": "5e8a21...0d4f"
}
```

Cada login genera un token CSRF nuevo.

**Errores:**

- `400`, si falta el email o la contraseña:

  ```json
  { "error": "Ingresá tu email y tu contraseña" }
  ```

- `401`, si el email no existe, la contraseña es incorrecta o la cuenta está bloqueada. El mensaje es siempre el mismo, para no revelar qué emails están registrados:

  ```json
  { "error": "Email o contraseña incorrectos" }
  ```

**Bloqueo:** 5 intentos fallidos en 15 minutos, contados desde el primer fallo, bloquean la cuenta durante 15 minutos. Mientras está bloqueada, incluso la contraseña correcta recibe el `401`. Un login correcto reinicia el contador.

---

## POST /api/auth/logout

**Pedido:** sin cuerpo. Lleva la cookie (la manda el navegador) y el header `X-CSRF-Token`.

**204 No Content**, sin cuerpo. Borra la cookie:

```
Set-Cookie: huellavet_sesion=; Max-Age=0; Path=/; Secure; HttpOnly; SameSite=Strict
```

**Errores:**

- `401`, si no hay sesión o venció: `{ "error": "No autenticado" }`. Para el frontend, el resultado es el mismo: el usuario queda sin sesión.
- `403`, si falta o no coincide el token CSRF: `{ "error": "Token CSRF inválido" }`.

---

## GET /api/auth/yo

**Pedido:** sin cuerpo. Lleva la cookie, que la manda el navegador.

Sirve para saber quién está logueado al cargar cualquier página: con el `rol` se arma el menú, y el `csrf` se recupera después de recargar la página.

**200 OK:**

```json
{
  "id": 15,
  "nombre": "Ana Pérez",
  "email": "ana@mail.com",
  "rol": "CLIENTE",
  "estado_validacion": null,
  "csrf": "5e8a21...0d4f"
}
```

**Errores:**

- `401`, si no hay sesión, venció o el JWT fue alterado: `{ "error": "No autenticado" }`.

---

## Rescatistas (Entrega 5)

La veterinaria valida a los rescatistas que se registran. Solo uno `APROBADO` publica en adopciones (lo controla el módulo Adopciones leyendo la base). La decisión es una sola: un rescatista ya aprobado o rechazado no cambia de estado. El circuito completo está en `docs/api/contrato-adopciones.md` del repo Adopciones.

Las tres rutas exigen sesión de veterinario: sin sesión, `401`; con otro rol, `403`. Las dos de POST exigen además el header `X-CSRF-Token` (sin él, `403`).

### Forma del rescatista

```json
{
  "id": 105,
  "nombre": "Rescatista Pendiente",
  "email": "rescatista2@prueba.huellavet",
  "telefono": "1155550105",
  "organizacion": "Patitas al Rescate",
  "estado_validacion": "PENDIENTE"
}
```

`organizacion` puede ser `null`.

### GET /api/rescatistas

Parámetro opcional `estado`: `PENDIENTE` (por defecto), `APROBADO`, `RECHAZADO` o `TODOS`. Ordenados por id, los más viejos primero (hasta 100).

- `200`: `{ "rescatistas": [ ... ] }`
- `400`: `estado` no es uno de los cuatro.

### POST /api/rescatistas/{id}/aprobar y POST /api/rescatistas/{id}/rechazar

Sin cuerpo.

- `200`: `{ "rescatista": { ... } }` con el estado nuevo.
- `404`: `{ "error": "Rescatista no encontrado" }` si no existe, no es un rescatista o el id no es un número.
- `409`: `{ "error": "El rescatista ya fue aprobado" }` (o "rechazado") si no estaba `PENDIENTE`.

Cada validación queda en el log: qué rescatista, qué estado y qué veterinario.

Además, la rescatista recibe un aviso en la campana (tabla `notificacion`, tipo `CUENTA`, canal `WEB`, `id_operacion` `cuenta-{id}-{APROBADO|RECHAZADO}`). Si no se puede guardar el aviso, la validación queda hecha igual y el error va al log.

---

## Mis datos (Entrega 5)

Cada usuario (cliente, rescatista o veterinaria) ve y cambia **los datos de su propia cuenta**: el usuario sale del JWT, nunca de la ruta, así que nadie puede cambiar los de otro. El **email no se cambia** (es el usuario para entrar). Los `PUT` exigen `X-CSRF-Token`. Sin sesión, `401`.

### Forma de una cuenta

```json
{ "id": 102, "nombre": "Ana Pérez", "email": "ana@ejemplo.com", "telefono": "2323555555", "rol": "CLIENTE", "organizacion": null }
```

- `telefono`: los 10 dígitos guardados (sin +54 ni 9), o `null` si no tiene (la veterinaria puede no tenerlo).
- `organizacion`: solo la del rescatista; para los demás, `null`.

### GET /api/cuenta

`200` con la cuenta.

### PUT /api/cuenta

```json
{ "nombre": "Ana Pérez", "telefono": "2323 555555", "organizacion": "Patitas Luján" }
```

- `nombre` y `telefono`: las mismas reglas y mensajes que en el registro (tabla de arriba). El teléfono es **obligatorio para cliente y rescatista** y **opcional para la veterinaria** (vacío = sin teléfono).
- `organizacion`: solo la usa el rescatista (opcional, hasta 100 caracteres; vacía = sin organización). Para los demás se ignora.
- `email`: si viene, se ignora.

`200` con la cuenta actualizada. `400 { "error": "Datos inválidos", "campos": { ... } }` con un mensaje por campo. El nombre nuevo se ve enseguida en el encabezado (`/api/auth/yo` lo lee de la base).

### PUT /api/cuenta/contrasena

```json
{ "actual": "mi-contraseña-de-hoy", "nueva": "mi-contraseña-nueva", "repetir": "mi-contraseña-nueva" }
```

- `actual`: obligatoria ("Escribí tu contraseña actual") y tiene que ser la guardada ("La contraseña actual no es correcta"). Así, con una sesión abierta en otra compu, nadie la cambia sin saberla.
- `nueva`: las mismas reglas que `password` en el registro, y distinta de la actual ("La contraseña nueva tiene que ser distinta de la actual").
- `repetir`: igual a la nueva ("Las contraseñas nuevas no coinciden").

`200 { "mensaje": "Tu contraseña se cambió" }`. La sesión sigue abierta; la nueva se usa al próximo inicio de sesión. Si algo está mal, `400` con el campo (también si la actual no es correcta: no es un `401`, la sesión no se cierra).

## Clientes (Entrega 5)

Para la veterinaria: la pantalla **Clientes** (Panel → Mis pacientes) y la **ficha de un cliente**, a la que se llega también desde el nombre del dueño en Mascotas y en la Agenda. **Solo lectura**: cada uno cambia sus datos desde "Mis datos". Sin sesión, `401`; otro rol, `403`.

### GET /api/clientes?buscar=ana&pagina=1

Los usuarios con rol `CLIENTE`, por nombre, **de a 20**.

- `buscar` (opcional, hasta 100 caracteres): por nombre, email o teléfono (cualquier parte, sin importar mayúsculas). `%` y `_` se buscan como letras.
- `pagina` (desde 1, por defecto 1): `400` si no es un entero desde 1.

`200`:

```json
{ "datos": [ { "id": 102, "nombre": "Ana Pérez", "email": "ana@ejemplo.com", "telefono": "2323555555", "rol": "CLIENTE", "organizacion": null } ],
  "pagina": 1, "por_pagina": 20, "hay_mas": false }
```

### GET /api/clientes/{id}

La ficha (forma de una cuenta) de un **cliente o un rescatista** (los rescatistas también tienen mascotas a cargo). `404 { "error": "Cliente no encontrado" }` si no existe o es una veterinaria. Las mascotas del cliente no vienen acá (son de Pacientes): la pantalla las pide a `GET /api/pacientes/mascotas`.

## Configuración (Entrega 5)

Los parámetros que la veterinaria cambia desde su panel (pantalla `panel/configuracion.html`). Qué parámetros hay, su tipo, su rango y su valor por defecto están en `src/CatalogoConfiguracion.php`. La tabla `configuracion` (migración 014 del Gateway) guarda solo los que se cambiaron. Los demás módulos los leen con `comun/configuracion.php`, y si un parámetro no está guardado usan su valor por defecto.

| Clave | Tipo | Por defecto | Rango | Lo usa |
|---|---|---|---|---|
| `hora_apertura` | HORA | `08:00` | 06:00 a 22:00 | Pacientes (turnos) |
| `hora_cierre` | HORA | `20:00` | 07:00 a 23:59, después de la apertura | Pacientes (turnos) |
| `dias_aviso_vacuna` | ENTERO | 7 | 1 a 30 días | Reportes (motor) |
| `dias_control_adopcion_1` | ENTERO | 30 | 7 a 180 días | Reportes (motor) |
| `dias_control_adopcion_2` | ENTERO | 90 | 14 a 365 días, después del primero | Reportes (motor) |
| `limite_stock_bajo` | ENTERO | 10 | 0 a 1000 unidades | Reportes (motor y reporte de stock) |
| `dias_liquidar` | ENTERO | 30 | 1 a 180 días | Reportes (qué liquidar) |
| `minutos_para_pagar` | ENTERO | 15 | 5 a 60 minutos | Inventario (tienda) |
| `dias_habiles_para_retirar` | ENTERO | 5 | 2 a 15 días hábiles | Inventario (tienda) |
| `hora_avisos_tienda` | HORA | `09:00` | 07:00 a 20:00 | Inventario (tienda) |
| `dia_primer_recordatorio` | ENTERO | 2 | 1 a 13 días hábiles, al menos 2 antes del último día para retirar | Inventario (tienda) |
| `porcentaje_reintegro_anticipado` | ENTERO | 75 | 0 a 100 % | Inventario (tienda: reintegro si el comprador cancela con anticipación) |
| `porcentaje_reintegro_ultimo_momento` | ENTERO | 50 | 0 a 100 %, no mayor que el anterior | Inventario (tienda: reintegro si cancela a último momento) |
| `horas_anticipacion_reintegro` | ENTERO | 48 | 1 a 168 horas | Inventario (tienda: qué es "con anticipación") |

Los de la tienda valen para las compras nuevas: cada compra guarda sus plazos y su política de reintegro al confirmarse (migraciones 015 y 017 del Gateway).

Las dos rutas exigen sesión de veterinario (sin sesión, `401`; otro rol, `403`). El PUT exige además `X-CSRF-Token`.

### GET /api/configuracion

- `200`: `{ "parametros": [ ... ] }`, en el orden de la tabla de arriba. Cada uno:

```json
{
  "clave": "hora_apertura", "grupo": "TURNOS", "nombre_grupo": "Turnos",
  "nombre": "Apertura", "ayuda": "Desde qué hora se pueden dar turnos.",
  "tipo": "HORA", "unidad": null, "minimo": "06:00", "maximo": "22:00",
  "defecto": "08:00", "valor": "09:00", "modificado": true,
  "actualizado_en": "2026-11-20 10:15:00", "actualizado_por": "Veterinaria de Prueba"
}
```

En los `ENTERO`, `minimo`, `maximo`, `defecto` y `valor` son números. Si `modificado` es `false`, `valor` es el de por defecto y `actualizado_en` y `actualizado_por` son `null`.

### PUT /api/configuracion

```json
{ "valores": { "hora_apertura": "09:00", "dias_aviso_vacuna": 5 } }
```

Solo los que cambian. Se guardan todos o ninguno. Mandar el valor por defecto vuelve el parámetro a como estaba (se borra la fila).

- `200`: la misma respuesta que el GET, ya con los valores nuevos.
- `400`: `{ "error": "Revisá los valores marcados", "campos": { "hora_cierre": "El cierre tiene que ser después de la apertura" } }`. Una clave que no existe, un valor de otro tipo o fuera de rango, la apertura después del cierre o el segundo control antes del primero (el error va en el campo que se cambió). Sin `valores`: el error va en `campos.valores`.

Cada cambio queda en el log: qué veterinaria y qué claves.

---

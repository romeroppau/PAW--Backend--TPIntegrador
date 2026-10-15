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
  "csrf": "9f2c4e...a71b"
}
```

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
  "csrf": "5e8a21...0d4f"
}
```

**Errores:**

- `401`, si no hay sesión, venció o el JWT fue alterado: `{ "error": "No autenticado" }`.

---

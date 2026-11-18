# Contrato de la API: Notificaciones (módulo Backend)

Qué recibe y qué devuelve cada endpoint de notificaciones. La campana del sitio programa contra este documento, y Adopciones y Reportes contra el endpoint interno. Si un endpoint cambia, este archivo se actualiza en el mismo commit.

Las reglas generales (formato JSON, errores, cookie de sesión y token CSRF) están en el [contrato del ApiGateway](https://github.com/romeroppau/PAW--ApiGateway--TPIntegrador/blob/main/api/CONTRATO_API_ApiGateway.md) y en el [contrato de autenticación](contrato.md).

## Resumen

| Método y ruta | Quién | Para qué |
|---|---|---|
| `POST /internal/notificaciones` | Otros módulos (token de servicio) | Crear un aviso. Repetido: se ignora |
| `GET /api/notificaciones` | Usuario con sesión | Sus avisos, los no leídos primero |
| `POST /api/notificaciones/{id}/leida` | Usuario con sesión | Marcar un aviso propio como leído |

## La notificación

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | número | Identificador |
| `tipo` | texto | `VACUNA`, `CONTROL_ADOPCION`, `STOCK_BAJO`, `PROMOCION`, `CUENTA` o `POSTULACION` |
| `canal` | texto | `WEB` o `WHATSAPP` |
| `mensaje` | texto | Hasta 500 caracteres |
| `fecha` | texto | Fecha programada, `AAAA-MM-DD HH:MM:SS` (hora de Argentina) |
| `leida` | booleano | `true` si el usuario ya la marcó |

---

## POST /internal/notificaciones

Endpoint de proceso a proceso: Nginx lo bloquea desde afuera y exige token. El frontend nunca lo llama.

`http://127.0.0.1:9001/internal/notificaciones`

**Header obligatorio:** `X-Servicio-Token: <INTERNAL_TOKEN>`

**Pedido:**

```json
{
  "usuario_id": 15,
  "tipo": "VACUNA",
  "canal": "WHATSAPP",
  "mensaje": "A Rocco le toca la vacuna antirrábica el 15/11",
  "fecha_programada": "2026-11-13 10:00:00",
  "id_operacion": "vacuna-proxima-412"
}
```

| Campo | Regla |
|---|---|
| `usuario_id` | Entero mayor que 0, de un usuario existente |
| `tipo` | Uno de los tipos de arriba |
| `canal` | `WEB` o `WHATSAPP` |
| `mensaje` | Texto no vacío de hasta 500 caracteres |
| `fecha_programada` | `AAAA-MM-DD HH:MM:SS`, fecha válida |
| `id_operacion` | Texto de hasta 100 caracteres, único por aviso |

Los tipos tienen que ser los correctos: un número como texto (`"15"`) se rechaza.

**Respuestas:**

| Código | Cuerpo | Cuándo |
|---|---|---|
| `200` | `{ "aplicado": true }` | Aviso guardado |
| `200` | `{ "aplicado": false }` | Ya existía ese `id_operacion`: se ignora, no se duplica |
| `400` | `{ "error": "Datos de solicitud inválidos" }` | Cuerpo mal formado |
| `401` | `{ "error": "No autorizado" }` | Falta el token o no coincide |
| `404` | `{ "error": "Usuario no encontrado" }` | El `usuario_id` no existe |

**Reintentos:** el `id_operacion` identifica al aviso. Quien llama lo arma siempre igual (por ejemplo `vacuna-proxima-{evento}`), así la tarea diaria puede correr dos veces el mismo día sin repetir avisos. Reintentar es seguro.

---

## GET /api/notificaciones

Los avisos del usuario de la sesión (hasta 100), no leídos primero y después los más recientes. El usuario sale del JWT.

**200 OK:**

```json
{
  "no_leidas": 1,
  "datos": [
    {
      "id": 1,
      "tipo": "VACUNA",
      "canal": "WEB",
      "mensaje": "A Rocco le toca la vacuna antirrábica el 15/11",
      "fecha": "2026-11-13 10:00:00",
      "leida": false
    }
  ]
}
```

`no_leidas` es el número que muestra la campana.

**Errores:** `401 { "error": "No autenticado" }` si no hay sesión.

---

## POST /api/notificaciones/{id}/leida

Marca un aviso como leído. Exige el header `X-CSRF-Token`. Marcar uno ya leído también responde `200`.

**200 OK:** `{ "id": 1, "leida": true }`

**Errores:**

- `401` sin sesión; `403 { "error": "Token CSRF inválido" }` si falta o no coincide el token.
- `404 { "error": "Notificación no encontrada" }` si no existe **o es de otro usuario** (el mismo mensaje, para no revelar que existe).
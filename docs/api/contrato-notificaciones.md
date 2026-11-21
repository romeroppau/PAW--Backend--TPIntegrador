# Contrato de notificaciones (Backend · rol 1)

Avisos del sistema: la campana del sitio (canal `WEB`) y el envío real por WhatsApp (canal `WHATSAPP`).
Los crean otros módulos (Adopciones, Reportes, Inventario) con la llamada interna; Backend los guarda,
los muestra y los envía.

Tabla `notificacion` (migraciones 008, 011 y 018 del Gateway).

## Resumen

| Método | Ruta | Quién | Para qué |
|---|---|---|---|
| POST | `/internal/notificaciones` | Otro módulo (`X-Servicio-Token`) | Crear un aviso |
| GET | `/api/notificaciones` | Cualquier usuario con sesión | La campana |
| POST | `/api/notificaciones/{id}/leida` | El dueño del aviso (con CSRF) | Marcarlo leído |
| POST | `/api/notificaciones/tareas/enviar` | GitHub Actions (`X-Tarea-Token`), cada 15 min | Enviar los WhatsApp pendientes |

## POST /internal/notificaciones

Solo desde `127.0.0.1:9001` (nginx bloquea `/internal/` desde afuera: 404).

```
POST http://127.0.0.1:9001/internal/notificaciones
X-Servicio-Token: <INTERNAL_TOKEN>
{ "usuario_id": 15, "tipo": "VACUNA", "canal": "WHATSAPP",
  "mensaje": "a Rocco le toca la vacuna antirrábica el 15/11",
  "fecha_programada": "2026-11-13 10:00:00", "id_operacion": "vacuna-proxima-412" }
```

| Campo | Regla |
|---|---|
| `usuario_id` | entero > 0 (no `"15"`) y el usuario existe |
| `tipo` | `VACUNA`, `CONTROL_ADOPCION`, `STOCK_BAJO`, `PROMOCION`, `CUENTA`, `POSTULACION`, `COMPRA` |
| `canal` | `WEB` o `WHATSAPP`. Un aviso por los dos canales son **dos llamadas** con distinto `id_operacion` |
| `mensaje` | texto, hasta 500 caracteres |
| `fecha_programada` | `Y-m-d H:i:s`, hora de Argentina. El WhatsApp no sale antes de esa hora |
| `id_operacion` | hasta 100 caracteres, único |

Respuestas:

* `200 { "aplicado": true }`: se guardó.
* `200 { "aplicado": false }`: el `id_operacion` ya existía (aviso repetido). No es un error.
* `400` datos inválidos · `401` token · `404` usuario inexistente.

## GET /api/notificaciones

Las del usuario de la sesión, **solo de canal WEB**, las no leídas primero (hasta 100).

```json
{ "no_leidas": 2,
  "datos": [ { "id": 901, "tipo": "VACUNA", "canal": "WEB", "mensaje": "…",
               "fecha": "2026-11-21 10:00:00", "leida": false } ] }
```

`401` sin sesión.

## POST /api/notificaciones/{id}/leida

Con `X-CSRF-Token`. `200 { "id": 901, "leida": true }` (marcarla dos veces también da 200).
`401` sin sesión · `403` sin CSRF · `404` si no existe, no es del usuario o no es de canal WEB
(mismo 404, para no revelar que existe).

## POST /api/notificaciones/tareas/enviar

Header `X-Tarea-Token: <TAREAS_TOKEN>` (comparado con `hash_equals`). Sin token o con uno incorrecto: `401`.

Toma hasta 20 avisos `WHATSAPP` con `enviada_en` en NULL, `fecha_programada` ya cumplida (y de los últimos
3 días) y menos de 5 intentos. Los manda con la plantilla aprobada:

* plantilla `WHATSAPP_PLANTILLA` (`aviso_huellavet`), idioma `WHATSAPP_IDIOMA` (`es_AR`);
* `{{1}}` = nombre del usuario, `{{2}}` = el mensaje (sin saltos de línea: Meta los rechaza).

```json
{ "enviadas": 3, "fallidas": 1, "reintentar": 0, "descartadas": 1, "simulado": false }
```

Qué pasa con cada aviso:

| Resultado | Qué se hace | Ejemplos |
|---|---|---|
| Enviado | `enviada_en` = ahora | Meta respondió 2xx |
| Pasajero | Se anota `ultimo_error`; la próxima corrida lo reintenta (hasta 5 intentos) | Timeout, 5xx, límite de mensajes (429) |
| Permanente | `ultimo_error` y se agotan los intentos: queda solo en la web | Sin teléfono, teléfono inválido, número no verificado en la cuenta de prueba (131030) |
| Configuración | La corrida **se corta**, no gasta intentos y responde `"error_configuracion": "<motivo>"` | Falta `WHATSAPP_TOKEN`/`WHATSAPP_NUMERO_ID`, token vencido (190), plantilla inexistente o pausada (1320xx) |

Detalles:

* **Teléfono:** se normaliza a `549` + 10 dígitos (acepta `+54 9 11 …`, `011 15 …`, `0351 15 …`, etc.).
  Si Meta responde 131030 se reintenta una vez sin el 9 (`54` + 10 dígitos): la lista de números
  verificados de Meta a veces guarda los celulares argentinos así.
* **Sin repetir:** cada aviso se reserva sumándole un intento antes de enviarlo
  (`UPDATE … WHERE intentos = <leído>`); si dos corridas se superponen, solo una lo manda.
* **Modo simulado:** con `ENTORNO` = `local` o `ci` no sale a internet y todo teléfono válido cuenta como enviado.
* **Log:** nunca el token ni el teléfono; sí el código HTTP y el código de error de Meta.
* **Versión de la API:** `v25.0` por defecto (la v20.0 venció el 24/09/2026); se cambia con `WHATSAPP_API_VERSION`.

Para ver por qué no llegó un WhatsApp:

```sql
SELECT id, usuario_id, tipo, intentos, ultimo_error, fecha_programada
FROM notificacion WHERE canal = 'WHATSAPP' AND enviada_en IS NULL ORDER BY id DESC;
```

## Variables (solo Backend)

`INTERNAL_TOKEN`, `TAREAS_TOKEN`, `WHATSAPP_TOKEN`, `WHATSAPP_NUMERO_ID` (el *identificador del número*
de Meta, no el número), `WHATSAPP_PLANTILLA`, `WHATSAPP_IDIOMA` y, opcional, `WHATSAPP_API_VERSION`.

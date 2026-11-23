# Contrato de la API — Reseñas (módulo Backend, Entrega 5)

Las reseñas de la veterinaria: **estrellas (1 a 5) y texto**. El autor (nombre e inicial del apellido) y la fecha los pone el sistema.

- Las escriben **clientes y rescatistas**, todas las que quieran, y **se publican enseguida**.
- Cada autor puede **borrar las suyas** (se borran con su respuesta).
- La **veterinaria las responde** (una respuesta por reseña, que puede corregir), pero **no las borra**.
- Las ve **cualquiera, sin sesión**: la página "Reseñas" y la portada (las 3 más nuevas de 4 o 5 estrellas).
- Por cada reseña nueva, a cada veterinaria le llega un aviso en la **campana** (solo web, tipo `RESENA`).

Tabla `resena` (migración 019 del Gateway).

## Resumen

| Método y ruta | Quién | Para qué |
|---|---|---|
| `GET /api/resenas` | Cualquiera (sin sesión) | Las reseñas, con el resumen (total, promedio y por estrellas), de a 3 |
| `POST /api/resenas` | Cliente o rescatista (con CSRF) | Escribir una reseña |
| `DELETE /api/resenas/{id}` | Su autor (con CSRF) | Borrar una reseña propia |
| `PUT /api/resenas/{id}/respuesta` | Veterinario (con CSRF) | Responder (o corregir la respuesta) |

## Forma de una reseña

```json
{ "id": 101, "estrellas": 5, "texto": "Atendieron a Firulais con mucha paciencia…",
  "autor": "Cliente P.", "fecha": "2026-11-03 10:15:00", "propia": false,
  "respuesta": { "texto": "¡Gracias por tu reseña!…", "fecha": "2026-11-04 09:00:00" } }
```

- `autor`: el nombre y la inicial del apellido ("Ana Pérez" → "Ana P."), por privacidad. Se lee del usuario: si cambia su nombre en "Mis datos", se actualiza.
- `propia`: `true` si la escribió quien está mirando (con sesión), para mostrarle "Borrar".
- `respuesta`: la de la veterinaria, o `null`. Se muestra como "Respuesta de HuellaVet".

## GET /api/resenas?orden=recientes&estrellas=5&minimo=4&pagina=1

Parámetros (opcionales):

- `orden`: `recientes` (por defecto), `mejores` (de más a menos estrellas) o `peores` (de menos a más). A igual cantidad de estrellas, las más nuevas primero.
- `estrellas` (1 a 5): solo las de esa cantidad de estrellas.
- `minimo` (1 a 5): solo las de esa cantidad o más (la portada pide `minimo=4`).
- `pagina` (desde 1): **de a 3**; "Ver más" pide la siguiente mientras `hay_mas` sea `true`.

`200`:

```json
{ "resumen": { "total": 5, "promedio": 4.4, "por_estrellas": { "5": 3, "4": 1, "3": 1, "2": 0, "1": 0 } },
  "datos": [ { "id": 105, "estrellas": 5, "texto": "…", "autor": "Cliente D.", "fecha": "…", "propia": false, "respuesta": null } ],
  "pagina": 1, "por_pagina": 3, "hay_mas": true }
```

- `resumen` es de **todas** las reseñas (no cambia con los filtros). `promedio`: con un decimal, o `null` si no hay ninguna.
- `400 { "error": "Datos inválidos", "campos": { ... } }` si `orden`, `estrellas`, `minimo` o `pagina` no son válidos.

## POST /api/resenas

```json
{ "estrellas": 5, "texto": "Excelente atención." }
```

- `estrellas`: entero de 1 a 5 ("Elegí de 1 a 5 estrellas").
- `texto`: obligatorio ("Escribí tu opinión"), hasta 1000 caracteres.

`201` con la reseña. `400` con un mensaje por campo · `401` sin sesión · `403` sin CSRF o si es una veterinaria.

## DELETE /api/resenas/{id}

`204` sin cuerpo. `404 { "error": "Reseña no encontrada" }` si no existe **o es de otro** (el mismo 404, para no revelar de quién es) · `401` · `403`.

## PUT /api/resenas/{id}/respuesta

```json
{ "texto": "¡Gracias por tu reseña!" }
```

`200` con la reseña ya respondida. Si ya tenía respuesta, la reemplaza. `400` si el texto está vacío ("Escribí la respuesta") o tiene más de 1000 caracteres · `404` si la reseña no existe · `401` · `403` (sin CSRF o si no es veterinaria).

# HuellaVet — Backend

Módulo **Backend** de **HuellaVet**, la aplicación de gestión integral de una veterinaria: cuentas, sesión, notificaciones (campana y WhatsApp), configuración de la veterinaria, reseñas y validación de rescatistas.

Trabajo Práctico Integrador de **Programación en Ambiente Web (PAW)** — UNLu, 2026 — Grupo **La 25**.

| Entorno | Sitio | API |
| --- | --- | --- |
| Producción | https://huellavet-frontend.vercel.app | https://paw-apigateway-tpintegrador.onrender.com/api/backend/salud |
| Desarrollo | https://huellavet-la25-dev.vercel.app | https://paw-apigateway-tpintegrador-dev.onrender.com/api/backend/salud |

## Autores

| Integrante | Legajo |
| --- | --- |
| Ana Paula Romero | 195388 |
| Maria Trinidad Lopez | 197958 |
| Valentino Aimale | 197961 |
| Cristian Tomás Anito | 158887 |

---

## Documentación

Este repositorio guarda la documentación general del proyecto (la de todos los módulos).

| Documento | Qué tiene |
| --- | --- |
| [Guía de uso por rol](./docs/GUIA-DE-USO.md) | Qué puede hacer cada rol (cliente, rescatista, veterinario), con las cuentas de prueba y los pasos de cada tarea |
| [Seguridad](./SEGURIDAD.md) | Las medidas de seguridad por capa: red, Gateway, aplicación, base de datos y sistema operativo |
| [Arquitectura](./docs/arquitectura/ARQUITECTURA.md) | Los módulos, cómo se comunican y cómo se despliegan ([PDF](./docs/arquitectura/Arquitectura_HuellaVet_v3.pdf)) |
| [Modelo de datos (DER)](./docs/base-de-datos/DER.md) | Las tablas y sus relaciones ([PDF](./docs/base-de-datos/DER_HuellaVet.pdf)) |
| [Modelo de datos en DBML](./docs/base-de-datos/modelo-produccion.dbml) | El modelo como está hoy en producción (se abre en [dbdiagram.io](https://dbdiagram.io)) |
| [Modelo de objetos (UML)](./docs/UML/MODELO_DE_OBJETOS.md) | El diagrama de clases actualizado ([PlantUML](./docs/UML/modelo-de-objetos.puml), [versión de la propuesta](./docs/UML/Diagrama_de_clases_HuellaVet_v3.pdf)) |
| [Contrato de la API de Backend](./docs/api/contrato.md) | Los endpoints de sesión, mis datos, verificación del WhatsApp, clientes, rescatistas y configuración |
| [Contrato de notificaciones](./docs/api/contrato-notificaciones.md) | Cómo los módulos dejan avisos y cómo se mandan por WhatsApp |
| [Contrato de reseñas](./docs/api/contrato-resenas.md) | Las reseñas de la portada |
| [Tecnología](./TECNOLOGíA.md) | Qué usamos y por qué |
| [Huellitas](./HUELLITAS.md) | El diseño del programa de puntos (no llegó a implementarse: ver [Próximas funcionalidades](./proximas-funcionalidades.md)) |
| [Propuesta](./docs/propuesta/) | La propuesta original del trabajo |

Los demás módulos tienen su propio contrato en su repositorio (ver [Repositorios](#repositorios)).

---

## Qué hace este módulo

| Área | Endpoints | Quién |
| --- | --- | --- |
| Registro e inicio de sesión | `POST /api/auth/registro/codigo`, `/registro/cliente`, `/registro/rescatista`, `/login`, `/logout`, `GET /api/auth/yo` | Público / cualquier sesión |
| Mis datos | `GET` y `PUT /api/cuenta`, `PUT /api/cuenta/contrasena`, `POST /api/cuenta/telefono/codigo`, `POST /api/cuenta/telefono` | Cada usuario, la suya |
| Clientes | `GET /api/clientes`, `GET /api/clientes/{id}` | Veterinario |
| Rescatistas | `GET /api/rescatistas`, `GET /api/rescatistas/{id}`, `POST .../aprobar`, `POST .../rechazar` | Veterinario |
| Reseñas | `GET /api/resenas` (público), `POST`, `DELETE /api/resenas/{id}`, `PUT /api/resenas/{id}/respuesta` | Clientes y rescatistas escriben; el veterinario responde |
| Notificaciones | `GET /api/notificaciones`, `/historial`, `POST /leidas`, `POST /{id}/leida` | Cada usuario, las suyas |
| Avisos de los otros módulos | `POST /internal/notificaciones` | Solo los módulos (token de servicio) |
| Envío de WhatsApp pendientes | `POST /api/notificaciones/tareas/enviar` | La tarea programada (token de tareas) |
| Configuración de la veterinaria | `GET` y `PUT /api/configuracion`, `GET /api/configuracion/contacto` (público) | Veterinario |
| Salud | `GET /api/backend/salud` | Público |

- **Registro con WhatsApp verificado:** el cliente y el rescatista reciben un código por WhatsApp y la cuenta se crea solo si el código es correcto. El rescatista queda pendiente hasta que el veterinario lo aprueba.
- **Sesión:** JWT firmado con RS256 en una cookie `HttpOnly`, más un token CSRF para los pedidos que cambian datos. Con 5 intentos fallidos en 15 minutos, la cuenta se bloquea 15 minutos.
- **Notificaciones:** todos los módulos dejan sus avisos acá con un `id_operacion` único (un reintento no lo duplica). Los de canal `WEB` aparecen en la campana; los de `WHATSAPP` se mandan en el momento y, si fallan, los reintenta la tarea programada.

---

## Cómo correrlo

El módulo no se levanta solo: corre adentro del contenedor que arma el repo **ApiGateway**, junto con Nginx, el código común (`comun/`) y los demás módulos. Todo se levanta desde ApiGateway, con Docker. Los pasos completos (variables de entorno, migraciones, semillas, cuentas de prueba) están en el [README del ApiGateway](https://github.com/romeroppau/PAW--ApiGateway--TPIntegrador#cómo-correrlo). En resumen:

```bash
git clone --recurse-submodules https://github.com/romeroppau/PAW--ApiGateway--TPIntegrador.git
cd PAW--ApiGateway--TPIntegrador
cp .env.example .env                                   # completarlo (ver el README del Gateway)
docker compose up -d --build
docker compose --profile tools run --rm --build migrator
docker compose --profile tools run --rm sembrador      # datos y cuentas de prueba (solo local y dev)
curl http://localhost:10000/api/backend/salud          # {"estado":"funciona","modulo":"Backend"}
```

Sin las semillas, el veterinario se crea con un script (si ya existe, no hace nada):

```bash
docker compose exec app sh -c 'DB_USER="$BACKEND_DB_USER" DB_PASSWORD="$BACKEND_DB_PASSWORD" VET_NOMBRE="Nombre" VET_EMAIL="vet@huellavet.com" VET_PASSWORD="una-contraseña-segura" php /app/Backend/scripts/crear_veterinario.php'
```

> **En Render:** si nadie entró en los últimos 15 minutos, el servicio se apaga y la primera visita tarda alrededor de un minuto. Conviene abrir el sitio unos minutos antes de mostrarlo.

### Pruebas

PHPUnit, desde la carpeta `Backend/` (adentro de ApiGateway):

```bash
composer install
vendor/bin/phpunit --testdox
```

Con este repo suelto (fuera de ApiGateway), hay que indicar dónde está `comun/`:

```bash
COMUN_DIR="ruta/a/PAW--ApiGateway--TPIntegrador/comun" vendor/bin/phpunit --testdox
```

Las pruebas de la base usan un MySQL de prueba local, nunca Aiven. Si no está levantado, esas se saltean y las demás corren igual:

```bash
docker compose --profile local up -d db                # desde ApiGateway
export TEST_DB_PASSWORD="$(grep '^MYSQL_ROOT_PASSWORD=' .env | cut -d= -f2-)"
```

En GitHub corren solas en cada push y en cada pull request (`.github/workflows/pruebas.yml`); hay que pasarlas para mergear a `dev` y a `main`.

### Cómo está organizado el código

| Archivo | Qué hace |
| --- | --- |
| `src/Index.php` | Punto de entrada: carga el código, registra las rutas y responde 500 si algo falla de forma inesperada |
| `src/AuthControlador.php`, `Sesion.php`, `Jwt.php` | Registro, inicio y cierre de sesión; el JWT, la cookie y el CSRF |
| `src/VerificacionControlador.php`, `VerificacionRepositorio.php`, `VerificacionWhatsApp.php` | Los códigos por WhatsApp para registrarse y para cambiar el teléfono |
| `src/WhatsAppCliente.php` | El envío por WhatsApp (simulado en `local` y `ci`) |
| `src/CuentaControlador.php` | Mis datos y cambio de contraseña |
| `src/ClientesControlador.php`, `RescatistaControlador.php` | La lista y la ficha de clientes; la validación de rescatistas |
| `src/NotificacionControlador.php`, `NotificacionRepositorio.php` | La campana, los avisos de los otros módulos y el envío de los WhatsApp pendientes |
| `src/ConfiguracionControlador.php`, `ConfiguracionRepositorio.php`, `CatalogoConfiguracion.php` | Los parámetros que la veterinaria cambia desde su panel (horarios, plazos, políticas, contacto) |
| `src/ResenaControlador.php`, `ResenaRepositorio.php`, `ValidadorResena.php` | Las reseñas |
| `src/UsuarioRepositorio.php`, `ValidadorRegistro.php` | La tabla `usuario`, el bloqueo por intentos fallidos y las validaciones del registro |
| `src/Cliente.php`, `Veterinario.php`, `Rescatista.php` | Los tres tipos de usuario, como en el diagrama de clases |
| `scripts/crear_veterinario.php` | Alta del veterinario |
| `tests/` | Las pruebas automáticas |

Las tablas se crean con las migraciones de `base/` del repo ApiGateway.

---

## Repositorios

| Repositorio | Qué tiene |
| --- | --- |
| [Frontend](https://github.com/romeroppau/PAW--Frontend--TPIntegrador) | La interfaz web: sitio público, tienda, adopciones y el panel de cada rol |
| [ApiGateway](https://github.com/romeroppau/PAW--ApiGateway--TPIntegrador) | Nginx (la única entrada pública), el código común, las migraciones, las semillas y el despliegue |
| [Backend](https://github.com/romeroppau/PAW--Backend--TPIntegrador) | Este repositorio |
| [Pacientes](https://github.com/romeroppau/PAW--Pacientes--TPIntegrador) | Mascotas, turnos, consultas, historia clínica y calendario sanitario |
| [Inventario](https://github.com/romeroppau/PAW--Inventario--TPIntegrador) | Productos, insumos, lotes, movimientos, tienda y pagos con Mercado Pago |
| [Adopciones](https://github.com/romeroppau/PAW--Adopciones--TPIntegrador) | Publicaciones, moderación, postulaciones, chequeo y cierre de la adopción |
| [Reportes](https://github.com/romeroppau/PAW--Reportes--TPIntegrador) | Reportes de la veterinaria y el motor de recomendaciones |

## Tecnologías

- **Frontend:** HTML5, CSS y JavaScript, sin frameworks.
- **Servidor:** PHP 8 sin frameworks, MySQL 8 con PDO, Nginx como API Gateway, Composer y PHPUnit.
- **Servicios:** Render (aplicación), Vercel (frontend), Aiven (MySQL), Meta WhatsApp Cloud API, Mercado Pago (pruebas), GitHub Actions (pruebas y tareas programadas).

El detalle y la justificación están en [TECNOLOGíA.md](./TECNOLOGíA.md).

## Control de versiones

Cada entrega tiene su tag en los siete repositorios (`entrega-1` … `entrega-5`, y `entrega-final` para la defensa). Se trabaja en ramas que se mergean a `dev` por pull request, y `dev` pasa a `main` (producción).

---

**Grupo La 25** · HuellaVet — Gestión Integral de Veterinaria · Programación en Ambiente Web · Universidad Nacional de Luján, 2026

# Consideraciones de Seguridad — HuellaVet

Las medidas siguen el diagrama de arquitectura v3 (`docs/arquitectura/Arquitectura HuellaVet v3.pdf`): un frontend (SPA), un API Gateway (Nginx) como única entrada pública, cinco módulos (Backend, Pacientes, Inventario, Adopciones y Reportes) y una única base de datos MySQL. Los servicios externos son MercadoPago y WhatsApp.

En este documento, **servidor de despliegue** es la máquina (física o virtual, por ejemplo un VPS) donde se instala y corre todo el sistema: el API Gateway (Nginx), los cinco módulos y MySQL. No es un módulo. Se asume que todo corre en esa misma máquina.

## Red
- **HTTPS/TLS** (TLS es el protocolo que cifra la conexión, y HTTPS es HTTP sobre TLS) obligatorio entre el navegador y el API Gateway (Nginx).
  - El API Gateway (Nginx) **termina TLS** (tiene el certificado, descifra el tráfico HTTPS que llega y se lo pasa al módulo por la red interna).
  - Además **redirige HTTP a HTTPS** (si alguien entra por `http://`, lo manda a `https://`).
- **Entre el API Gateway (Nginx) y los módulos se usa HTTP, no HTTPS.**

  ```
  Navegador ──HTTPS (cifrado, viaja por internet)──> Nginx ──HTTP (127.0.0.1, no sale de la máquina)──> Pacientes
  ```

  - Ese tramo es comunicación entre dos programas de la misma computadora y nunca pasa por una red. Para interceptarlo, alguien tendría que estar ya dentro del servidor de despliegue, y ahí podría leer la base directamente, así que cifrarlo no agrega protección.
  - Así los módulos no tienen que manejar certificados: el cifrado lo resuelve Nginx.
  - Si en algún momento un módulo se mueve a otra máquina, el tráfico hacia él pasaría por la red y tendría que ir por HTTPS.
- El API Gateway (Nginx) es la única entrada pública.
- Los cinco módulos y MySQL solo son accesibles desde la **red interna** (el tráfico que no sale del servidor de despliegue).
  - Se implementa haciendo que cada módulo y MySQL escuchen solo en `127.0.0.1` (localhost), cada uno en su puerto.
  - Ejemplos: Backend en `127.0.0.1:3001`, Pacientes en `127.0.0.1:3002`, MySQL en `127.0.0.1:3306`.
  - Desde internet no se puede llegar a esos puertos. Solo el API Gateway (Nginx), que corre en la misma máquina, les reenvía pedidos.
- **Firewall** (filtro del sistema operativo que decide qué puertos aceptan conexiones de afuera) en el servidor de despliegue: solo se abren los puertos 80 y 443 del API Gateway (Nginx).
- **CORS** (regla del navegador que impide que una página de un dominio lea respuestas de una API de otro dominio, salvo que la API lo permita) restringido al **origen del frontend propio**.
  - El origen es la combinación de protocolo, dominio y puerto, por ejemplo `https://huellavet.com`.
  - La "API" son las direcciones `https://huellavet.com/api/...`, que el API Gateway (Nginx) reenvía a cada módulo. Por ejemplo, `/api/pacientes/...` va a Pacientes y `/api/tienda/...` va a Inventario.
  - El "origen" no es la IP de un módulo. Es la dirección del sitio desde el que se cargó la página que hace el pedido.
  - "Restringido al origen del frontend propio" significa que solo las páginas cargadas desde `https://huellavet.com` pueden leer las respuestas de los módulos.
  - **Ejemplo:**
    1. Ana inicia sesión en `https://huellavet.com`. Su navegador guarda la cookie con su JWT.
    2. En "Mis mascotas", el JavaScript del frontend pide `https://huellavet.com/api/pacientes/mascotas`. Nginx se lo pasa a Pacientes, que le devuelve sus mascotas. Todo bien.
    3. Sin cerrar sesión, Ana entra a otro sitio, `https://sitio-trucho.com`. Esa página tiene un JavaScript escondido que, desde el navegador de Ana, pide `https://huellavet.com/api/pacientes/mascotas`.
    4. **Sin CORS**, ese JavaScript podría leer la respuesta con los datos de Ana y mandárselos al dueño del sitio trucho.
    5. **Con CORS**, antes de dejar que la página lea la respuesta, el navegador se fija desde qué orígenes permite HuellaVet que se lean sus datos. HuellaVet solo permite `https://huellavet.com`. Como la página que pregunta es de `sitio-trucho.com`, el navegador la bloquea.
  - Como el frontend y los módulos se sirven desde el mismo dominio a través del API Gateway (Nginx), no hace falta habilitar CORS para ningún otro origen: por defecto, el navegador ya bloquea a cualquier otro sitio.
- Las **rutas internas** `/internal/*` (endpoints que solo usan los módulos entre sí) quedan bloqueadas en el API Gateway (Nginx): un pedido externo a esas rutas recibe `404`.
- La comunicación entre módulos se autentica con **tokens de servicio**.
  - Un token de servicio es una contraseña larga y aleatoria que identifica a un módulo, no a una persona.
  - No se obtiene de ningún servicio en tiempo de ejecución. El equipo lo genera una sola vez, por ejemplo con `openssl rand -hex 32`, y lo copia en el `.env` del módulo que llama y en el de los módulos que lo reciben.
  - Cada módulo que hace llamadas internas tiene su propio token y lo envía en el header `X-Service-Token`.
  - El módulo destino verifica el token y que ese módulo tenga permiso para el endpoint. Si el token no es válido responde `401`, y si el módulo no tiene permiso, `403`.
  - Los endpoints internos no aceptan el **JWT** de un usuario (ver Aplicación), y los públicos no aceptan tokens de servicio.

  | Destino | Endpoint interno | Quién puede llamarlo |
  |---|---|---|
  | Inventario | `POST /internal/stock/descontar` | Pacientes |
  | Backend | `POST /internal/huellitas/acreditar` | Pacientes, Inventario, Adopciones |
  | Backend | `POST /internal/huellitas/reservar` | Inventario |
  | Backend | `POST /internal/notificaciones` | Pacientes, Adopciones, Reportes |
  | Pacientes | `POST /internal/mascotas/{id}/transferir` | Adopciones |

- Las llamadas internas entre módulos también van por HTTP a `127.0.0.1`, por el mismo motivo: no salen del servidor de despliegue.
- **Webhook de MercadoPago** (aviso HTTP que los servidores de MercadoPago le mandan a una URL nuestra cuando cambia el estado de un pago).
  - Cualquiera podría llamar a esa URL con un falso "pago aprobado".
  - Por eso, antes de marcar un pago como aprobado, se verifica la firma del aviso y se consulta el pago directamente a la API de MercadoPago.

## Sistema Operativo
- Actualizaciones y parches de seguridad regulares en el servidor de despliegue.
- **Principio de mínimo privilegio** (cada programa tiene solo los permisos que necesita): el API Gateway (Nginx), cada uno de los cinco módulos y MySQL corren con su propio usuario de sistema, sin permisos de administrador.
- Cada módulo solo puede leer su propia carpeta y su **`.env`**.
  - El `.env` es el archivo de texto de cada módulo con su configuración secreta: la contraseña de MySQL, el token de servicio, las claves.
  - El módulo lo lee al arrancar. No se sube al repositorio.
- Las imágenes subidas (mascotas, publicaciones y productos) se guardan en una carpeta desde la que no se ejecuta código.
- Deshabilitación de servicios y puertos no utilizados.

## Motor de Base de Datos
- Una única base MySQL. Cada módulo se conecta con su propio usuario de MySQL:
  - con escritura (`INSERT`, `UPDATE`, `DELETE`) solo sobre sus propias tablas;
  - con `SELECT` sobre las tablas de los demás módulos, para las lecturas directas que define la arquitectura;
  - Reportes solo tiene `SELECT`;
  - ningún módulo usa `root`.
- **Consultas parametrizadas** (los datos del usuario se pasan aparte de la sentencia SQL y nunca se interpretan como código) u ORM para evitar **inyección SQL** (meter código SQL dentro de un dato de entrada).
- **Transacciones** (varias operaciones que se guardan todas juntas o no se guarda ninguna) para lo que toca varias tablas. Por ejemplo, la consulta, sus ítems y su factura.
- MySQL escucha solo en `127.0.0.1`: no se puede conectar desde afuera del servidor de despliegue.
- Como la base es única, es el único punto de falla aceptado del diseño:
  - backup diario, guardado fuera del servidor de despliegue y con acceso restringido;
  - se prueba al menos una vez que se pueda restaurar.

## Aplicación
- Contraseñas guardadas con hash **bcrypt** (algoritmo de hash pensado para contraseñas: es lento a propósito y agrega una sal aleatoria, así que es muy costoso adivinarlas por fuerza bruta).
  - Se usa con costo 12.
  - Nunca se guardan en texto plano ni con MD5 o SHA-1.
- Login:
  - el error es el mismo para email y para contraseña ("Email o contraseña incorrectos");
  - 5 intentos fallidos en 15 minutos bloquean el login de esa cuenta durante 15 minutos.
- Autenticación con **JWT** (JSON Web Token: un texto firmado que identifica al usuario que inició sesión; cualquiera puede leer su contenido, pero nadie puede modificarlo sin invalidar la firma).
  - Backend lo firma con su clave privada (RS256), y los otros cuatro módulos lo verifican con la clave pública.
  - Expira en 1 hora y solo lleva el `id` y el `rol`.
  - Se guarda en una **cookie `HttpOnly`, `Secure` y `SameSite=Strict`**: JavaScript no puede leerla, solo viaja por HTTPS y el navegador no la manda en pedidos que vienen de otros sitios. Esto último protege contra **CSRF** (que otro sitio haga acciones en HuellaVet usando la sesión del usuario).
  - **Ejemplo:**
    1. La clienta Ana (id 15) inicia sesión. El pedido pasa por Nginx y llega a Backend, que verifica su contraseña.
    2. Backend le devuelve un JWT firmado con su clave privada, cuyo contenido es `{"id": 15, "rol": "CLIENTE", "exp": <en 1 hora>}`. El navegador lo guarda en una cookie.
    3. Ana abre "Mis mascotas". El navegador pide `GET /api/pacientes/mascotas` y manda la cookie automáticamente.
    4. Pacientes verifica la firma con la clave pública de Backend, sin preguntarle nada a Backend. Lee que es el id 15 con rol CLIENTE y devuelve solo las mascotas de Ana.
    5. Si Ana modifica el token para decir `"id": 16`, la firma deja de coincidir y Pacientes lo rechaza.
    6. Si Backend se cae, Pacientes puede seguir verificando el token, porque tiene la clave pública.
- Control de acceso validado en cada módulo, no solo en el frontend:
  - **por rol:** solo el veterinario carga consultas, administra el stock, modera y aprueba rescatistas; solo un rescatista `APROBADO` publica en adopciones;
  - **por dueño:** un cliente solo ve sus mascotas, turnos, facturas y Huellitas; un rescatista solo ve las postulaciones de sus publicaciones.
- Validación de entradas en el servidor:
  - tipo, largo y rango de cada campo, y rechazo de los datos inválidos;
  - las imágenes solo pueden ser JPG, PNG o WEBP, de hasta 5 MB, y se renombran;
  - los precios y totales siempre los calcula el servidor.
- Sanitización de salidas para prevenir **XSS** (que un usuario cargue código JavaScript en un texto y se ejecute en el navegador de otro):
  - el contenido escrito por usuarios (publicaciones, reseñas, postulaciones) se muestra escapado, nunca como HTML;
  - las respuestas no incluyen campos internos, como el hash de la contraseña o datos de contacto de otros usuarios;
  - los errores no muestran el stack trace ni el SQL.
- Llamadas internas:
  - cada una lleva un `idOperacion` único, para que un reintento desde `operacion_pendiente` no descuente stock ni acredite puntos dos veces;
  - el cuerpo se valida igual que en un endpoint público;
  - el timeout es de 3 segundos.
- Registro de auditoría:
  - accesos y modificaciones a fichas médicas;
  - acciones del veterinario: aprobar rescatistas, moderar, completar adopciones y ajustar stock;
  - logins fallidos y llamadas internas rechazadas;
  - nunca se registran contraseñas, tokens ni datos de pago.

## Ciclo de vida del software
- **Diseño:** decisiones de arquitectura descritas arriba: el API Gateway (Nginx) como única entrada, tokens de servicio entre módulos, un usuario de MySQL por módulo y la tabla de operaciones pendientes.
- **Desarrollo:**
  - revisión de dependencias de terceros con `npm audit` o `composer audit` (comandos de los gestores de paquetes de Node y de PHP que comparan las librerías del proyecto con una base de vulnerabilidades conocidas; se usa el que corresponda al lenguaje de cada módulo);
  - los secretos van en el `.env` de cada módulo, fuera del repositorio, y hay un `.env.example` en cada repo;
  - secretos distintos para desarrollo y para producción.
- **Testing:**
  - pruebas de control de acceso por rol y por dueño;
  - pruebas de inyección SQL y XSS;
  - pruebas de las llamadas internas sin token, con un token inválido y con un módulo sin permiso.
- **Despliegue:** HTTPS en el API Gateway (Nginx), firewall, módulos y MySQL solo en la red interna, y **hardening del servidor** de despliegue (reducir lo que se puede atacar: actualizar, cerrar puertos, quitar servicios que no se usan y correr cada programa con un usuario sin privilegios).
- **Mantenimiento:**
  - actualización de dependencias;
  - monitoreo de logs, en especial los logins fallidos y los rechazos de llamadas internas;
  - control de los backups diarios;
  - si un secreto se filtra, se cambia en el momento.
